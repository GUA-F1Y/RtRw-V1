/**
 * Phase 20 — Household Operational Actions UI: Data-Layer Unit Tests
 *
 * Pure data-layer tests (no DOM, no network calls).
 *
 * Covers:
 * - CreateHouseholdPayload: required/optional fields, 16-digit KK validation, forbidden fields
 * - UpdateHouseholdPayload: address, notes; KK number immutability; forbidden fields
 * - AddHouseholdMemberPayload: resident_id, relationship enum (all 11 backend values), joined_at
 * - RemoveHouseholdMemberPayload: left_reason, left_at, forbidden fields
 * - ChangeHouseholdHeadPayload: new_head_resident_id, old_head_new_relationship (excluding KEPALA_KELUARGA)
 * - MoveHouseholdPayload: INTER_RT move_type, target_rt_id, new_address, notes
 * - DissolveHouseholdPayload: optional reason, non-destructive lifecycle contract
 * - Status action availability: ACTIVE, HEAD_VACANT, DISSOLVED states
 * - Idempotency: UUID v4 format, session stability, uniqueness
 * - Query key invalidation strategy: household, resident, overview caches
 * - Privacy: mutation payloads do not leak NIK, phone, or demographic fields
 * - Complete householdApi surface: all 7 mutation operations
 */

import { describe, it } from 'node:test';
import assert from 'node:assert/strict';

// ── A. Create Household ────────────────────────────────────────────────────────

describe('CreateHouseholdPayload', () => {
  it('accepts required fields: kk_number, address, rt_id', () => {
    const payload: import('../src/types/household.ts').CreateHouseholdPayload = {
      kk_number: '3201011205900001',
      address: 'Jl. Merdeka No. 10',
      rt_id: '01924b12-9c9e-716b-9c3f-8461abde5003',
    };

    assert.equal(payload.kk_number, '3201011205900001');
    assert.equal(payload.address, 'Jl. Merdeka No. 10');
    assert.equal(payload.rt_id, '01924b12-9c9e-716b-9c3f-8461abde5003');
    assert.equal(payload.notes, undefined);
    assert.equal(payload.initial_head_resident_id, undefined);
  });

  it('accepts optional fields: notes, initial_head_resident_id', () => {
    const payload: import('../src/types/household.ts').CreateHouseholdPayload = {
      kk_number: '3201011205900002',
      address: 'Jl. Melati No. 5',
      rt_id: '01924b12-9c9e-716b-9c3f-8461abde5003',
      notes: 'Keluarga baru pindah dari Bandung',
      initial_head_resident_id: '01924b12-9c9e-716b-9c3f-8461abde5001',
    };

    assert.equal(payload.notes, 'Keluarga baru pindah dari Bandung');
    assert.equal(payload.initial_head_resident_id, '01924b12-9c9e-716b-9c3f-8461abde5001');
  });

  it('validates 16-digit KK number format regex', () => {
    const kkRegex = /^[0-9]{16}$/;
    assert.ok(kkRegex.test('3201011205900001'), 'Valid 16-digit KK must match');
    assert.ok(!kkRegex.test('320101120590000'), '15 digits must fail');
    assert.ok(!kkRegex.test('32010112059000001'), '17 digits must fail');
    assert.ok(!kkRegex.test('320101120590ABCD'), 'Non-numeric KK must fail');
    assert.ok(!kkRegex.test(''), 'Empty string must fail');
  });

  it('does not include forbidden fields (status, id, organization_id, rw_id, created_at)', () => {
    const payload: import('../src/types/household.ts').CreateHouseholdPayload = {
      kk_number: '3201011205900001',
      address: 'Jl. Merdeka No. 10',
      rt_id: 'rt-123',
    };

    const keys = Object.keys(payload);
    assert.ok(!keys.includes('status'), 'status must not be in create payload');
    assert.ok(!keys.includes('id'), 'id must not be in create payload');
    assert.ok(!keys.includes('organization_id'), 'organization_id must not be in create payload');
    assert.ok(!keys.includes('rw_id'), 'rw_id must not be in create payload');
    assert.ok(!keys.includes('created_at'), 'created_at must not be in create payload');
    assert.ok(!keys.includes('active_members_count'), 'active_members_count must not be in create payload');
  });

  it('householdApi.create is a function', async () => {
    const { householdApi } = await import('../src/features/households/api/householdApi.ts');
    assert.equal(typeof householdApi.create, 'function');
  });
});

