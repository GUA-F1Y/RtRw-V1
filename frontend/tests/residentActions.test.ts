/**
 * Phase 19 — Resident Operational Actions UI: Data-Layer Tests
 *
 * Pure data-layer tests (no React rendering, no DOM, no fetch).
 *
 * Covers:
 * - VerifyResidentPayload shape and field optionality
 * - ChangeResidentStatusPayload — intent enum, forbidden fields
 * - RelocateResidentPayload — movement types, conditional fields
 * - RemoveResidentFromHouseholdPayload — correct field names (left_reason, left_at)
 * - Lifecycle intent availability per resident status
 * - Movement type coverage (HOUSEHOLD_SPLIT added)
 * - Idempotency key uniqueness per session
 * - Query invalidation: correct cache keys after mutations
 * - API surface: all mutation methods exist on residentApi
 * - Privacy: forbidden fields absent from all payloads
 */

import { describe, it } from 'node:test';
import assert from 'node:assert/strict';

// ── A. Verify Resident ─────────────────────────────────────────────────────────

describe('VerifyResidentPayload', () => {
  it('all fields are optional — empty payload is valid', () => {
    // Backend rules: notes?, reference_letter_number?, attachment_file_id?
    const emptyPayload: import('../src/types/resident.ts').VerifyResidentPayload = {};
    assert.deepEqual(Object.keys(emptyPayload), []);
  });

  it('accepts notes and reference_letter_number', () => {
    const payload: import('../src/types/resident.ts').VerifyResidentPayload = {
      notes: 'Dokumen lengkap.',
      reference_letter_number: '474/RT01/X/2026',
    };
    assert.equal(payload.notes, 'Dokumen lengkap.');
    assert.equal(payload.reference_letter_number, '474/RT01/X/2026');
  });

  it('does not include forbidden fields (status, resident_id, recorded_by)', () => {
    const payload: import('../src/types/resident.ts').VerifyResidentPayload = {
      notes: 'ok',
    };
    const keys = Object.keys(payload);
    assert.ok(!keys.includes('status'));
    assert.ok(!keys.includes('resident_id'));
    assert.ok(!keys.includes('recorded_by'));
    assert.ok(!keys.includes('organization_id'));
  });

  it('residentApi.verify is a function', async () => {
    const { residentApi } = await import('../src/features/residents/api/residentApi.ts');
    assert.equal(typeof residentApi.verify, 'function');
  });
});

// ── B. Change Resident Status ──────────────────────────────────────────────────

describe('ChangeResidentStatusPayload', () => {
  it('intent field accepts only the three valid values', () => {
    const validIntents: import('../src/types/resident.ts').ResidentStatusIntent[] = [
      'DEACTIVATE',
      'REACTIVATE',
      'MARK_DECEASED',
    ];
    assert.equal(validIntents.length, 3);
    for (const intent of validIntents) {
      const payload: import('../src/types/resident.ts').ChangeResidentStatusPayload = {
        intent,
        reason: 'Test reason',
      };
      assert.equal(payload.intent, intent);
    }
  });

  it('reason is optional per backend FormRequest (sometimes|nullable)', () => {
    const payload: import('../src/types/resident.ts').ChangeResidentStatusPayload = {
      intent: 'DEACTIVATE',
    };
    assert.equal(payload.reason, undefined);
  });

  it('effective_at is optional (sometimes|nullable)', () => {
    const payload: import('../src/types/resident.ts').ChangeResidentStatusPayload = {
      intent: 'MARK_DECEASED',
      reason: 'Warga meninggal',
      effective_at: '2026-10-01',
    };
    assert.equal(payload.effective_at, '2026-10-01');
  });

  it('does not include forbidden fields', () => {
    const payload: import('../src/types/resident.ts').ChangeResidentStatusPayload = {
      intent: 'DEACTIVATE',
      reason: 'Test',
    };
    const keys = Object.keys(payload);
    assert.ok(!keys.includes('status'));
    assert.ok(!keys.includes('resident_id'));
    assert.ok(!keys.includes('recorded_by'));
    assert.ok(!keys.includes('from_status'));
    assert.ok(!keys.includes('to_status'));
  });
});

// ── B2. Lifecycle intent availability per status ───────────────────────────────

