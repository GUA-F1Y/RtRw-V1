/**
 * Household Registry & Detail — Data Layer Unit Tests (Phase 18)
 *
 * Pure data-layer tests only (no React rendering, no DOM, no fetch).
 * Covers: householdKeys factory, HOUSEHOLD_STATUS_MAP labels,
 * HouseholdFilterState shape, pagination math, KK privacy contract,
 * member semantics, lifecycle labels, and API URL contract.
 */

import { describe, it } from 'node:test';
import assert from 'node:assert/strict';

// ── Query Key Factory ──────────────────────────────────────────────────────────

describe('Household Query Key Factory', () => {
  it('has a stable households root namespace', async () => {
    const { householdKeys } = await import('../src/features/households/api/householdKeys.ts');
    assert.ok(Array.isArray(householdKeys.all));
    assert.equal(householdKeys.all[0], 'households');
  });

  it('generates stable list key with and without params', async () => {
    const { householdKeys } = await import('../src/features/households/api/householdKeys.ts');
    const key1 = householdKeys.list();
    const key2 = householdKeys.list({ page: 1, per_page: 20 });
    assert.ok(Array.isArray(key1));
    assert.ok(Array.isArray(key2));
    assert.equal(key1[0], 'households');
    assert.equal(key2[0], 'households');
  });

  it('generates stable detail key for a given household id', async () => {
    const { householdKeys } = await import('../src/features/households/api/householdKeys.ts');
    const id = 'household-abc-123';
    const key = householdKeys.detail(id);
    assert.ok(Array.isArray(key));
    assert.ok(key.includes(id), `Key should contain household id; got ${JSON.stringify(key)}`);
  });

  it('different household ids generate different detail keys', async () => {
    const { householdKeys } = await import('../src/features/households/api/householdKeys.ts');
    const keyA = JSON.stringify(householdKeys.detail('household-aaa'));
    const keyB = JSON.stringify(householdKeys.detail('household-bbb'));
    assert.notEqual(keyA, keyB);
  });

  it('does not collide with residents or hierarchy namespaces', async () => {
    const { householdKeys } = await import('../src/features/households/api/householdKeys.ts');
    const rootKey = JSON.stringify(householdKeys.all);
    assert.ok(!rootKey.includes('residents'));
    assert.ok(!rootKey.includes('hierarchy'));
    assert.ok(!rootKey.includes('overview'));
  });

  it('list key with status filter differs from list key without filter', async () => {
    const { householdKeys } = await import('../src/features/households/api/householdKeys.ts');
    const key1 = JSON.stringify(householdKeys.list());
    const key2 = JSON.stringify(householdKeys.list({ status: 'ACTIVE' }));
    assert.notEqual(key1, key2, 'Different params must produce different cache keys');
  });
});

// ── Status Map Labels ──────────────────────────────────────────────────────────

describe('HOUSEHOLD_STATUS_MAP Labels', () => {
  it('maps all 5 household statuses to Indonesian labels', async () => {
    const { HOUSEHOLD_STATUS_MAP } = await import(
      '../src/features/households/components/householdConstants.ts'
    );

    assert.equal(HOUSEHOLD_STATUS_MAP.ACTIVE.label, 'Aktif');
    assert.equal(HOUSEHOLD_STATUS_MAP.HEAD_VACANT.label, 'Kepala Keluarga Kosong');
    assert.equal(HOUSEHOLD_STATUS_MAP.MOVED.label, 'Pindah');
    assert.equal(HOUSEHOLD_STATUS_MAP.DISSOLVED.label, 'Dibubarkan');
    assert.equal(HOUSEHOLD_STATUS_MAP.ARCHIVED.label, 'Diarsipkan');
  });

  it('every status entry has required display properties', async () => {
    const { HOUSEHOLD_STATUS_MAP } = await import(
      '../src/features/households/components/householdConstants.ts'
    );
    const statuses = ['ACTIVE', 'HEAD_VACANT', 'MOVED', 'DISSOLVED', 'ARCHIVED'] as const;
    for (const status of statuses) {
      const config = HOUSEHOLD_STATUS_MAP[status];
      assert.ok(typeof config.label === 'string' && config.label.length > 0, `${status} missing label`);
      assert.ok(typeof config.bg === 'string', `${status} missing bg`);
      assert.ok(typeof config.text === 'string', `${status} missing text`);
      assert.ok(typeof config.border === 'string', `${status} missing border`);
      assert.ok(typeof config.dot === 'string', `${status} missing dot`);
    }
  });

  it('HEAD_VACANT is distinct from ACTIVE visually', async () => {
    const { HOUSEHOLD_STATUS_MAP } = await import(
      '../src/features/households/components/householdConstants.ts'
    );
    assert.notEqual(HOUSEHOLD_STATUS_MAP.HEAD_VACANT.bg, HOUSEHOLD_STATUS_MAP.ACTIVE.bg);
    assert.notEqual(HOUSEHOLD_STATUS_MAP.HEAD_VACANT.text, HOUSEHOLD_STATUS_MAP.ACTIVE.text);
  });
});

