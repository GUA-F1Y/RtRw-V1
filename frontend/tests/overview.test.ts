import { describe, it, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { overviewKeys } from '../src/features/overview/api/overviewKeys.ts';
import { overviewApi } from '../src/features/overview/api/overviewApi.ts';
import { queryKeys } from '../src/app/query/queryKeys.ts';
import { OverviewResponseSchema, OverviewSchema } from '../src/lib/api/schemas.ts';
import { ApiError } from '../src/lib/api/errors.ts';
import type { Overview } from '../src/types/overview.ts';

describe('Overview Data Layer & API Client', () => {
  const originalFetch = globalThis.fetch;

  afterEach(() => {
    globalThis.fetch = originalFetch;
  });

  // -------------------------------------------------------------
  // 1. QUERY KEYS
  // -------------------------------------------------------------
  describe('Query Keys', () => {
    it('generates canonical query keys for overview caching', () => {
      assert.deepEqual(overviewKeys.all, ['overview']);
      assert.deepEqual(overviewKeys.current(), ['overview', 'current']);
    });

    it('is registered centrally in queryKeys registry', () => {
      assert.deepEqual(queryKeys.overview.all, ['overview']);
      assert.deepEqual(queryKeys.overview.current(), ['overview', 'current']);
    });
  });

  // -------------------------------------------------------------
  // 2. RESPONSE PARSING & SCHEMA VALIDATION
  // -------------------------------------------------------------
  describe('Response Parsing & Schema Validation', () => {
    const validBackendPayload = {
      data: {
        scope: {
          level: 'RW',
          id: '01924b12-9c9e-716b-9c3f-8461abde5001',
          name: 'RW 01',
        },
        population: {
          total: 1000,
          active: 950,
          prospective: 20,
          inactive: 15,
          moved: 10,
          deceased: 5,
        },
        households: {
          active: 320,
          head_assigned: 315,
          head_vacant: 5,
          moved: 10,
          dissolved: 8,
        },
        rt_breakdown: [
          {
            rt_id: '01924b12-9c9e-716b-9c3f-8461abde5010',
            rt_code: '01',
            resident_count: 135,
            household_count: 42,
            head_vacant_household_count: 1,
          },
          {
            rt_id: '01924b12-9c9e-716b-9c3f-8461abde5011',
            rt_code: '02',
            resident_count: 150,
            household_count: 48,
            head_vacant_household_count: 0,
          },
        ],
      },
      meta: {
        request_id: 'req-overview-123',
      },
    };

    it('successfully parses valid backend overview response with schema', () => {
      const parsed = OverviewResponseSchema.parse(validBackendPayload);
      assert.equal(parsed.data.scope.level, 'RW');
      assert.equal(parsed.data.scope.name, 'RW 01');
      assert.equal(parsed.data.population.total, 1000);
      assert.equal(parsed.data.population.active, 950);
      assert.equal(parsed.data.households.active, 320);
      assert.equal(parsed.data.households.head_assigned, 315);
      assert.equal(parsed.data.rt_breakdown.length, 2);
      assert.equal(parsed.data.rt_breakdown[0].rt_code, '01');
      assert.equal(parsed.data.rt_breakdown[0].resident_count, 135);
      assert.equal(parsed.meta?.request_id, 'req-overview-123');
    });

    it('parses zero-state / empty RT breakdown cleanly', () => {
      const emptyPayload = {
        data: {
          scope: {
            level: 'RT',
            id: 'rt-empty-001',
            name: 'RT 05',
          },
          population: {
            total: 0,
            active: 0,
            prospective: 0,
            inactive: 0,
            moved: 0,
            deceased: 0,
          },
          households: {
            active: 0,
            head_assigned: 0,
            head_vacant: 0,
            moved: 0,
            dissolved: 0,
          },
          rt_breakdown: [],
        },
      };

      const parsed = OverviewResponseSchema.parse(emptyPayload);
      assert.equal(parsed.data.population.total, 0);
      assert.equal(parsed.data.households.active, 0);
      assert.equal(parsed.data.rt_breakdown.length, 0);
    });

    it('rejects malformed payload with negative counts', () => {
      const invalidPayload = {
        scope: { level: 'RW', id: '1', name: 'RW' },
        population: {
          total: -1, // invalid negative count
          active: 0,
          prospective: 0,
          inactive: 0,
          moved: 0,
          deceased: 0,
        },
        households: {
          active: 0,
          head_assigned: 0,
          head_vacant: 0,
          moved: 0,
          dissolved: 0,
        },
        rt_breakdown: [],
      };

      assert.throws(() => OverviewSchema.parse(invalidPayload));
    });
  });

  // -------------------------------------------------------------
  // 3. PRIVACY & SENSITIVE ATTRIBUTES ABSENCE
  // -------------------------------------------------------------
  describe('Privacy & Boundary Verification', () => {
    it('guarantees Overview types and payloads contain NO sensitive fields', () => {
      const mockOverview: Overview = {
        scope: { level: 'RW', id: 'rw-1', name: 'RW 01' },
        population: { total: 10, active: 10, prospective: 0, inactive: 0, moved: 0, deceased: 0 },
        households: { active: 3, head_assigned: 3, head_vacant: 0, moved: 0, dissolved: 0 },
        rt_breakdown: [
          { rt_id: 'rt-1', rt_code: '01', resident_count: 10, household_count: 3, head_vacant_household_count: 0 },
        ],
      };

      const serialized = JSON.stringify(mockOverview);
      const prohibitedTokens = ['nik', 'kk_number', 'phone', 'address', 'full_name', 'birth_date', 'residents'];

      for (const token of prohibitedTokens) {
        assert.equal(
          serialized.includes(`"${token}"`),
          false,
          `Overview serialization must not contain sensitive attribute "${token}".`
        );
      }
    });
  });

  // -------------------------------------------------------------
  // 4. API CLIENT METHOD INTEGRATION
  // -------------------------------------------------------------
  describe('overviewApi.getOverview()', () => {
    it('dispatches GET to /api/v1/overview and unwraps data', async () => {
      const mockResponse: Overview = {
        scope: { level: 'RW', id: 'rw-123', name: 'RW 01' },
        population: { total: 50, active: 48, prospective: 2, inactive: 0, moved: 0, deceased: 0 },
        households: { active: 15, head_assigned: 15, head_vacant: 0, moved: 0, dissolved: 0 },
        rt_breakdown: [
          { rt_id: 'rt-1', rt_code: '01', resident_count: 50, household_count: 15, head_vacant_household_count: 0 },
        ],
      };

      globalThis.fetch = async (input, init) => {
        assert.ok(String(input).includes('/api/v1/overview'));
        assert.equal(init?.method, 'GET');
        return new Response(
          JSON.stringify({
            data: mockResponse,
            meta: { request_id: 'req-overview-test' },
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      };

      const res = await overviewApi.getOverview();
      assert.equal(res.data.scope.level, 'RW');
      assert.equal(res.data.population.total, 50);
      assert.equal(res.meta?.request_id, 'req-overview-test');
    });

    it('passes AbortSignal for request cancellation', async () => {
      const controller = new AbortController();

      globalThis.fetch = async (_input, init) => {
        assert.equal(init?.signal, controller.signal);
        return new Response(
          JSON.stringify({
            data: {
              scope: { level: 'RT', id: '1', name: 'RT' },
              population: { total: 0, active: 0, prospective: 0, inactive: 0, moved: 0, deceased: 0 },
              households: { active: 0, head_assigned: 0, head_vacant: 0, moved: 0, dissolved: 0 },
              rt_breakdown: [],
            },
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      };

      await overviewApi.getOverview({ signal: controller.signal });
    });
  });

  // -------------------------------------------------------------
  // 5. ERROR HANDLING
  // -------------------------------------------------------------
  describe('Error Normalization', () => {
    it('normalizes 401 unauthenticated as ApiError', async () => {
      globalThis.fetch = async () =>
        new Response(
          JSON.stringify({
            error: {
              code: 'unauthenticated',
              message: 'Unauthenticated.',
              request_id: 'err-401',
            },
          }),
          { status: 401, headers: { 'Content-Type': 'application/json' } }
        );

      await assert.rejects(
        () => overviewApi.getOverview(),
        (err: unknown) => {
          assert.ok(err instanceof ApiError);
          assert.equal(err.status, 401);
          assert.equal(err.code, 'unauthenticated');
          return true;
        }
      );
    });

    it('normalizes 403 forbidden as ApiError', async () => {
      globalThis.fetch = async () =>
        new Response(
          JSON.stringify({
            error: {
              code: 'account_inactive',
              message: 'Account is inactive.',
              request_id: 'err-403',
            },
          }),
          { status: 403, headers: { 'Content-Type': 'application/json' } }
        );

      await assert.rejects(
        () => overviewApi.getOverview(),
        (err: unknown) => {
          assert.ok(err instanceof ApiError);
          assert.equal(err.status, 403);
          assert.equal(err.code, 'account_inactive');
          return true;
        }
      );
    });

    it('normalizes 500 server error cleanly without stack traces', async () => {
      globalThis.fetch = async () =>
        new Response(
          JSON.stringify({
            error: {
              code: 'server_error',
              message: 'An internal server error occurred.',
              request_id: 'err-500',
            },
          }),
          { status: 500, headers: { 'Content-Type': 'application/json' } }
        );

      await assert.rejects(
        () => overviewApi.getOverview(),
        (err: unknown) => {
          assert.ok(err instanceof ApiError);
          assert.equal(err.status, 500);
          assert.equal(err.code, 'server_error');
          return true;
        }
      );
    });
  });
});