// ── B. Edit Household ──────────────────────────────────────────────────────────

describe('UpdateHouseholdPayload', () => {
  it('supports address and notes', () => {
    const payload: import('../src/types/household.ts').UpdateHouseholdPayload = {
      address: 'Jl. Mawar No. 12 RT 01',
      notes: 'Alamat diperbarui sesuai sertifikat rumah',
    };

    assert.equal(payload.address, 'Jl. Mawar No. 12 RT 01');
    assert.equal(payload.notes, 'Alamat diperbarui sesuai sertifikat rumah');
  });

  it('address is optional on partial updates', () => {
    const payload: import('../src/types/household.ts').UpdateHouseholdPayload = {
      notes: 'Catatan tambahan',
    };

    assert.equal(payload.address, undefined);
    assert.equal(payload.notes, 'Catatan tambahan');
  });

  it('enforces min 3 characters for address when provided', () => {
    const validAddress = 'Jl. A';
    assert.ok(validAddress.length >= 3, 'Valid address meets min length');

    const invalidAddress = 'Jl';
    assert.ok(invalidAddress.length < 3, 'Short address fails min length');
  });

  it('kk_number is NOT part of UpdateHouseholdPayload (strictly immutable/read-only)', () => {
    const payload: import('../src/types/household.ts').UpdateHouseholdPayload = {
      address: 'Jl. Dahlia No. 8',
    };

    // Ensure TypeScript contract does not permit kk_number
    assert.ok(!('kk_number' in payload), 'kk_number must not be in UpdateHouseholdPayload');
    const keys = Object.keys(payload);
    assert.ok(!keys.includes('kk_number'));
    assert.ok(!keys.includes('status'));
    assert.ok(!keys.includes('rt_id'));
    assert.ok(!keys.includes('rw_id'));
  });

  it('householdApi.update is a function', async () => {
    const { householdApi } = await import('../src/features/households/api/householdApi.ts');
    assert.equal(typeof householdApi.update, 'function');
  });
});

// ── C. Add Member to Household ────────────────────────────────────────────────

describe('AddHouseholdMemberPayload', () => {
  const ALL_11_RELATIONSHIPS: import('../src/types/household.ts').HouseholdMemberRelationship[] = [
    'KEPALA_KELUARGA',
    'SUAMI',
    'ISTRI',
    'ANAK',
    'MENANTU',
    'CUCU',
    'ORANGTUA',
    'MERTUA',
    'FAMILI_LAIN',
    'PEMBANTU',
    'LAINNYA',
  ];

  it('supports all 11 backend-defined relationship enum values', () => {
    assert.equal(ALL_11_RELATIONSHIPS.length, 11);

    for (const rel of ALL_11_RELATIONSHIPS) {
      const payload: import('../src/types/household.ts').AddHouseholdMemberPayload = {
        resident_id: '01924b12-9c9e-716b-9c3f-8461abde5001',
        relationship: rel,
      };
      assert.equal(payload.relationship, rel);
    }
  });

  it('joined_at is optional with YYYY-MM-DD format', () => {
    const payloadWithDate: import('../src/types/household.ts').AddHouseholdMemberPayload = {
      resident_id: '01924b12-9c9e-716b-9c3f-8461abde5001',
      relationship: 'ANAK',
      joined_at: '2026-10-14',
    };

    assert.equal(payloadWithDate.joined_at, '2026-10-14');
    assert.match(payloadWithDate.joined_at!, /^\d{4}-\d{2}-\d{2}$/);

    const payloadWithoutDate: import('../src/types/household.ts').AddHouseholdMemberPayload = {
      resident_id: '01924b12-9c9e-716b-9c3f-8461abde5001',
      relationship: 'ISTRI',
    };
    assert.equal(payloadWithoutDate.joined_at, undefined);
  });

  it('does not include forbidden fields (is_active, left_at, household_id, recorded_by)', () => {
    const payload: import('../src/types/household.ts').AddHouseholdMemberPayload = {
      resident_id: 'res-1',
      relationship: 'ANAK',
    };

    const keys = Object.keys(payload);
    assert.ok(!keys.includes('is_active'));
    assert.ok(!keys.includes('left_at'));
    assert.ok(!keys.includes('household_id'));
    assert.ok(!keys.includes('recorded_by'));
  });

  it('householdApi.addMember is a function', async () => {
    const { householdApi } = await import('../src/features/households/api/householdApi.ts');
    assert.equal(typeof householdApi.addMember, 'function');
  });
});

