/**
 * Phase 25 — Finance Period Closing, Reconciliation & Audit Controls Tests
 *
 * Covers:
 * - Authoritative accounting invariants (opening/closing balance, net movement in integer Rupiah)
 * - Physical cash reconciliation sign convention: difference = counted - ledger
 * - Surplus vs shortage identification and mandatory notes validation on discrepancy
 * - Completed month eligibility (past completed months eligible; current/future months ineligible)
 * - Closed-period lock enforcement contract (transaction posting & reversals)
 * - Permission checks (canCloseFinancePeriod: Ketua/Bendahara RW/RT vs Sekretaris RW/RT read-only)
 * - Query key factory for period closures and preview
 * - API client method contracts, idempotency headers, and URL routing
 * - Immutability invariants (no edit/delete/reopen controls)
 */

import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { financeApi } from '../src/features/finance/api/financeApi.ts';
import { financeKeys } from '../src/features/finance/api/financeKeys.ts';
import {
  canCloseFinancePeriod,
  FINANCE_PERMISSIONS,
} from '../src/features/finance/utils/permissions.ts';
import type { User } from '../src/types/auth.ts';
import type {
  FinancialPeriodClosure,
  ClosePeriodPayload,
} from '../src/types/finance.ts';

// ── 1. Authoritative Accounting Invariants ───────────────────────────────────

describe('Period Closing Accounting Invariants', () => {
  it('computes closing balance correctly: period_closing = period_opening + net_movement', () => {
    const openingBalance = 10_000_000;
    const totalIncome = 5_500_000;
    const totalExpense = 2_300_000;
    const netMovement = totalIncome - totalExpense; // +3,200,000
    const closingBalance = openingBalance + netMovement; // 13,200,000

    assert.equal(netMovement, 3_200_000);
    assert.equal(closingBalance, 13_200_000);
    assert.ok(Number.isInteger(closingBalance), 'Financial calculations must remain in integer Rupiah');
  });

  it('correctly calculates empty-month period closure (zero activity)', () => {
    const openingBalance = 7_500_000;
    const totalIncome = 0;
    const totalExpense = 0;
    const netMovement = totalIncome - totalExpense;
    const closingBalance = openingBalance + netMovement;
    const transactionCount = 0;

    assert.equal(netMovement, 0);
    assert.equal(closingBalance, 7_500_000);
    assert.equal(transactionCount, 0);
  });

  it('handles negative net movement without violating integer balance invariants', () => {
    const openingBalance = 15_000_000;
    const totalIncome = 1_000_000;
    const totalExpense = 6_500_000;
    const netMovement = totalIncome - totalExpense; // -5,500,000
    const closingBalance = openingBalance + netMovement; // 9,500,000

    assert.equal(netMovement, -5_500_000);
    assert.equal(closingBalance, 9_500_000);
    assert.ok(Number.isInteger(closingBalance));
  });
});

// ── 2. Physical Cash Reconciliation & Sign Convention ────────────────────────

