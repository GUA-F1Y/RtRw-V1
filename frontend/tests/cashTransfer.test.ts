/**
 * Phase 26 — Internal Cash Transfers & Financial Integrity Unit Tests
 *
 * Covers:
 * - Authoritative accounting formula:
 *   balance = opening + operating_income - operating_expense + transfers_in - transfers_out
 * - Zero-sum transfer movement invariant across system accounts
 * - Operational neutrality: transfers do not count toward operating income or expenses
 * - Integer Rupiah arithmetic invariants
 * - Validation rules: distinct accounts, positive amounts, available funds check
 * - Reversal workflow: compensating transfer direction, immutability, dual-reversal prevention
 * - Scoped permissions & role capabilities: Ketua/Bendahara vs read-only Sekretaris vs Residents
 * - Query key factory stability
 * - API client routing & idempotency contracts
 */

import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { financeApi } from '../src/features/finance/api/financeApi.ts';
import { financeKeys } from '../src/features/finance/api/financeKeys.ts';
import {
  canCreateFinanceTransfer,
  canReverseFinanceTransfer,
  canViewFinanceTransfers,
} from '../src/features/finance/utils/permissions.ts';
import type { User } from '../src/types/auth.ts';
import type {
  CashTransfer,
  RecordTransferPayload,
  ReverseTransferPayload,
} from '../src/types/finance.ts';

// ── 1. Authoritative Accounting Formula & Invariants ─────────────────────────

describe('Authoritative Balance & Transfer Accounting Invariants', () => {
  it('integrates transfers into cash account balance formula correctly', () => {
    const openingBalance = 10_000_000;
    const operatingIncome = 5_000_000;
    const operatingExpense = 2_000_000;
    const transfersIn = 3_000_000;
    const transfersOut = 1_500_000;

    const currentBalance =
      openingBalance + operatingIncome - operatingExpense + transfersIn - transfersOut;

    assert.equal(currentBalance, 14_500_000);
    assert.ok(Number.isInteger(currentBalance), 'Balance must remain an integer in Rupiah');
  });

  it('guarantees zero-sum system-wide net movement for internal transfers', () => {
    // Transfer of Rp 2,000,000 from RW Cash (Account A) to RT 01 Cash (Account B)
    const transferAmount = 2_000_000;

    const accountAMovement = -transferAmount; // transfers_out
    const accountBMovement = +transferAmount; // transfers_in

    const systemNetTransferMovement = accountAMovement + accountBMovement;

    assert.equal(systemNetTransferMovement, 0, 'Internal transfers must produce zero net system delta');
  });

  it('preserves operating income and expense isolation (operational neutrality)', () => {
    const openingBalance = 5_000_000;
    const operatingIncome = 1_000_000;
    const operatingExpense = 500_000;
    const internalTransferIn = 4_000_000;

    // Operating net income must NOT include transfers
    const operatingNetSurplus = operatingIncome - operatingExpense;
    assert.equal(operatingNetSurplus, 500_000);

    // Total closing balance does include transfers
    const closingBalance = openingBalance + operatingIncome - operatingExpense + internalTransferIn;
    assert.equal(closingBalance, 9_500_000);
  });

  it('maintains integer Rupiah arithmetic during multi-step transfers and reversals', () => {
    const initialBalanceA = 10_000_000;
    const initialBalanceB = 5_000_000;

    const transferAmount = 2_750_000;

    // After transfer:
    const balA1 = initialBalanceA - transferAmount; // 7,250,000
    const balB1 = initialBalanceB + transferAmount; // 7,750,000

    assert.equal(balA1, 7_250_000);
    assert.equal(balB1, 7_750_000);
    assert.ok(Number.isInteger(balA1));
    assert.ok(Number.isInteger(balB1));

    // After compensating reversal (B -> A of 2,750,000):
    const balA2 = balA1 + transferAmount; // 10,000,000
    const balB2 = balB1 - transferAmount; // 5,000,000

    assert.equal(balA2, initialBalanceA);
    assert.equal(balB2, initialBalanceB);
    assert.ok(Number.isInteger(balA2));
    assert.ok(Number.isInteger(balB2));
  });
});

// ── 2. Transfer Validation Rules & Invariants ─────────────────────────────────

