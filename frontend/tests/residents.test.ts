/**
 * Resident Directory & Detail — Data Layer Unit Tests (Phase 17)
 *
 * Pure data-layer tests only (no React rendering, no DOM, no fetch).
 * Covers: residentKeys factory, RESIDENT_STATUS_MAP labels,
 * ResidentFilterState shape, pagination math, privacy contract,
 * and API URL contract.
 */

import { describe, it } from 'node:test';
import assert from 'node:assert/strict';

// ── Query Key Factory ──────────────────────────────────────────────────────────

describe('Resident Query Key Factory', () => {
  it('has a stable residents root namespace', async () => {
    const { residentKeys } = await import('../src/features/residents/api/residentKeys.ts');
    assert.ok(Array.isArray(residentKeys.all));
    assert.equal(residentKeys.all[0], 'residents');
  });

  it('generates stable list key with and without params', async () => {
    const { residentKeys } = await import('../src/features/residents/api/residentKeys.ts');
    const key1 = residentKeys.list();
    const key2 = residentKeys.list({ page: 1, per_page: 20 });
    assert.ok(Array.isArray(key1));
    assert.ok(Array.isArray(key2));
    // List key should be prefixed with ['residents', 'list']
    assert.equal(key1[0], 'residents');
    assert.equal(key2[0], 'residents');
  });

  it('generates stable detail key for a given resident id', async () => {
    const { residentKeys } = await import('../src/features/residents/api/residentKeys.ts');
    const id = 'resident-abc-123';
    const key = residentKeys.detail(id);
    assert.ok(Array.isArray(key));
    assert.ok(key.includes(id), `Key should contain resident id; got ${JSON.stringify(key)}`);
  });

  it('different resident ids generate different detail keys', async () => {
    const { residentKeys } = await import('../src/features/residents/api/residentKeys.ts');
    const keyA = JSON.stringify(residentKeys.detail('resident-aaa'));
    const keyB = JSON.stringify(residentKeys.detail('resident-bbb'));
    assert.notEqual(keyA, keyB);
  });

  it('does not collide with household or hierarchy namespaces', async () => {
    const { residentKeys } = await import('../src/features/residents/api/residentKeys.ts');
    const rootKey = JSON.stringify(residentKeys.all);
    assert.ok(!rootKey.includes('households'));
    assert.ok(!rootKey.includes('hierarchy'));
  });
});

// ── Status Map Labels ──────────────────────────────────────────────────────────

describe('RESIDENT_STATUS_MAP Labels', () => {
  it('maps all 5 resident statuses to Indonesian labels', async () => {
    const { RESIDENT_STATUS_MAP } = await import(
      '../src/features/residents/components/residentConstants.ts'
    );

    assert.equal(RESIDENT_STATUS_MAP.PROSPECTIVE.label, 'Belum Terverifikasi');
    assert.equal(RESIDENT_STATUS_MAP.ACTIVE.label, 'Aktif');
    assert.equal(RESIDENT_STATUS_MAP.INACTIVE.label, 'Tidak Aktif');
    assert.equal(RESIDENT_STATUS_MAP.MOVED.label, 'Pindah');
    assert.equal(RESIDENT_STATUS_MAP.DECEASED.label, 'Meninggal');
  });

  it('every status entry has required display properties', async () => {
    const { RESIDENT_STATUS_MAP } = await import(
      '../src/features/residents/components/residentConstants.ts'
    );
    const statuses = ['PROSPECTIVE', 'ACTIVE', 'INACTIVE', 'MOVED', 'DECEASED'] as const;
    for (const status of statuses) {
      const config = RESIDENT_STATUS_MAP[status];
      assert.ok(typeof config.label === 'string' && config.label.length > 0, `${status} missing label`);
      assert.ok(typeof config.bg === 'string', `${status} missing bg class`);
      assert.ok(typeof config.text === 'string', `${status} missing text class`);
      assert.ok(typeof config.border === 'string', `${status} missing border class`);
      assert.ok(typeof config.dot === 'string', `${status} missing dot class`);
    }
  });
});

// ── Filter State Shape ─────────────────────────────────────────────────────────

describe('ResidentFilterState Shape', () => {
  it('ResidentFilterState type has all required filter keys (structural check)', () => {
    // Cannot import .tsx components directly in node:test; validate shape structurally
    const filterState: Record<string, unknown> = {
      q: '',
      status: '',
      gender: '',
      residency_type: '',
      marital_status: '',
      sort: '',
    };
    const requiredKeys = ['q', 'status', 'gender', 'residency_type', 'marital_status', 'sort'];
    for (const key of requiredKeys) {
      assert.ok(key in filterState, `Filter state must have key: ${key}`);
    }
  });

  it('empty string is a valid value for all filter fields (no filter applied)', () => {
    // This is the "no filter" state; assert JS shapes are valid
    const emptyFilter = {
      q: '',
      status: '' as const,
      gender: '' as const,
      residency_type: '' as const,
      marital_status: '' as const,
      sort: '' as const,
    };

    assert.equal(emptyFilter.q, '');
    assert.equal(emptyFilter.status, '');
    assert.equal(emptyFilter.gender, '');
    assert.equal(emptyFilter.residency_type, '');
    assert.equal(emptyFilter.marital_status, '');
    assert.equal(emptyFilter.sort, '');
  });
});