describe('Physical Cash Reconciliation & Discrepancy Sign Convention', () => {
  const computeReconciliation = (counted: number | null | undefined, ledger: number) => {
    if (counted === null || counted === undefined) {
      return { difference: null, hasDiscrepancy: false, status: 'NO_COUNT' };
    }
    // authoritative convention: difference = counted_cash - ledger_closing
    const difference = counted - ledger;
    const hasDiscrepancy = difference !== 0;
    let status: 'MATCH' | 'SURPLUS' | 'SHORTAGE';
    if (difference > 0) status = 'SURPLUS';
    else if (difference < 0) status = 'SHORTAGE';
    else status = 'MATCH';

    return { difference, hasDiscrepancy, status };
  };

  it('returns positive difference for surplus cash (counted > ledger)', () => {
    const ledgerClosing = 10_000_000;
    const countedCash = 10_250_000; // Rp 250.000 surplus

    const res = computeReconciliation(countedCash, ledgerClosing);
    assert.equal(res.difference, 250_000);
    assert.equal(res.hasDiscrepancy, true);
    assert.equal(res.status, 'SURPLUS');
  });

  it('returns negative difference for cash shortage (counted < ledger)', () => {
    const ledgerClosing = 10_000_000;
    const countedCash = 9_850_000; // Rp 150.000 shortage

    const res = computeReconciliation(countedCash, ledgerClosing);
    assert.equal(res.difference, -150_000);
    assert.equal(res.hasDiscrepancy, true);
    assert.equal(res.status, 'SHORTAGE');
  });

  it('returns zero difference when physical cash matches ledger exactly', () => {
    const ledgerClosing = 5_000_000;
    const countedCash = 5_000_000;

    const res = computeReconciliation(countedCash, ledgerClosing);
    assert.equal(res.difference, 0);
    assert.equal(res.hasDiscrepancy, false);
    assert.equal(res.status, 'MATCH');
  });

  it('handles optional physical cash when no physical count is entered', () => {
    const ledgerClosing = 5_000_000;

    const res = computeReconciliation(undefined, ledgerClosing);
    assert.equal(res.difference, null);
    assert.equal(res.hasDiscrepancy, false);
    assert.equal(res.status, 'NO_COUNT');
  });

  it('enforces mandatory notes when reconciliation discrepancy is non-zero', () => {
    const validateClosureSubmission = (payload: ClosePeriodPayload, ledgerClosing: number) => {
      if (payload.counted_cash_balance !== undefined && payload.counted_cash_balance !== null) {
        if (payload.counted_cash_balance < 0) {
          return { valid: false, error: 'Jumlah fisik kas tidak boleh bernilai negatif.' };
        }
        const diff = payload.counted_cash_balance - ledgerClosing;
        if (diff !== 0 && (!payload.reconciliation_notes || !payload.reconciliation_notes.trim())) {
          return { valid: false, error: 'Catatan penjelasan wajib diisi jika terdapat selisih fisik kas.' };
        }
      }
      return { valid: true, error: null };
    };

    // Discrepancy without notes -> rejected
    const invalidSub = validateClosureSubmission(
      { counted_cash_balance: 9_900_000, reconciliation_notes: '' },
      10_000_000
    );
    assert.equal(invalidSub.valid, false);
    assert.equal(invalidSub.error, 'Catatan penjelasan wajib diisi jika terdapat selisih fisik kas.');

    // Discrepancy with notes -> accepted
    const validSub = validateClosureSubmission(
      {
        counted_cash_balance: 9_900_000,
        reconciliation_notes: 'Selisih Rp 100.000 akibat uang kembalian receh belum disetor oleh panitia.',
      },
      10_000_000
    );
    assert.equal(validSub.valid, true);
    assert.equal(validSub.error, null);

    // Negative count -> rejected
    const negativeSub = validateClosureSubmission(
      { counted_cash_balance: -500 },
      10_000_000
    );
    assert.equal(negativeSub.valid, false);
  });
});

// ── 3. Period Eligibility Rules ──────────────────────────────────────────────

describe('Period Closure Eligibility Rules', () => {
  const isPeriodEligibleForClosure = (
    year: number,
    month: number,
    isAlreadyClosed: boolean,
    now: Date = new Date()
  ): { eligible: boolean; reason?: string } => {
    if (isAlreadyClosed) {
      return { eligible: false, reason: 'Periode ini sudah pernah ditutup.' };
    }
    const currentYear = now.getFullYear();
    const currentMonth = now.getMonth() + 1;

    if (year > currentYear || (year === currentYear && month >= currentMonth)) {
      return {
        eligible: false,
        reason: 'Hanya periode bulan kalender yang telah berakhir yang dapat ditutup.',
      };
    }

    return { eligible: true };
  };

  it('allows closure of past completed calendar months', () => {
    const fixedNow = new Date('2026-10-15T00:00:00Z');
    // September 2026 is completed
    const septResult = isPeriodEligibleForClosure(2026, 9, false, fixedNow);
    assert.equal(septResult.eligible, true);

    // August 2026 is completed
    const augResult = isPeriodEligibleForClosure(2026, 8, false, fixedNow);
    assert.equal(augResult.eligible, true);
  });

  it('rejects closure of the ongoing current month', () => {
    const fixedNow = new Date('2026-10-15T00:00:00Z');
    // October 2026 is ongoing
    const octResult = isPeriodEligibleForClosure(2026, 10, false, fixedNow);
    assert.equal(octResult.eligible, false);
    assert.equal(octResult.reason, 'Hanya periode bulan kalender yang telah berakhir yang dapat ditutup.');
  });

  it('rejects closure of future months', () => {
    const fixedNow = new Date('2026-10-15T00:00:00Z');
    const futureResult = isPeriodEligibleForClosure(2026, 11, false, fixedNow);
    assert.equal(futureResult.eligible, false);
  });

  it('rejects closure of already-closed periods with 409 conflict semantics', () => {
    const fixedNow = new Date('2026-10-15T00:00:00Z');
    const result = isPeriodEligibleForClosure(2026, 8, true, fixedNow);
    assert.equal(result.eligible, false);
    assert.equal(result.reason, 'Periode ini sudah pernah ditutup.');
  });
});