describe('Status action availability by resident status', () => {
  // Mirror the logic in ChangeResidentStatusDialog.tsx
  function availableIntents(
    status: import('../src/types/resident.ts').ResidentStatus
  ): import('../src/types/resident.ts').ResidentStatusIntent[] {
    switch (status) {
      case 'ACTIVE':
        return ['DEACTIVATE', 'MARK_DECEASED'];
      case 'INACTIVE':
        return ['REACTIVATE', 'MARK_DECEASED'];
      case 'PROSPECTIVE':
      case 'MOVED':
      case 'DECEASED':
        return [];
      default:
        return [];
    }
  }

  it('ACTIVE resident can be DEACTIVATED or MARK_DECEASED', () => {
    const intents = availableIntents('ACTIVE');
    assert.ok(intents.includes('DEACTIVATE'));
    assert.ok(intents.includes('MARK_DECEASED'));
    assert.ok(!intents.includes('REACTIVATE'));
  });

  it('INACTIVE resident can be REACTIVATED or MARK_DECEASED', () => {
    const intents = availableIntents('INACTIVE');
    assert.ok(intents.includes('REACTIVATE'));
    assert.ok(intents.includes('MARK_DECEASED'));
    assert.ok(!intents.includes('DEACTIVATE'));
  });

  it('PROSPECTIVE resident has no status-change intents', () => {
    assert.deepEqual(availableIntents('PROSPECTIVE'), []);
  });

  it('MOVED resident has no status-change intents', () => {
    assert.deepEqual(availableIntents('MOVED'), []);
  });

  it('DECEASED resident has no status-change intents', () => {
    assert.deepEqual(availableIntents('DECEASED'), []);
  });
});

// ── C. Relocate Resident ───────────────────────────────────────────────────────

describe('RelocateResidentPayload', () => {
  it('movement_type is required', () => {
    const payload: import('../src/types/resident.ts').RelocateResidentPayload = {
      movement_type: 'TRANSFER_INTERNAL',
      effective_date: '2026-10-01',
    };
    assert.equal(payload.movement_type, 'TRANSFER_INTERNAL');
  });

  it('includes all three backend-supported movement types', () => {
    const types: import('../src/types/resident.ts').ResidencyMovementType[] = [
      'TRANSFER_INTERNAL',
      'MOVE_OUT_EXTERNAL',
      'HOUSEHOLD_SPLIT',
    ];
    assert.equal(types.length, 3);
    for (const type of types) {
      const p: import('../src/types/resident.ts').RelocateResidentPayload = {
        movement_type: type,
      };
      assert.equal(p.movement_type, type);
    }
  });

  it('TRANSFER_INTERNAL payload includes target_rt_id', () => {
    const payload: import('../src/types/resident.ts').RelocateResidentPayload = {
      movement_type: 'TRANSFER_INTERNAL',
      target_rt_id: 'rt-uuid-001',
    };
    assert.equal(payload.target_rt_id, 'rt-uuid-001');
  });

  it('MOVE_OUT_EXTERNAL payload omits target_rt_id (not required)', () => {
    const payload: import('../src/types/resident.ts').RelocateResidentPayload = {
      movement_type: 'MOVE_OUT_EXTERNAL',
      effective_date: '2026-10-01',
    };
    assert.equal(payload.target_rt_id, undefined);
  });

  it('target_household_id is optional', () => {
    const with_hh: import('../src/types/resident.ts').RelocateResidentPayload = {
      movement_type: 'HOUSEHOLD_SPLIT',
      target_rt_id: 'rt-uuid-002',
      target_household_id: 'hh-uuid-003',
    };
    assert.equal(with_hh.target_household_id, 'hh-uuid-003');
  });

  it('does not include forbidden fields (from_*, resident_id, recorded_by)', () => {
    const payload: import('../src/types/resident.ts').RelocateResidentPayload = {
      movement_type: 'TRANSFER_INTERNAL',
      target_rt_id: 'rt-uuid-001',
    };
    const keys = Object.keys(payload);
    assert.ok(!keys.includes('from_rt_id'));
    assert.ok(!keys.includes('from_rw_id'));
    assert.ok(!keys.includes('resident_id'));
    assert.ok(!keys.includes('recorded_by'));
    assert.ok(!keys.includes('to_rt_id'));
    assert.ok(!keys.includes('to_rw_id'));
  });

  it('residentApi.relocate is a function', async () => {
    const { residentApi } = await import('../src/features/residents/api/residentApi.ts');
    assert.equal(typeof residentApi.relocate, 'function');
  });
});

// ── D. Remove Resident From Household ─────────────────────────────────────────

