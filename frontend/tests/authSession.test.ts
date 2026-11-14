import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { authApi } from '../src/features/auth/api/authApi.ts';
import { getAuthToken, setAuthToken } from '../src/lib/api/client.ts';
import { ApiError } from '../src/lib/api/errors.ts';

// Mock localStorage for Node environment
const mockStorage: Record<string, string> = {};
const fakeLocalStorage = {
  getItem: (key: string) => mockStorage[key] || null,
  setItem: (key: string, val: string) => {
    mockStorage[key] = val;
  },
  removeItem: (key: string) => {
    delete mockStorage[key];
  },
  clear: () => {
    for (const key of Object.keys(mockStorage)) {
      delete mockStorage[key];
    }
  },
};

describe('Authentication Session & Lifecycle', () => {
  const originalFetch = globalThis.fetch;
  const originalLocalStorage = globalThis.localStorage;

  beforeEach(() => {
    fakeLocalStorage.clear();
    // @ts-expect-error Mocking localStorage in Node test runner
    globalThis.localStorage = fakeLocalStorage;
  });

  afterEach(() => {
    globalThis.fetch = originalFetch;
    // @ts-expect-error Restoring localStorage
    globalThis.localStorage = originalLocalStorage;
  });

  it('initializes unauthenticated when no token is present', () => {
    assert.equal(getAuthToken(), null);
  });

  it('hydrates user when valid token is present in storage', async () => {
    setAuthToken('valid-sanctum-token');
    assert.equal(getAuthToken(), 'valid-sanctum-token');

    globalThis.fetch = async (_input, init) => {
      const headers = new Headers(init?.headers);
      assert.equal(headers.get('Authorization'), 'Bearer valid-sanctum-token');

      return new Response(
        JSON.stringify({
          data: {
            id: 'user-100',
            name: 'Ahmad Dahlan',
            email: 'ahmad@rtrw.local',
            status: 'active',
            memberships: [],
          },
          meta: { request_id: 'req-me-1' },
        }),
        {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        }
      );
    };

    const user = await authApi.getMe();
    assert.equal(user.id, 'user-100');
    assert.equal(user.name, 'Ahmad Dahlan');
    assert.equal(user.status, 'active');
  });

  it('clears token on 401 unauthenticated response', async () => {
    setAuthToken('stale-token');
    assert.equal(getAuthToken(), 'stale-token');

    globalThis.fetch = async () => {
      return new Response(
        JSON.stringify({
          error: {
            code: 'unauthenticated',
            message: 'Unauthenticated.',
          },
        }),
        {
          status: 401,
          headers: { 'Content-Type': 'application/json' },
        }
      );
    };

    await assert.rejects(
      async () => {
        await authApi.getMe();
      },
      (err: unknown) => {
        assert.ok(err instanceof ApiError);
        assert.equal(err.status, 401);
        assert.equal(err.code, 'unauthenticated');
        return true;
      }
    );

    assert.equal(getAuthToken(), null);
  });

  it('handles 403 account_inactive without token leak', async () => {
    setAuthToken('active-token-but-suspended-account');

    globalThis.fetch = async () => {
      return new Response(
        JSON.stringify({
          error: {
            code: 'account_inactive',
            message: 'This account is not active.',
          },
        }),
        {
          status: 403,
          headers: { 'Content-Type': 'application/json' },
        }
      );
    };

    await assert.rejects(
      async () => {
        await authApi.getMe();
      },
      (err: unknown) => {
        assert.ok(err instanceof ApiError);
        assert.equal(err.status, 403);
        assert.equal(err.code, 'account_inactive');
        return true;
      }
    );
  });

  it('stores token on successful login and populates session', async () => {
    globalThis.fetch = async (_input, init) => {
      assert.equal(init?.method, 'POST');
      return new Response(
        JSON.stringify({
          data: {
            token: 'fresh-sanctum-token-777',
            token_type: 'Bearer',
            user: {
              id: 'user-777',
              name: 'Ketua RW 01',
              email: 'ketua@rtrw.local',
              status: 'active',
            },
          },
        }),
        {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        }
      );
    };

    const session = await authApi.login({
      email: 'ketua@rtrw.local',
      password: 'password123',
    });

    assert.equal(session.token, 'fresh-sanctum-token-777');
    assert.equal(getAuthToken(), 'fresh-sanctum-token-777');
    assert.equal(session.user.id, 'user-777');
  });

  it('revokes token and clears client storage on logout', async () => {
    setAuthToken('token-to-revoke');
    assert.equal(getAuthToken(), 'token-to-revoke');

    let logoutEndpointCalled = false;
    globalThis.fetch = async (input) => {
      if (String(input).includes('auth/logout')) {
        logoutEndpointCalled = true;
      }
      return new Response(
        JSON.stringify({
          data: { message: 'Logged out successfully.' },
        }),
        {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        }
      );
    };

    await authApi.logout();

    assert.equal(logoutEndpointCalled, true);
    assert.equal(getAuthToken(), null);
  });
});