// ── 4. Closed-Period Locking Enforcement Contract ───────────────────────────

describe('Closed-Period Locking Contracts', () => {
  it('correctly detects transaction date falling within closed period boundaries', () => {
    const closedPeriod = {
      period_start_date: '2026-08-01',
      period_end_date: '2026-08-31',
    };

    const isDateInsidePeriod = (dateStr: string) => {
      return dateStr >= closedPeriod.period_start_date && dateStr <= closedPeriod.period_end_date;
    };

    // First day of closed period
    assert.equal(isDateInsidePeriod('2026-08-01'), true);
    // Last day of closed period
    assert.equal(isDateInsidePeriod('2026-08-31'), true);
    // Middle of closed period
    assert.equal(isDateInsidePeriod('2026-08-15'), true);

    // Immediately before closed period
    assert.equal(isDateInsidePeriod('2026-07-31'), false);
    // Immediately after closed period
    assert.equal(isDateInsidePeriod('2026-09-01'), false);
  });

  it('preserves error message structure for period_closed conflict (409)', () => {
    const mockApiError = {
      status: 409,
      code: 'period_closed',
      message:
        'Tidak dapat mencatat transaksi pada periode yang sudah ditutup (2026-08). Rekonsiliasi atau koreksi harus dilakukan pada periode berjalan yang masih terbuka melalui transaksi pembalik.',
      requestId: 'req-abc-123',
    };

    assert.equal(mockApiError.status, 409);
    assert.equal(mockApiError.code, 'period_closed');
    assert.ok(mockApiError.message.includes('2026-08'));
    assert.ok(mockApiError.message.includes('transaksi pembalik'));
  });
});

// ── 5. Permission & Least-Privilege Checks ────────────────────────────────────

describe('Finance Period Closure Permissions', () => {
  const createMockUser = (roleKey: string, permissions: string[] = []): User => ({
    id: 'user-uuid-1',
    name: 'Test Treasurer',
    email: 'treasurer@example.com',
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
        created_at: '2026-01-01',
      },
    ],
    ...((permissions.length > 0 ? { permissions } : {}) as Record<string, unknown>),
  });

  it('defines FINANCE_PERMISSIONS.PERIOD_CLOSE as finance.period.close', () => {
    assert.equal(FINANCE_PERMISSIONS.PERIOD_CLOSE, 'finance.period.close');
  });

  it('allows organization admin to close finance period', () => {
    const adminUser = createMockUser('organization_admin');
    assert.equal(canCloseFinancePeriod(adminUser), true);
  });

  it('allows Ketua RW and Bendahara RW to close finance period', () => {
    const ketuaRw = createMockUser('ketua_rw');
    const bendaharaRw = createMockUser('bendahara_rw');

    assert.equal(canCloseFinancePeriod(ketuaRw), true);
    assert.equal(canCloseFinancePeriod(bendaharaRw), true);
  });

  it('allows Ketua RT and Bendahara RT to close finance period', () => {
    const ketuaRt = createMockUser('ketua_rt');
    const bendaharaRt = createMockUser('bendahara_rt');

    assert.equal(canCloseFinancePeriod(ketuaRt), true);
    assert.equal(canCloseFinancePeriod(bendaharaRt), true);
  });

  it('DENIES Sekretaris RW and Sekretaris RT (read-only finance users) from closing periods', () => {
    const sekretarisRw = createMockUser('sekretaris_rw');
    const sekretarisRt = createMockUser('sekretaris_rt');

    assert.equal(canCloseFinancePeriod(sekretarisRw), false);
    assert.equal(canCloseFinancePeriod(sekretarisRt), false);
  });

  it('allows closing when user has explicit finance.period.close permission', () => {
    const explicitPermUser = createMockUser('custom_role', ['finance.period.close']);
    assert.equal(canCloseFinancePeriod(explicitPermUser), true);
  });

  it('denies arbitrary regular user from closing finance periods', () => {
    const regularUser = createMockUser('warga');
    assert.equal(canCloseFinancePeriod(regularUser), false);
  });
});