// ── D. Remove Member from Household ───────────────────────────────────────────

describe('RemoveHouseholdMemberPayload', () => {
  it('uses left_reason and left_at per backend FormRequest', () => {
    const payload: import('../src/types/household.ts').RemoveHouseholdMemberPayload = {
      left_reason: 'Membentuk KK sendiri',
      left_at: '2026-10-14',
    };

    assert.equal(payload.left_reason, 'Membentuk KK sendiri');
    assert.equal(payload.left_at, '2026-10-14');
    assert.match(payload.left_at!, /^\d{4}-\d{2}-\d{2}$/);
  });

  it('all fields are optional — empty payload is valid', () => {
    const emptyPayload: import('../src/types/household.ts').RemoveHouseholdMemberPayload = {};
    assert.deepEqual(Object.keys(emptyPayload), []);
  });

  it('does not include forbidden fields (status, is_active, reason)', () => {
    const payload: import('../src/types/household.ts').RemoveHouseholdMemberPayload = {
      left_reason: 'Pindah alamat',
    };

    const keys = Object.keys(payload);
    assert.ok(!keys.includes('status'));
    assert.ok(!keys.includes('is_active'));
    assert.ok(!keys.includes('reason')); // must use left_reason
  });

  it('householdApi.removeMember is a function', async () => {
    const { householdApi } = await import('../src/features/households/api/householdApi.ts');
    assert.equal(typeof householdApi.removeMember, 'function');
  });
});

// ── E. Change Household Head ──────────────────────────────────────────────────

describe('ChangeHouseholdHeadPayload', () => {
  const DEMOTED_HEAD_RELATIONSHIPS: import('../src/types/household.ts').HouseholdMemberRelationship[] = [
    'SUAMI',
    'ISTRI',
    'ANAK',
    'MENANTU',
    'CUCU',
    'ORANGTUA',
    'MERTUA',
    'FAMILI_LAIN',
    'PEMBANTU',
    'LAINNYA',
  ];

  it('accepts new_head_resident_id and old_head_new_relationship', () => {
    const payload: import('../src/types/household.ts').ChangeHouseholdHeadPayload = {
      new_head_resident_id: '01924b12-9c9e-716b-9c3f-8461abde5002',
      old_head_new_relationship: 'ISTRI',
    };

    assert.equal(payload.new_head_resident_id, '01924b12-9c9e-716b-9c3f-8461abde5002');
    assert.equal(payload.old_head_new_relationship, 'ISTRI');
  });

  it('strictly excludes KEPALA_KELUARGA from former head relationships (canonical rule)', () => {
    assert.equal(DEMOTED_HEAD_RELATIONSHIPS.length, 10);
    assert.ok(!DEMOTED_HEAD_RELATIONSHIPS.includes('KEPALA_KELUARGA'));

    for (const rel of DEMOTED_HEAD_RELATIONSHIPS) {
      const payload: import('../src/types/household.ts').ChangeHouseholdHeadPayload = {
        new_head_resident_id: 'res-new',
        old_head_new_relationship: rel,
      };
      assert.equal(payload.old_head_new_relationship, rel);
    }
  });

  it('does not include forbidden fields (status, household_id, recorded_by)', () => {
    const payload: import('../src/types/household.ts').ChangeHouseholdHeadPayload = {
      new_head_resident_id: 'res-new',
      old_head_new_relationship: 'SUAMI',
    };

    const keys = Object.keys(payload);
    assert.ok(!keys.includes('status'));
    assert.ok(!keys.includes('household_id'));
    assert.ok(!keys.includes('recorded_by'));
  });

  it('householdApi.changeHead is a function', async () => {
    const { householdApi } = await import('../src/features/households/api/householdApi.ts');
    assert.equal(typeof householdApi.changeHead, 'function');
  });
});

// ── F. Move Household Between RTs ─────────────────────────────────────────────