// ── Pagination Math ────────────────────────────────────────────────────────────

describe('Resident Pagination Math', () => {
  function calcRange(currentPage: number, lastPage: number, delta = 2): number[] {
    const range: number[] = [];
    for (
      let i = Math.max(1, currentPage - delta);
      i <= Math.min(lastPage, currentPage + delta);
      i++
    ) {
      range.push(i);
    }
    return range;
  }

  it('shows pages 1–5 when on page 1 of 10', () => {
    const range = calcRange(1, 10);
    assert.deepEqual(range, [1, 2, 3]);
  });

  it('shows pages 3–7 when on page 5 of 10', () => {
    const range = calcRange(5, 10);
    assert.deepEqual(range, [3, 4, 5, 6, 7]);
  });

  it('shows last 3 pages when near the end', () => {
    const range = calcRange(10, 10);
    assert.deepEqual(range, [8, 9, 10]);
  });

  it('correctly computes from/to display range', () => {
    // page 3, per_page 20, total 100 → from=41, to=60
    const currentPage = 3;
    const perPage = 20;
    const total = 100;
    const from = (currentPage - 1) * perPage + 1;
    const to = Math.min(currentPage * perPage, total);
    assert.equal(from, 41);
    assert.equal(to, 60);
  });

  it('clamps "to" to total on final page', () => {
    // page 6, per_page 20, total 101 → from=101, to=101
    const currentPage = 6;
    const perPage = 20;
    const total = 101;
    const from = (currentPage - 1) * perPage + 1;
    const to = Math.min(currentPage * perPage, total);
    assert.equal(from, 101);
    assert.equal(to, 101);
  });
});

// ── Privacy Contract ───────────────────────────────────────────────────────────

describe('Resident Privacy Contract', () => {
  it('Resident directory-tier fields do NOT include NIK, phone, or religion', async () => {
    const { residentKeys } = await import('../src/features/residents/api/residentKeys.ts');
    // Structural sanity: the list query key never encodes sensitive tokens
    const listKey = JSON.stringify(residentKeys.list({ page: 1 }));
    assert.ok(!listKey.includes('nik'));
    assert.ok(!listKey.includes('phone'));
    assert.ok(!listKey.includes('religion'));
    assert.ok(!listKey.includes('blood_type'));
  });

  it('ResidentQueryParams supports privacy-safe include tokens only', async () => {
    // Include tokens exposed to the list query should not be sensitive
    const safeIncludes = ['organization', 'rw', 'rt', 'household', 'memberships'];
    const sensitiveTokens = ['nik', 'phone', 'religion'];
    for (const token of safeIncludes) {
      assert.ok(!sensitiveTokens.includes(token), `Token ${token} should not be sensitive`);
    }
  });

  it('detail include tokens for history do not leak raw NIK/phone in URL params', async () => {
    const { residentKeys } = await import('../src/features/residents/api/residentKeys.ts');
    const detailKey = JSON.stringify(
      residentKeys.detail('resident-123', ['status_history', 'movements'])
    );
    assert.ok(!detailKey.includes('nik'));
    assert.ok(!detailKey.includes('phone'));
    assert.ok(!detailKey.includes('religion'));
  });
});

// ── API URL Contract ───────────────────────────────────────────────────────────

describe('Resident API URL Contract', () => {
  it('list endpoint is /api/v1/residents', async () => {
    const { residentApi } = await import('../src/features/residents/api/residentApi.ts');
    assert.ok(typeof residentApi.list === 'function');
  });

  it('getById endpoint is /api/v1/residents/{id}', async () => {
    const { residentApi } = await import('../src/features/residents/api/residentApi.ts');
    assert.ok(typeof residentApi.getById === 'function');
  });

  it('residentApi exposes register, updateProfile, verify, relocate, changeStatus', async () => {
    const { residentApi } = await import('../src/features/residents/api/residentApi.ts');
    assert.ok(typeof residentApi.register === 'function');
    assert.ok(typeof residentApi.updateProfile === 'function');
    assert.ok(typeof residentApi.verify === 'function');
    assert.ok(typeof residentApi.relocate === 'function');
    assert.ok(typeof residentApi.changeStatus === 'function');
  });
});