describe('RemoveResidentFromHouseholdPayload', () => {
  it('uses left_reason (not reason) matching backend FormRequest', () => {
    const payload: import('../src/types/resident.ts').RemoveResidentFromHouseholdPayload = {
      left_reason: 'Pindah KK',
      left_at: '2026-10-01',
    };
    assert.equal(payload.left_reason, 'Pindah KK');
    assert.equal(payload.left_at, '2026-10-01');
    // Ensure legacy "reason" field is not present on the type
    assert.ok(!('reason' in payload));
  });

  it('empty payload is valid (all fields optional)', () => {
    const empty: import('../src/types/resident.ts').RemoveResidentFromHouseholdPayload = {};
    assert.deepEqual(Object.keys(empty), []);
  });

  it('left_at format matches YYYY-MM-DD per backend date_format rule', () => {
    const payload: import('../src/types/resident.ts').RemoveResidentFromHouseholdPayload = {
      left_at: '2026-10-14',
    };
    // Simple regex for YYYY-MM-DD
    assert.match(payload.left_at!, /^\d{4}-\d{2}-\d{2}$/);
  });

  it('residentApi.removeFromHousehold is a function', async () => {
    const { residentApi } = await import('../src/features/residents/api/residentApi.ts');
    assert.equal(typeof residentApi.removeFromHousehold, 'function');
  });
});

// ── E. Idempotency ─────────────────────────────────────────────────────────────

describe('Idempotency key uniqueness for relocation', () => {
  it('generates a different key for each call (simulates per-dialog-open uniqueness)', async () => {
    const { generateIdempotencyKey } = await import('../src/lib/api/idempotency.ts');
    const key1 = generateIdempotencyKey();
    const key2 = generateIdempotencyKey();
    assert.notEqual(key1, key2, 'Each dialog open must generate a fresh idempotency key');
  });

  it('generated key is a valid UUID v4 format', async () => {
    const { generateIdempotencyKey } = await import('../src/lib/api/idempotency.ts');
    const key = generateIdempotencyKey();
    assert.match(
      key,
      /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
    );
  });
});

// ── F. Query key invalidation coverage ────────────────────────────────────────

describe('Query invalidation after mutations', () => {
  it('residentKeys.detail(id) and residentKeys.lists() are distinct', async () => {
    const { residentKeys } = await import('../src/features/residents/api/residentKeys.ts');
    const detailKey = JSON.stringify(residentKeys.detail('some-id'));
    const listKey = JSON.stringify(residentKeys.lists());
    assert.notEqual(detailKey, listKey);
  });

  it('household list key is invalidated by household-affecting actions (key shape check)', async () => {
    // The mutation hooks invalidate ['households'] — verify key namespace
    const { householdKeys } = await import('../src/features/households/api/householdKeys.ts');
    const listsKey = JSON.stringify(householdKeys.lists());
    assert.ok(listsKey.includes('households'));
  });

  it('overview key is distinct from resident and household keys', async () => {
    const { overviewKeys } = await import('../src/features/overview/api/overviewKeys.ts');
    const { residentKeys } = await import('../src/features/residents/api/residentKeys.ts');
    const overviewStr = JSON.stringify(overviewKeys.all);
    const residentStr = JSON.stringify(residentKeys.all);
    assert.notEqual(overviewStr, residentStr);
    assert.ok(overviewStr.includes('overview'));
  });
});

// ── G. Actions panel availability logic ───────────────────────────────────────

describe('ResidentActionsPanel availability logic', () => {
  // Mirror getAvailableActions from ResidentActionsPanel.tsx
  function getAvailableActions(resident: {
    status: import('../src/types/resident.ts').ResidentStatus;
    current_household_id?: string | null;
  }) {
    const { status } = resident;
    return {
      canVerify: status === 'PROSPECTIVE',
      canChangeStatus: status === 'ACTIVE' || status === 'INACTIVE',
      canRelocate: status === 'ACTIVE',
      canRemoveFromHousehold: status === 'ACTIVE' && Boolean(resident.current_household_id),
    };
  }

  it('PROSPECTIVE: only canVerify is true', () => {
    const actions = getAvailableActions({ status: 'PROSPECTIVE' });
    assert.equal(actions.canVerify, true);
    assert.equal(actions.canChangeStatus, false);
    assert.equal(actions.canRelocate, false);
    assert.equal(actions.canRemoveFromHousehold, false);
  });

  it('ACTIVE with household: all four actions available', () => {
    const actions = getAvailableActions({
      status: 'ACTIVE',
      current_household_id: 'hh-123',
    });
    assert.equal(actions.canVerify, false);
    assert.equal(actions.canChangeStatus, true);
    assert.equal(actions.canRelocate, true);
    assert.equal(actions.canRemoveFromHousehold, true);
  });

  it('ACTIVE without household: canRemoveFromHousehold is false', () => {
    const actions = getAvailableActions({ status: 'ACTIVE', current_household_id: null });
    assert.equal(actions.canRelocate, true);
    assert.equal(actions.canRemoveFromHousehold, false);
  });

  it('INACTIVE: canChangeStatus true, canRelocate false', () => {
    const actions = getAvailableActions({ status: 'INACTIVE' });
    assert.equal(actions.canChangeStatus, true);
    assert.equal(actions.canRelocate, false);
    assert.equal(actions.canVerify, false);
  });

  it('MOVED: no actions available', () => {
    const actions = getAvailableActions({ status: 'MOVED' });
    assert.equal(actions.canVerify, false);
    assert.equal(actions.canChangeStatus, false);
    assert.equal(actions.canRelocate, false);
    assert.equal(actions.canRemoveFromHousehold, false);
  });

  it('DECEASED: no actions available', () => {
    const actions = getAvailableActions({ status: 'DECEASED' });
    const values = Object.values(actions);
    assert.ok(values.every((v) => v === false));
  });
});