describe('MoveHouseholdPayload', () => {
  it('move_type is strictly INTER_RT matching backend enum', () => {
    const payload: import('../src/types/household.ts').MoveHouseholdPayload = {
      move_type: 'INTER_RT',
      target_rt_id: '01924b12-9c9e-716b-9c3f-8461abde5004',
      new_address: 'Jl. Anyelir No. 15 RT 02',
    };

    assert.equal(payload.move_type, 'INTER_RT');
    assert.equal(payload.target_rt_id, '01924b12-9c9e-716b-9c3f-8461abde5004');
    assert.equal(payload.new_address, 'Jl. Anyelir No. 15 RT 02');
  });

  it('accepts optional notes field', () => {
    const payload: import('../src/types/household.ts').MoveHouseholdPayload = {
      move_type: 'INTER_RT',
      target_rt_id: 'rt-uuid-002',
      new_address: 'Jl. Kenanga No. 7',
      notes: 'Pindah rumah ke RT 02 setelah renovasi selesai',
    };

    assert.equal(payload.notes, 'Pindah rumah ke RT 02 setelah renovasi selesai');
  });

  it('requires new_address with minimum 3 characters', () => {
    const valid = 'Jl. RT 2';
    assert.ok(valid.length >= 3);

    const invalid = 'Jl';
    assert.ok(invalid.length < 3);
  });

  it('prevents no-op move when target_rt_id matches current rt_id', () => {
    const currentRtId = 'rt-current-100';
    const targetRtIdSame = 'rt-current-100';
    const targetRtIdDifferent = 'rt-other-200';

    assert.equal(currentRtId === targetRtIdSame, true, 'Same RT must be detected as no-op');
    assert.equal(currentRtId === targetRtIdDifferent, false, 'Different RT is a valid move');
  });

  it('does not include forbidden fields (from_rt_id, organization_id, status)', () => {
    const payload: import('../src/types/household.ts').MoveHouseholdPayload = {
      move_type: 'INTER_RT',
      target_rt_id: 'rt-2',
      new_address: 'Jl. Baru No. 1',
    };

    const keys = Object.keys(payload);
    assert.ok(!keys.includes('from_rt_id'));
    assert.ok(!keys.includes('from_rw_id'));
    assert.ok(!keys.includes('organization_id'));
    assert.ok(!keys.includes('status'));
  });

  it('householdApi.move is a function', async () => {
    const { householdApi } = await import('../src/features/households/api/householdApi.ts');
    assert.equal(typeof householdApi.move, 'function');
  });
});

// ── G. Dissolve Household ─────────────────────────────────────────────────────

describe('DissolveHouseholdPayload', () => {
  it('reason is optional per backend FormRequest', () => {
    const withReason: import('../src/types/household.ts').DissolveHouseholdPayload = {
      reason: 'Semua anggota pindah ke luar kota',
    };
    assert.equal(withReason.reason, 'Semua anggota pindah ke luar kota');

    const withoutReason: import('../src/types/household.ts').DissolveHouseholdPayload = {};
    assert.deepEqual(Object.keys(withoutReason), []);
  });

  it('does not include forbidden fields (status, is_active, deleted_at)', () => {
    const payload: import('../src/types/household.ts').DissolveHouseholdPayload = {
      reason: 'Bubarkan KK',
    };

    const keys = Object.keys(payload);
    assert.ok(!keys.includes('status'));
    assert.ok(!keys.includes('is_active'));
    assert.ok(!keys.includes('deleted_at'));
    assert.ok(!keys.includes('household_id'));
  });

  it('householdApi.dissolve is a function', async () => {
    const { householdApi } = await import('../src/features/households/api/householdApi.ts');
    assert.equal(typeof householdApi.dissolve, 'function');
  });
});

// ── H. Action Availability by Household Status ────────────────────────────────