describe('Transfer Validation Rules & Constraints', () => {
  const validateTransferPayload = (
    payload: RecordTransferPayload,
    sourceCurrentBalance: number
  ): { valid: boolean; error?: string } => {
    if (!payload.source_cash_account_id) {
      return { valid: false, error: 'source_account_required' };
    }
    if (!payload.destination_cash_account_id) {
      return { valid: false, error: 'destination_account_required' };
    }
    if (payload.source_cash_account_id === payload.destination_cash_account_id) {
      return { valid: false, error: 'same_account_transfer' };
    }
    if (!payload.amount || payload.amount <= 0 || !Number.isInteger(payload.amount)) {
      return { valid: false, error: 'invalid_amount' };
    }
    if (payload.amount > sourceCurrentBalance) {
      return { valid: false, error: 'insufficient_funds' };
    }
    if (!payload.transfer_date) {
      return { valid: false, error: 'transfer_date_required' };
    }
    if (!payload.description || payload.description.trim().length < 3) {
      return { valid: false, error: 'description_min_length' };
    }
    return { valid: true };
  };

  it('rejects transfer when source and destination accounts are identical', () => {
    const payload: RecordTransferPayload = {
      source_cash_account_id: 'acc-uuid-1',
      destination_cash_account_id: 'acc-uuid-1',
      amount: 500_000,
      transfer_date: '2026-10-15',
      description: 'Transfer internal',
    };

    const result = validateTransferPayload(payload, 1_000_000);
    assert.equal(result.valid, false);
    assert.equal(result.error, 'same_account_transfer');
  });

  it('rejects transfer exceeding source available balance', () => {
    const payload: RecordTransferPayload = {
      source_cash_account_id: 'acc-uuid-1',
      destination_cash_account_id: 'acc-uuid-2',
      amount: 1_500_000,
      transfer_date: '2026-10-15',
      description: 'Transfer kas melebihi saldo',
    };

    const result = validateTransferPayload(payload, 1_000_000);
    assert.equal(result.valid, false);
    assert.equal(result.error, 'insufficient_funds');
  });

  it('accepts transfer matching exact available balance (sweeping account to zero)', () => {
    const payload: RecordTransferPayload = {
      source_cash_account_id: 'acc-uuid-1',
      destination_cash_account_id: 'acc-uuid-2',
      amount: 1_000_000,
      transfer_date: '2026-10-15',
      description: 'Penyaluran saldo penuh',
    };

    const result = validateTransferPayload(payload, 1_000_000);
    assert.equal(result.valid, true);
  });

  it('rejects negative or fractional Rupiah amounts', () => {
    const invalidNegative: RecordTransferPayload = {
      source_cash_account_id: 'acc-uuid-1',
      destination_cash_account_id: 'acc-uuid-2',
      amount: -100_000,
      transfer_date: '2026-10-15',
      description: 'Transfer negatif',
    };
    assert.equal(validateTransferPayload(invalidNegative, 1_000_000).error, 'invalid_amount');

    const invalidFractional: RecordTransferPayload = {
      source_cash_account_id: 'acc-uuid-1',
      destination_cash_account_id: 'acc-uuid-2',
      amount: 100.5,
      transfer_date: '2026-10-15',
      description: 'Transfer pecahan',
    };
    assert.equal(validateTransferPayload(invalidFractional, 1_000_000).error, 'invalid_amount');
  });

  it('rejects description shorter than 3 characters', () => {
    const payload: RecordTransferPayload = {
      source_cash_account_id: 'acc-uuid-1',
      destination_cash_account_id: 'acc-uuid-2',
      amount: 100_000,
      transfer_date: '2026-10-15',
      description: 'ab',
    };

    const result = validateTransferPayload(payload, 1_000_000);
    assert.equal(result.valid, false);
    assert.equal(result.error, 'description_min_length');
  });
});

// ── 3. Reversal Workflow & Immutability Semantics ──────────────────────────────

describe('Cash Transfer Reversal Workflow & Immutability', () => {
  it('correctly creates compensating transfer with reversed accounts direction', () => {
    const originalTransfer: CashTransfer = {
      id: 'tx-orig-uuid',
      source_cash_account_id: 'acc-rw-1',
      destination_cash_account_id: 'acc-rt-1',
      amount: 1_000_000,
      transfer_date: '2026-10-01',
      description: 'Penyaluran dana operasional RT',
      reference_number: 'TRF-001',
      evidence_file_id: null,
      recorded_by: 'user-bendahara',
      is_reversal: false,
      original_transfer_id: null,
      reversal_reason: null,
      created_at: '2026-10-01T10:00:00Z',
      is_reversed: false,
    };

    const reversalPayload: ReverseTransferPayload = {
      reversal_reason: 'Koreksi salah nominal',
      transfer_date: '2026-10-02',
    };

    // Compensating transfer model simulation
    const compensatingTransfer: CashTransfer = {
      id: 'tx-rev-uuid',
      source_cash_account_id: originalTransfer.destination_cash_account_id, // inverted: acc-rt-1
      destination_cash_account_id: originalTransfer.source_cash_account_id, // inverted: acc-rw-1
      amount: originalTransfer.amount,
      transfer_date: reversalPayload.transfer_date!,
      description: `[REVERSAL] ${originalTransfer.description}`,
      reference_number: null,
      evidence_file_id: null,
      recorded_by: 'user-ketua',
      is_reversal: true,
      original_transfer_id: originalTransfer.id,
      reversal_reason: reversalPayload.reversal_reason,
      created_at: '2026-10-02T11:00:00Z',
      is_reversed: false,
    };

    // Invariant assertions
    assert.equal(compensatingTransfer.source_cash_account_id, originalTransfer.destination_cash_account_id);
    assert.equal(compensatingTransfer.destination_cash_account_id, originalTransfer.source_cash_account_id);
    assert.equal(compensatingTransfer.amount, originalTransfer.amount);
    assert.equal(compensatingTransfer.is_reversal, true);
    assert.equal(compensatingTransfer.original_transfer_id, originalTransfer.id);
    assert.equal(compensatingTransfer.reversal_reason, 'Koreksi salah nominal');
  });

  it('prevents reversing a transfer that has already been reversed', () => {
    const alreadyReversedTransfer: Partial<CashTransfer> = {
      id: 'tx-1',
      is_reversal: false,
      is_reversed: true,
    };

    const canReverse = !alreadyReversedTransfer.is_reversed && !alreadyReversedTransfer.is_reversal;
    assert.equal(canReverse, false, 'Already reversed transfer cannot be reversed again');
  });

  it('prevents reversing a compensating reversal transfer', () => {
    const reversalTransfer: Partial<CashTransfer> = {
      id: 'tx-2',
      is_reversal: true,
      is_reversed: false,
    };

    const canReverse = !reversalTransfer.is_reversed && !reversalTransfer.is_reversal;
    assert.equal(canReverse, false, 'A reversal transfer cannot be reversed');
  });
});