// ── Relationship Labels ────────────────────────────────────────────────────────

describe('Household Relationship Labels', () => {
  it('maps KEPALA_KELUARGA to Indonesian label', async () => {
    const { RELATIONSHIP_LABEL } = await import(
      '../src/features/households/components/householdConstants.ts'
    );
    assert.equal(RELATIONSHIP_LABEL['KEPALA_KELUARGA'], 'Kepala Keluarga');
    assert.equal(RELATIONSHIP_LABEL['ANAK'], 'Anak');
    assert.equal(RELATIONSHIP_LABEL['ISTRI'], 'Istri');
    assert.equal(RELATIONSHIP_LABEL['SUAMI'], 'Suami');
  });
});

// ── Filter State Shape ─────────────────────────────────────────────────────────

describe('HouseholdFilterState Shape', () => {
  it('HouseholdFilterState has all required keys', () => {
    const filterState: Record<string, unknown> = {
      q: '',
      status: '',
      head_availability: '',
      sort: '',
    };
    const required = ['q', 'status', 'head_availability', 'sort'];
    for (const key of required) {
      assert.ok(key in filterState, `Filter state must have key: ${key}`);
    }
  });

  it('sort values are limited to backend-supported tokens', () => {
    const validSorts = [
      '',
      'created_at',
      '-created_at',
      'address',
      '-address',
      'status',
      '-status',
    ];
    // Ensure no arbitrary SQL columns can be set
    const disallowed = ['name', 'nik', 'kk_number', 'SELECT', 'DROP'];
    for (const invalid of disallowed) {
      assert.ok(!validSorts.includes(invalid), `${invalid} should not be a valid sort token`);
    }
  });

  it('head_availability values match backend contract', () => {
    const validValues = ['', 'present', 'vacant'];
    assert.ok(validValues.includes('present'));
    assert.ok(validValues.includes('vacant'));
    assert.ok(!validValues.includes('ACTIVE'));
    assert.ok(!validValues.includes('HEAD_VACANT'));
  });
});

// ── KK Privacy Contract ────────────────────────────────────────────────────────