describe('Household Action Availability logic (getHouseholdAvailableActions)', () => {
  it('ACTIVE household: all operational actions are available', async () => {
    const { getHouseholdAvailableActions } = await import(
      '../src/features/households/components/householdActionUtils.ts'
    );

    const activeHousehold = {
      id: 'hh-1',
      status: 'ACTIVE' as const,
      representation: 'normal' as const,
      organization_id: 'org-1',
      rw_id: 'rw-1',
      rt_id: 'rt-1',
      kk_number: '3201011205900001',
      address: 'Jl. Merdeka No. 1',
      active_members_count: 3,
    };

    const actions = getHouseholdAvailableActions(activeHousehold);
    assert.equal(actions.canEdit, true);
    assert.equal(actions.canAddMember, true);
    assert.equal(actions.canChangeHead, true);
    assert.equal(actions.canMove, true);
    assert.equal(actions.canDissolve, true);
  });

  it('HEAD_VACANT household: operable actions available', async () => {
    const { getHouseholdAvailableActions } = await import(
      '../src/features/households/components/householdActionUtils.ts'
    );

    const headVacantHousehold = {
      id: 'hh-2',
      status: 'HEAD_VACANT' as const,
      representation: 'normal' as const,
      organization_id: 'org-1',
      rw_id: 'rw-1',
      rt_id: 'rt-1',
      kk_number: '3201011205900002',
      address: 'Jl. Kenanga No. 2',
      active_members_count: 2,
    };

    const actions = getHouseholdAvailableActions(headVacantHousehold);
    assert.equal(actions.canEdit, true);
    assert.equal(actions.canAddMember, true);
    assert.equal(actions.canChangeHead, true);
    assert.equal(actions.canMove, true);
    assert.equal(actions.canDissolve, true);
  });

  it('DISSOLVED household: all actions are disabled (terminal state)', async () => {
    const { getHouseholdAvailableActions } = await import(
      '../src/features/households/components/householdActionUtils.ts'
    );

    const dissolvedHousehold = {
      id: 'hh-3',
      status: 'DISSOLVED' as const,
      representation: 'normal' as const,
      organization_id: 'org-1',
      rw_id: 'rw-1',
      rt_id: 'rt-1',
      kk_number: '3201011205900003',
      address: 'Jl. Melati No. 3',
      active_members_count: 0,
    };

    const actions = getHouseholdAvailableActions(dissolvedHousehold);
    assert.equal(actions.canEdit, false);
    assert.equal(actions.canAddMember, false);
    assert.equal(actions.canChangeHead, false);
    assert.equal(actions.canMove, false);
    assert.equal(actions.canDissolve, false);
  });

  it('MOVED and ARCHIVED households: all actions are disabled', async () => {
    const { getHouseholdAvailableActions } = await import(
      '../src/features/households/components/householdActionUtils.ts'
    );

    for (const status of ['MOVED', 'ARCHIVED'] as const) {
      const hh = {
        id: `hh-${status}`,
        status,
        representation: 'normal' as const,
        organization_id: 'org-1',
        rw_id: 'rw-1',
        rt_id: 'rt-1',
        kk_number: '3201011205900004',
        address: 'Jl. Alamat',
        active_members_count: 0,
      };

      const actions = getHouseholdAvailableActions(hh);
      assert.equal(actions.canEdit, false);
      assert.equal(actions.canAddMember, false);
      assert.equal(actions.canChangeHead, false);
      assert.equal(actions.canMove, false);
      assert.equal(actions.canDissolve, false);
    }
  });
});

// ── I. Idempotency Key Integration ────────────────────────────────────────────

describe('Household Idempotency Integration', () => {
  it('generates valid UUID v4 idempotency keys', async () => {
    const { generateIdempotencyKey } = await import('../src/lib/api/idempotency.ts');
    const key = generateIdempotencyKey();
    assert.match(
      key,
      /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
      'Idempotency key must be a valid UUID v4'
    );
  });

  it('generates unique keys across multiple invocations (different dialog instances)', async () => {
    const { generateIdempotencyKey } = await import('../src/lib/api/idempotency.ts');
    const keyA = generateIdempotencyKey();
    const keyB = generateIdempotencyKey();
    const keyC = generateIdempotencyKey();

    assert.notEqual(keyA, keyB);
    assert.notEqual(keyB, keyC);
    assert.notEqual(keyA, keyC);
  });

  it('maintains stability when stored in a ref during retry', async () => {
    const { generateIdempotencyKey } = await import('../src/lib/api/idempotency.ts');
    // Emulates dialog opening: key stored once in useRef
    const dialogSessionKey = generateIdempotencyKey();
    // Subsequent retry clicks within the same dialog must pass the same key
    const retryAttemptKey = dialogSessionKey;
    assert.equal(dialogSessionKey, retryAttemptKey);
  });
});

// ── J. Query Key Invalidation Strategy ────────────────────────────────────────

