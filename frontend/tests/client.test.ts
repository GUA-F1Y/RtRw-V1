import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { apiClient, apiRequest, getAuthToken, setAuthToken } from '../src/lib/api/client.ts';
import { ApiError } from '../src/lib/api/errors.ts';

// Mock localStorage for Node test runner
const memoryStorage: Record<string, string> = {};
const mockLocalStorage = {
  getItem: (key: string) => memoryStorage[key] || null,
  setItem: (key: string, val: string) => {
    memoryStorage[key] = val;
  },
  removeItem: (key: string) => {
    delete memoryStorage[key];
  },
  clear: () => {
    for (const key of Object.keys(memoryStorage)) {
      delete memoryStorage[key];
    }
  },
};

describe('API Client Transport', () => {
  const originalFetch = globalThis.fetch;
  const originalLocalStorage = globalThis.localStorage;

  beforeEach(() => {
    mockLocalStorage.clear();
    // @ts-expect-error Mocking localStorage in Node environment
    globalThis.localStorage = mockLocalStorage;
  });

  afterEach(() => {
    globalThis.fetch = originalFetch;
    // @ts-expect-error Restoring localStorage
    globalThis.localStorage = originalLocalStorage;
  });

  it('unwraps success envelope and attaches request_id from headers if missing in body', async () => {
    globalThis.fetch = async (input, init) => {
      assert.ok(String(input).includes('/api/v1/test-endpoint'));
      assert.equal(init?.method, 'GET');
      const headers = new Headers(init?.headers);
      assert.equal(headers.get('Accept'), 'application/json');

      return new Response(
        JSON.stringify({
          data: { id: 'item-1', name: 'Test' },
          meta: {},
        }),
        {
          status: 200,
          headers: {
            'Content-Type': 'application/json',
            'X-Request-Id': 'header-req-123',
          },
        }
      );
    };

    const response = await apiClient.get<{ id: string; name: string }>('test-endpoint');
    assert.deepEqual(response.data, { id: 'item-1', name: 'Test' });
    assert.equal(response.meta?.request_id, 'header-req-123');
  });

  it('handles 204 No Content with empty body and captures request ID', async () => {
    globalThis.fetch = async () => {
      return new Response(null, {
        status: 204,
        headers: {
          'X-Request-Id': 'req-no-content-789',
        },
      });
    };

    const response = await apiClient.delete('test-delete');
    assert.equal(response.data, null);
    assert.equal(response.meta?.request_id, 'req-no-content-789');
  });

  it('injects Bearer token from auth storage into Authorization header', async () => {
    setAuthToken('my-secret-sanctum-token');
    assert.equal(getAuthToken(), 'my-secret-sanctum-token');

    let interceptedAuthHeader: string | null = null;
    globalThis.fetch = async (_input, init) => {
      const headers = new Headers(init?.headers);
      interceptedAuthHeader = headers.get('Authorization');
      return new Response(JSON.stringify({ data: { ok: true } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      });
    };

    await apiClient.get('protected');
    assert.equal(interceptedAuthHeader, 'Bearer my-secret-sanctum-token');
  });

  it('injects Idempotency-Key header when specified', async () => {
    let interceptedKey: string | null = null;
    globalThis.fetch = async (_input, init) => {
      const headers = new Headers(init?.headers);
      interceptedKey = headers.get('Idempotency-Key');
      return new Response(JSON.stringify({ data: { success: true } }), {
        status: 201,
        headers: { 'Content-Type': 'application/json' },
      });
    };

    await apiRequest('residents', {
      method: 'POST',
      body: { name: 'Test' },
      idempotencyKey: 'idemp-uuid-555',
    });

    assert.equal(interceptedKey, 'idemp-uuid-555');
  });

  it('clears auth token when receiving 401 Unauthorized', async () => {
    setAuthToken('token-to-be-cleared');

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
        await apiClient.get('me');
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

  it('supports request cancellation via AbortSignal', async () => {
    const controller = new AbortController();

    globalThis.fetch = async (_input, init) => {
      const signal = init?.signal;
      if (signal?.aborted) {
        throw new DOMException('The operation was aborted', 'AbortError');
      }
      return new Promise((_resolve, reject) => {
        signal?.addEventListener('abort', () => {
          reject(new DOMException('The operation was aborted', 'AbortError'));
        });
      });
    };

    const promise = apiClient.get('long-request', { signal: controller.signal });
    controller.abort();

    await assert.rejects(promise, (err: unknown) => {
      assert.ok(err instanceof Error);
      assert.equal(err.name, 'AbortError');
      return true;
    });
  });
});