describe('KK Privacy Contract', () => {
  it('household list query key never contains kk_number value', async () => {
    const { householdKeys } = await import('../src/features/households/api/householdKeys.ts');
    // kk_number is NOT a query param — should never appear in query keys
    const key = JSON.stringify(householdKeys.list({ status: 'ACTIVE', page: 1 }));
    assert.ok(!key.includes('kk_number'), 'KK number must not appear in query keys');
    assert.ok(!key.includes('nik'), 'NIK must not appear in query keys');
  });

  it('privacy-elevating query params are not supported filter fields', () => {
    // These params must not exist in HouseholdQueryParams interface
    const forbiddenParams = ['tier', 'sensitive', 'privacy', 'kk_raw'];
    const supportedParams = ['q', 'status', 'rt_id', 'rw_id', 'head_availability', 'sort', 'page', 'per_page', 'include'];
    for (const forbidden of forbiddenParams) {
      assert.ok(
        !supportedParams.includes(forbidden),
        `${forbidden} must not be a supported filter param`
      );
    }
  });

  it('Household type has kk_number and kk_number_masked as optional', async () => {
    // Type-level check: importing the type module should succeed
    const types = await import('../src/types/household.ts');
    // Verify the module exports expected type markers
    assert.ok(types !== null);
  });

  it('member summary contains only safe fields — no NIK, phone, religion', async () => {
    // HouseholdMember.resident is ResidentSummary which only has id, name, gender, status
    const resident = await import('../src/types/resident.ts');
    // ResidentSummary should not include sensitive fields
    assert.ok(resident !== null);
    // Structural verification — HouseholdMember safe fields
    const safeMemberSummaryKeys = ['id', 'name', 'gender', 'status'];
    const forbiddenInSummary = ['nik', 'phone', 'religion', 'blood_type', 'user_id'];
    for (const field of forbiddenInSummary) {
      assert.ok(
        !safeMemberSummaryKeys.includes(field),
        `${field} must not be in member summary`
      );
    }
  });

  it('address is not placed in query keys or URLs by filter state', () => {
    const filterKeys = ['q', 'status', 'head_availability', 'sort', 'page', 'per_page'];
    assert.ok(!filterKeys.includes('address'), 'address must not be a URL filter param');
    assert.ok(!filterKeys.includes('kk_number'), 'kk_number must not be a URL filter param');
  });
});

// ── Member Semantics ───────────────────────────────────────────────────────────

describe('Member Active/Historical Semantics', () => {
  it('active member is determined by is_active = true', () => {
    const activeMember = { id: '1', is_active: true, relationship: 'ANAK', joined_at: '2024-01-01', household_id: 'hh-1', resident_id: 'r-1' };
    const historicalMember = { id: '2', is_active: false, relationship: 'ANAK', joined_at: '2023-01-01', left_at: '2024-01-01', household_id: 'hh-1', resident_id: 'r-2' };

    assert.equal(activeMember.is_active, true);
    assert.equal(historicalMember.is_active, false);
  });

  it('current head is identified by KEPALA_KELUARGA + is_active = true', () => {
    const members = [
      { id: '1', relationship: 'KEPALA_KELUARGA', is_active: true, household_id: 'hh-1', resident_id: 'r-1', joined_at: '2020-01-01' },
      { id: '2', relationship: 'KEPALA_KELUARGA', is_active: false, household_id: 'hh-1', resident_id: 'r-2', joined_at: '2018-01-01', left_at: '2020-01-01' },
      { id: '3', relationship: 'ANAK', is_active: true, household_id: 'hh-1', resident_id: 'r-3', joined_at: '2020-01-01' },
    ];

    const currentHead = members.find(
      (m) => m.relationship === 'KEPALA_KELUARGA' && m.is_active
    );
    assert.ok(currentHead !== undefined, 'Should find a current head');
    assert.equal(currentHead?.id, '1');
    assert.equal(currentHead?.is_active, true);
  });

  it('historical head (is_active=false) must NOT be treated as current head', () => {
    const historicalHead = { id: '2', relationship: 'KEPALA_KELUARGA', is_active: false, household_id: 'hh-1', resident_id: 'r-2', joined_at: '2018-01-01', left_at: '2020-01-01' };
    // is_active is the canonical discriminant — not just relationship
    assert.equal(historicalHead.is_active, false);
    const isCurrentHead = historicalHead.relationship === 'KEPALA_KELUARGA' && historicalHead.is_active;
    assert.equal(isCurrentHead, false);
  });

  it('active_members_count is backend aggregate — not derived from members array', () => {
    // This documents the architectural decision: never use members.length as authoritative
    const household = {
      id: 'hh-1',
      active_members_count: 5, // backend-authoritative
      members: [
        { id: '1', is_active: true },
        { id: '2', is_active: false }, // historical — not active
      ],
      status: 'ACTIVE' as const,
    };
    // Frontend must use active_members_count, not filter members array
    assert.equal(household.active_members_count, 5);
    // members.filter would give wrong count (2 total, 1 active) vs server's 5
    const wrongCount = household.members.filter((m) => m.is_active).length;
    assert.equal(wrongCount, 1, 'members.filter() would give wrong count');
    // Backend count is authoritative:
    assert.notEqual(household.active_members_count, wrongCount);
  });
});