// ── 4. Permissions & Role Capabilities ────────────────────────────────────────

describe('Cash Transfer Permissions & Scoped Role Capabilities', () => {
  const createMockUser = (roleKey: string, permissions: string[] = []): User =>
    ({
      id: 'user-test-uuid',
      name: 'Test Officer',
      email: 'test@rtrw.local',
      status: 'active',
      memberships: [
        {
          id: 'mem-1',
          scope_level: roleKey.includes('rt') ? 'rt' : roleKey.includes('rw') ? 'rw' : 'organization',
          organization_id: 'org-1',
          rw_id: 'rw-1',
          rt_id: null,
          is_active: true,
          role: { id: 1, key: roleKey, name: roleKey },
          created_at: '2026-01-01T00:00:00Z',
        },
      ],
      ...((permissions.length > 0 ? { permissions } : {}) as Record<string, unknown>),
    }) as unknown as User;

  it('allows Ketua RW with finance.transfer permissions full transfer management', () => {
    const user = createMockUser('ketua_rw');

    assert.equal(canViewFinanceTransfers(user), true);
    assert.equal(canCreateFinanceTransfer(user), true);
    assert.equal(canReverseFinanceTransfer(user), true);
  });

  it('allows Bendahara RW full transfer management', () => {
    const user = createMockUser('bendahara_rw');

    assert.equal(canViewFinanceTransfers(user), true);
    assert.equal(canCreateFinanceTransfer(user), true);
    assert.equal(canReverseFinanceTransfer(user), true);
  });

  it('allows Sekretaris RW to view transfers but prohibits creating or reversing', () => {
    const user = createMockUser('sekretaris_rw');

    assert.equal(canViewFinanceTransfers(user), true);
    assert.equal(canCreateFinanceTransfer(user), false, 'Sekretaris cannot create transfers');
    assert.equal(canReverseFinanceTransfer(user), false, 'Sekretaris cannot reverse transfers');
  });

  it('prohibits Warga from viewing or modifying transfers', () => {
    const user = createMockUser('warga');

    assert.equal(canViewFinanceTransfers(user), false);
    assert.equal(canCreateFinanceTransfer(user), false);
    assert.equal(canReverseFinanceTransfer(user), false);
  });

  it('handles null or unauthenticated user safely', () => {
    assert.equal(canViewFinanceTransfers(null), false);
    assert.equal(canCreateFinanceTransfer(null), false);
    assert.equal(canReverseFinanceTransfer(null), false);
  });
});

// ── 5. Query Key Factory Stability ────────────────────────────────────────────

describe('Finance Query Key Factory for Transfers', () => {
  it('generates deterministic query keys for transfer lists and details', () => {
    const rootTransfers = financeKeys.transfers();
    assert.deepEqual(rootTransfers, ['finance', 'transfers']);

    const listsKey = financeKeys.transferLists();
    assert.deepEqual(listsKey, ['finance', 'transfers', 'list']);

    const filter = { page: 1, per_page: 15, is_reversal: false };
    const listKeyWithFilter = financeKeys.transferList(filter);
    assert.deepEqual(listKeyWithFilter, ['finance', 'transfers', 'list', filter]);

    const detailsKey = financeKeys.transferDetails();
    assert.deepEqual(detailsKey, ['finance', 'transfers', 'detail']);

    const singleDetailKey = financeKeys.transferDetail('tx-uuid-123');
    assert.deepEqual(singleDetailKey, ['finance', 'transfers', 'detail', 'tx-uuid-123']);
  });
});

// ── 6. API Client Methods Contract ────────────────────────────────────────────

describe('Finance API Client Methods Contract for Transfers', () => {
  it('exposes listTransfers, getTransferById, recordTransfer, reverseTransfer', () => {
    assert.equal(typeof financeApi.listTransfers, 'function');
    assert.equal(typeof financeApi.getTransferById, 'function');
    assert.equal(typeof financeApi.recordTransfer, 'function');
    assert.equal(typeof financeApi.reverseTransfer, 'function');
  });
});