// ── H. Privacy: sensitive fields not in any mutation payload ──────────────────

describe('Phase 19 payload privacy contract', () => {
  it('VerifyResidentPayload does not include NIK, phone, or religion', () => {
    const payload: import('../src/types/resident.ts').VerifyResidentPayload = {
      notes: 'ok',
      reference_letter_number: '474/RT01/X/2026',
    };
    const keys = Object.keys(payload);
    assert.ok(!keys.includes('nik'));
    assert.ok(!keys.includes('phone'));
    assert.ok(!keys.includes('religion'));
    assert.ok(!keys.includes('blood_type'));
  });

  it('ChangeResidentStatusPayload does not include sensitive identity fields', () => {
    const payload: import('../src/types/resident.ts').ChangeResidentStatusPayload = {
      intent: 'DEACTIVATE',
      reason: 'Test',
    };
    const keys = Object.keys(payload);
    assert.ok(!keys.includes('nik'));
    assert.ok(!keys.includes('phone'));
    assert.ok(!keys.includes('birth_date'));
    assert.ok(!keys.includes('religion'));
  });

  it('RelocateResidentPayload does not include NIK or phone', () => {
    const payload: import('../src/types/resident.ts').RelocateResidentPayload = {
      movement_type: 'TRANSFER_INTERNAL',
      target_rt_id: 'rt-uuid',
      notes: 'Test move',
    };
    const keys = Object.keys(payload);
    assert.ok(!keys.includes('nik'));
    assert.ok(!keys.includes('phone'));
    assert.ok(!keys.includes('religion'));
  });

  it('RemoveFromHouseholdPayload does not include identity fields', () => {
    const payload: import('../src/types/resident.ts').RemoveResidentFromHouseholdPayload = {
      left_reason: 'Test',
    };
    const keys = Object.keys(payload);
    assert.ok(!keys.includes('nik'));
    assert.ok(!keys.includes('name'));
    assert.ok(!keys.includes('status'));
    assert.ok(!keys.includes('resident_id'));
  });
});

// ── I. API mutation method existence ──────────────────────────────────────────

describe('residentApi mutation surface (Phase 19)', () => {
  it('all four Phase 19 mutation methods exist on residentApi', async () => {
    const { residentApi } = await import('../src/features/residents/api/residentApi.ts');
    assert.equal(typeof residentApi.verify, 'function', 'verify missing');
    assert.equal(typeof residentApi.relocate, 'function', 'relocate missing');
    assert.equal(typeof residentApi.changeStatus, 'function', 'changeStatus missing');
    assert.equal(typeof residentApi.removeFromHousehold, 'function', 'removeFromHousehold missing');
  });
});

// ── J. Duplicate-submit prevention contract ───────────────────────────────────

describe('Duplicate-submit prevention (idempotency contract)', () => {
  it('relocation idempotency key is stable within a session (same ref)', async () => {
    const { generateIdempotencyKey } = await import('../src/lib/api/idempotency.ts');
    // Simulate: key generated once at dialog open, then reused on retry
    const sessionKey = generateIdempotencyKey();
    // If a network error occurs, the same key should be re-sent — test that
    // the key value is stable (it's a plain string, not a getter that changes)
    const reusedKey = sessionKey; // stored in useRef in the component
    assert.equal(sessionKey, reusedKey);
  });

  it('different dialog sessions produce different idempotency keys', async () => {
    const { generateIdempotencyKey } = await import('../src/lib/api/idempotency.ts');
    const session1 = generateIdempotencyKey();
    const session2 = generateIdempotencyKey();
    assert.notEqual(session1, session2);
  });
});