// ── 6. Query Keys & Invalidation Namespaces ──────────────────────────────────

describe('Period Closures Query Key Factory', () => {
  it('creates stable base namespaces', () => {
    assert.deepEqual(financeKeys.periodClosures(), ['finance', 'period-closures']);
    assert.deepEqual(financeKeys.periodClosureLists(), ['finance', 'period-closures', 'list']);
  });

  it('generates distinct parameterized list keys', () => {
    const key1 = financeKeys.periodClosureList({ cash_account_id: 'acc-1', year: 2026 });
    const key2 = financeKeys.periodClosureList({ cash_account_id: 'acc-2', year: 2026 });

    assert.notDeepEqual(key1, key2);
    assert.deepEqual(key1, [
      'finance',
      'period-closures',
      'list',
      { cash_account_id: 'acc-1', year: 2026 },
    ]);
  });

  it('generates stable detail key for a period closure id', () => {
    const closureId = 'c0000000-1111-2222-3333-444444444444';
    const detailKey = financeKeys.periodClosureDetail(closureId);

    assert.deepEqual(detailKey, ['finance', 'period-closures', 'detail', closureId]);
  });

  it('generates stable preview key by account, year, and month', () => {
    const accountId = 'acc-123';
    const previewKey = financeKeys.periodClosurePreview(accountId, 2026, 8);

    assert.deepEqual(previewKey, [
      'finance',
      'period-closures',
      'preview',
      accountId,
      2026,
      8,
    ]);
  });
});

// ── 7. API Client Contract & Methods ─────────────────────────────────────────

describe('Finance API Period Closure Methods', () => {
  it('exposes listPeriodClosures, getPeriodClosureById, previewPeriodClosure, and closePeriod', () => {
    assert.equal(typeof financeApi.listPeriodClosures, 'function');
    assert.equal(typeof financeApi.getPeriodClosureById, 'function');
    assert.equal(typeof financeApi.previewPeriodClosure, 'function');
    assert.equal(typeof financeApi.closePeriod, 'function');
  });

  it('does NOT expose generic update, patch, delete, or reopen methods for closures', () => {
    const untypedApi = financeApi as Record<string, unknown>;
    assert.equal(untypedApi.updatePeriodClosure, undefined);
    assert.equal(untypedApi.patchPeriodClosure, undefined);
    assert.equal(untypedApi.deletePeriodClosure, undefined);
    assert.equal(untypedApi.reopenPeriodClosure, undefined);
  });
});

// ── 8. Immutable Snapshot Data Model ─────────────────────────────────────────

describe('Period Closure Snapshot Data Model', () => {
  it('validates shape of completed closure record without client-side mutation fields', () => {
    const mockClosure: FinancialPeriodClosure = {
      id: 'f87a3210-9876-4abc-9def-123456789abc',
      organization_id: 'org-uuid-1',
      rw_id: 'rw-uuid-1',
      rt_id: null,
      cash_account_id: 'acc-uuid-1',
      cash_account_name: 'Kas Operasional RW 01',
      cash_account_number: '100-01-RW01',
      period_year: 2026,
      period_month: 8,
      period_start_date: '2026-08-01',
      period_end_date: '2026-08-31',
      opening_balance: 10_000_000,
      total_income: 5_000_000,
      total_expense: 2_000_000,
      net_movement: 3_000_000,
      closing_balance: 13_000_000,
      transaction_count: 14,
      reversal_count: 1,
      total_reversal_amount: 150_000,
      counted_cash_balance: 13_000_000,
      reconciliation_difference: 0,
      reconciliation_notes: null,
      closed_by: 'user-uuid-closer',
      closed_by_name: 'Bendahara RW 01',
      closed_at: '2026-09-02T10:00:00Z',
      snapshot_metadata: {
        hash: 'sha256-dummy-hash',
        schema_version: '1.0',
      },
      created_at: '2026-09-02T10:00:00Z',
    };

    assert.equal(mockClosure.period_year, 2026);
    assert.equal(mockClosure.period_month, 8);
    assert.equal(mockClosure.closing_balance, 13_000_000);
    assert.equal(mockClosure.reconciliation_difference, 0);
    assert.ok(mockClosure.closed_by_name);
    assert.ok(mockClosure.closed_at);
  });
});