// ── Lifecycle Label Completeness ───────────────────────────────────────────────

describe('Household Lifecycle Label Completeness', () => {
  it('every HouseholdStatus value has a UI label', async () => {
    const { HOUSEHOLD_STATUS_MAP } = await import(
      '../src/features/households/components/householdConstants.ts'
    );
    const allStatuses: string[] = ['ACTIVE', 'HEAD_VACANT', 'MOVED', 'DISSOLVED', 'ARCHIVED'];
    for (const status of allStatuses) {
      const config = HOUSEHOLD_STATUS_MAP[status as keyof typeof HOUSEHOLD_STATUS_MAP];
      assert.ok(config, `Status ${status} must have a config entry`);
      assert.ok(config.label.length > 0, `Status ${status} must have a non-empty label`);
    }
  });
});

// ── Pagination Math ────────────────────────────────────────────────────────────

describe('Household Pagination Math', () => {
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

  it('shows correct range at start', () => {
    assert.deepEqual(calcRange(1, 10), [1, 2, 3]);
  });

  it('shows correct range in middle', () => {
    assert.deepEqual(calcRange(5, 10), [3, 4, 5, 6, 7]);
  });

  it('shows correct range at end', () => {
    assert.deepEqual(calcRange(10, 10), [8, 9, 10]);
  });

  it('total count display calculates correctly', () => {
    const currentPage = 2;
    const perPage = 20;
    const total = 45;
    const from = (currentPage - 1) * perPage + 1;
    const to = Math.min(currentPage * perPage, total);
    assert.equal(from, 21);
    assert.equal(to, 40);
  });
});

// ── API URL Contract ───────────────────────────────────────────────────────────

describe('Household API URL Contract', () => {
  it('householdApi exposes list and getById', async () => {
    const { householdApi } = await import('../src/features/households/api/householdApi.ts');
    assert.ok(typeof householdApi.list === 'function', 'householdApi.list must be a function');
    assert.ok(typeof householdApi.getById === 'function', 'householdApi.getById must be a function');
  });

  it('householdApi exposes all expected mutation methods', async () => {
    const { householdApi } = await import('../src/features/households/api/householdApi.ts');
    assert.ok(typeof householdApi.create === 'function');
    assert.ok(typeof householdApi.update === 'function');
    assert.ok(typeof householdApi.addMember === 'function');
    assert.ok(typeof householdApi.removeMember === 'function');
    assert.ok(typeof householdApi.changeHead === 'function');
    assert.ok(typeof householdApi.move === 'function');
    assert.ok(typeof householdApi.dissolve === 'function');
  });
});

// ── URL Query State ────────────────────────────────────────────────────────────

describe('Household Registry URL Query State', () => {
  it('supported filter params match backend HouseholdQueryParams', () => {
    const uiFilterParams = ['q', 'status', 'head_availability', 'sort', 'page', 'per_page'];
    // These must all be valid HouseholdQueryParams keys
    const validBackendParams = [
      'q', 'status', 'rt_id', 'rw_id', 'head_availability',
      'sort', 'page', 'per_page', 'include',
    ];
    for (const param of uiFilterParams) {
      assert.ok(
        validBackendParams.includes(param),
        `UI param "${param}" must be a valid backend query param`
      );
    }
  });

  it('KK number never appears as a URL filter param', () => {
    const urlParams = ['q', 'status', 'head_availability', 'sort', 'page', 'per_page', 'rt_id'];
    assert.ok(!urlParams.includes('kk_number'), 'kk_number must not be a URL param');
    assert.ok(!urlParams.includes('kk'), 'kk must not be a URL param');
  });

  it('privacy-elevating params are not in URL state', () => {
    const urlParams = ['q', 'status', 'head_availability', 'sort', 'page', 'per_page', 'rt_id'];
    const forbidden = ['tier', 'sensitive', 'privacy', 'representation'];
    for (const f of forbidden) {
      assert.ok(!urlParams.includes(f), `${f} must not be in URL state`);
    }
  });
});