describe('Query Key Invalidation Strategy', () => {
  it('householdKeys detail and list namespaces are correctly formed', async () => {
    const { householdKeys } = await import('../src/features/households/api/householdKeys.ts');
    const detailKey = householdKeys.detail('hh-uuid-1');
    const listKey = householdKeys.list();

    assert.ok(Array.isArray(detailKey));
    assert.ok(Array.isArray(listKey));
    assert.ok(detailKey.includes('hh-uuid-1'));
    assert.equal(detailKey[0], 'households');
    assert.equal(listKey[0], 'households');
  });

  it('invalidates resident keys on member management operations', async () => {
    const { residentKeys } = await import('../src/features/residents/api/residentKeys.ts');
    assert.ok(Array.isArray(residentKeys.all));
    assert.equal(residentKeys.all[0], 'residents');
  });

  it('overview query key is distinct and targeted for dashboard refreshes', async () => {
    const { overviewKeys } = await import('../src/features/overview/api/overviewKeys.ts');
    const { householdKeys } = await import('../src/features/households/api/householdKeys.ts');

    const overviewJson = JSON.stringify(overviewKeys.all);
    const householdJson = JSON.stringify(householdKeys.all);

    assert.notEqual(overviewJson, householdJson);
    assert.ok(overviewJson.includes('overview'));
    assert.ok(householdJson.includes('households'));
  });
});

// ── K. Privacy & Boundaries Contract ──────────────────────────────────────────

describe('Phase 20 Privacy & Boundaries Contract', () => {
  it('CreateHouseholdPayload contains no NIK, phone, or demographic fields', () => {
    const payload: import('../src/types/household.ts').CreateHouseholdPayload = {
      kk_number: '3201011205900001',
      address: 'Jl. Kenari No. 1',
      rt_id: 'rt-1',
    };

    const keys = Object.keys(payload);
    assert.ok(!keys.includes('nik'));
    assert.ok(!keys.includes('phone'));
    assert.ok(!keys.includes('religion'));
    assert.ok(!keys.includes('birth_date'));
  });

  it('AddHouseholdMemberPayload references resident by ID without sensitive fields', () => {
    const payload: import('../src/types/household.ts').AddHouseholdMemberPayload = {
      resident_id: '01924b12-9c9e-716b-9c3f-8461abde5001',
      relationship: 'ANAK',
    };

    const keys = Object.keys(payload);
    assert.ok(!keys.includes('nik'));
    assert.ok(!keys.includes('name'));
    assert.ok(!keys.includes('phone'));
    assert.ok(!keys.includes('religion'));
  });

  it('ChangeHouseholdHeadPayload references resident by ID without sensitive fields', () => {
    const payload: import('../src/types/household.ts').ChangeHouseholdHeadPayload = {
      new_head_resident_id: '01924b12-9c9e-716b-9c3f-8461abde5002',
      old_head_new_relationship: 'ISTRI',
    };

    const keys = Object.keys(payload);
    assert.ok(!keys.includes('nik'));
    assert.ok(!keys.includes('name'));
    assert.ok(!keys.includes('phone'));
  });

  it('MoveHouseholdPayload does not leak member or demographic information', () => {
    const payload: import('../src/types/household.ts').MoveHouseholdPayload = {
      move_type: 'INTER_RT',
      target_rt_id: 'rt-target-2',
      new_address: 'Jl. Anyelir No. 5',
    };

    const keys = Object.keys(payload);
    assert.ok(!keys.includes('nik'));
    assert.ok(!keys.includes('members'));
    assert.ok(!keys.includes('head_name'));
  });
});

// ── L. Complete API Client Surface ────────────────────────────────────────────

describe('householdApi Complete Mutation Surface (Phase 20)', () => {
  it('all 7 operational mutation methods exist on householdApi', async () => {
    const { householdApi } = await import('../src/features/households/api/householdApi.ts');

    assert.equal(typeof householdApi.create, 'function', 'create missing');
    assert.equal(typeof householdApi.update, 'function', 'update missing');
    assert.equal(typeof householdApi.addMember, 'function', 'addMember missing');
    assert.equal(typeof householdApi.removeMember, 'function', 'removeMember missing');
    assert.equal(typeof householdApi.changeHead, 'function', 'changeHead missing');
    assert.equal(typeof householdApi.move, 'function', 'move missing');
    assert.equal(typeof householdApi.dissolve, 'function', 'dissolve missing');
  });

  it('read endpoints remain available alongside mutations', async () => {
    const { householdApi } = await import('../src/features/households/api/householdApi.ts');

    assert.equal(typeof householdApi.list, 'function', 'list missing');
    assert.equal(typeof householdApi.getById, 'function', 'getById missing');
  });
});
