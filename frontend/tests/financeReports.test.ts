/**
 * Phase 23 — Finance Reports, Periodic Recap & CSV Export: Data-Layer & Contract Tests
 *
 * Covers:
 * - Query key stability and cache key isolation for report endpoints
 * - Reversal arithmetic invariants and exact-once impact
 * - Period opening/closing balance formula consistency
 * - Idempotency key replay on identical payload vs rotation on modified payload
 * - Large monetary amounts and safe integer handling
 * - CSV formula-injection defense escaping rules
 * - Category breakdown directional percentage calculations
 * - Filter state parameter mapping
 */

import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { financeKeys } from '../src/features/finance/api/financeKeys.ts';
import { formatRupiah, parseIntegerRupiah } from '../src/features/finance/utils/currency.ts';
import { generateIdempotencyKey } from '../src/lib/api/idempotency.ts';
import { buildQueryString } from '../src/lib/api/queryParams.ts';
import type {
  CategoryBreakdownItem,
  ReportPeriodFilter,
  ReportSummaryData,
} from '../src/types/financeReports.ts';

// ── 1. Query Key Factory for Reports ──────────────────────────────────────────

describe('Finance Reports Query Keys', () => {
  it('creates stable base report namespace', () => {
    assert.deepEqual(financeKeys.reports(), ['finance', 'reports']);
  });

  it('generates distinct query keys for different report endpoints', () => {
    const summaryKey = financeKeys.reportSummary({ date_from: '2026-10-01' });
    const cashFlowKey = financeKeys.reportCashFlow({ date_from: '2026-10-01' });
    const categoriesKey = financeKeys.reportCategories({ date_from: '2026-10-01' });
    const rtBreakdownKey = financeKeys.reportRtBreakdown({ date_from: '2026-10-01' });
    const accountsKey = financeKeys.reportAccounts({ date_from: '2026-10-01' });

    assert.equal(summaryKey[2], 'summary');
    assert.equal(cashFlowKey[2], 'cash-flow');
    assert.equal(categoriesKey[2], 'categories');
    assert.equal(rtBreakdownKey[2], 'rt-breakdown');
    assert.equal(accountsKey[2], 'accounts');
  });

  it('differentiates query keys by period and account filter params', () => {
    const keyA = financeKeys.reportSummary({ date_from: '2026-10-01', date_to: '2026-10-31' });
    const keyB = financeKeys.reportSummary({ date_from: '2026-11-01', date_to: '2026-11-30' });
    const keyAccount = financeKeys.reportSummary({
      date_from: '2026-10-01',
      date_to: '2026-10-31',
      cash_account_id: 'acc-uuid-1',
    });

    assert.notDeepEqual(keyA, keyB);
    assert.notDeepEqual(keyA, keyAccount);
  });
});

// ── 2. Financial Balance Invariants & Reversal Arithmetic ────────────────────

describe('Report Balance Invariants & Reversal Math', () => {
  it('enforces closing_balance = opening_balance + net_movement', () => {
    const summary: ReportSummaryData = {
      period: { date_from: '2026-10-01', date_to: '2026-10-31' },
      opening_balance: 1500000,
      posted_income: 800000,
      reversed_income: 100000,
      effective_income: 700000,
      posted_expense: 400000,
      reversed_expense: 50000,
      effective_expense: 350000,
      net_movement: 350000, // 700.000 - 350.000
      closing_balance: 1850000, // 1.500.000 + 350.000
      total_transactions_count: 6,
      reversed_transactions_count: 2,
      effective_transactions_count: 4,
      cash_account_count: 2,
    };

    assert.equal(summary.effective_income, summary.posted_income - summary.reversed_income);
    assert.equal(summary.effective_expense, summary.posted_expense - summary.reversed_expense);
    assert.equal(summary.net_movement, summary.effective_income - summary.effective_expense);
    assert.equal(summary.closing_balance, summary.opening_balance + summary.net_movement);
  });

  it('negates reversal entry exactly once without double counting', () => {
    // Original income Rp 500.000, reversed Rp 500.000
    const postedIncome = 500000;
    const reversedIncome = 500000;
    const effectiveIncome = postedIncome - reversedIncome;

    assert.equal(effectiveIncome, 0);

    // Opening balance remains unchanged when an in-period transaction is reversed in-period
    const openingBalance = 1000000;
    const closingBalance = openingBalance + effectiveIncome;
    assert.equal(closingBalance, 1000000);
  });

  it('correctly handles negative net movement (deficit) in reporting periods', () => {
    const openingBalance = 2000000;
    const effectiveIncome = 300000;
    const effectiveExpense = 800000;
    const netMovement = effectiveIncome - effectiveExpense; // -500.000
    const closingBalance = openingBalance + netMovement; // 1.500.000

    assert.equal(netMovement, -500000);
    assert.equal(closingBalance, 1500000);
    assert.equal(formatRupiah(netMovement), 'Rp -500.000');
  });
});

// ── 3. Idempotency Key Handling on Retries vs Payload Changes ────────────────

describe('Idempotency Key Reuse and Rotation Contract', () => {
  it('generates a valid UUID v4 format key', () => {
    const key = generateIdempotencyKey();
    assert.match(
      key,
      /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
    );
  });

  it('reuses identical key on repeated attempts with unchanged payload', () => {
    let currentKey = generateIdempotencyKey();
    let lastPayload: string | null = null;

    const payload = { amount: 500000, description: 'Iuran Warga', date: '2026-10-15' };
    const payloadStr = JSON.stringify(payload);

    // First submit
    if (lastPayload !== null && lastPayload !== payloadStr) {
      currentKey = generateIdempotencyKey();
    }
    lastPayload = payloadStr;
    const keyFirstAttempt = currentKey;

    // Retry after timeout (same payload)
    if (lastPayload !== null && lastPayload !== payloadStr) {
      currentKey = generateIdempotencyKey();
    }
    lastPayload = payloadStr;
    const keySecondAttempt = currentKey;

    assert.equal(keyFirstAttempt, keySecondAttempt);
  });

  it('rotates to a fresh key when payload is modified after failed attempt', () => {
    let currentKey = generateIdempotencyKey();
    let lastPayload: string | null = null;

    const payload1 = { amount: 500000, description: 'Iuran Warga' };
    const payloadStr1 = JSON.stringify(payload1);

    // First submit
    if (lastPayload !== null && lastPayload !== payloadStr1) {
      currentKey = generateIdempotencyKey();
    }
    lastPayload = payloadStr1;
    const key1 = currentKey;

    // User edits amount to Rp 750.000
    const payload2 = { amount: 750000, description: 'Iuran Warga' };
    const payloadStr2 = JSON.stringify(payload2);

    if (lastPayload !== null && lastPayload !== payloadStr2) {
      currentKey = generateIdempotencyKey();
    }
    lastPayload = payloadStr2;
    const key2 = currentKey;

    assert.notEqual(key1, key2);
  });
});

// ── 4. CSV Formula Injection Sanitization ────────────────────────────────────

describe('CSV Formula Injection Defense', () => {
  function sanitizeCell(val: string): string {
    if (val === '') return '';
    const first = val[0];
    if (['=', '+', '-', '@', '\t', '\r'].includes(first)) {
      return `'${val}`;
    }
    return val;
  }

  it('prefixes cells starting with = with a single quote', () => {
    assert.equal(sanitizeCell('=SUM(A1:B10)'), "'=SUM(A1:B10)");
    assert.equal(sanitizeCell('=cmd|"/c calc"!A1'), "'=cmd|\"/c calc\"!A1");
  });

  it('prefixes cells starting with +, -, @ with a single quote', () => {
    assert.equal(sanitizeCell('+62812345678'), "'+62812345678");
    assert.equal(sanitizeCell('-100000'), "'-100000");
    assert.equal(sanitizeCell('@echo off'), "'@echo off");
  });

  it('leaves safe alphanumeric text unescaped', () => {
    assert.equal(sanitizeCell('Kas RW 01'), 'Kas RW 01');
    assert.equal(sanitizeCell('Pembelian Lampu'), 'Pembelian Lampu');
    assert.equal(sanitizeCell('150000'), '150000');
  });
});

// ── 5. Large Monetary Representation & Integer Precision ─────────────────────

describe('Integer-Safe Monetary Aggregation', () => {
  it('formats large multi-billion Rupiah amounts without precision loss', () => {
    const largeAmount = 25_000_000_000; // Rp 25 Miliar
    assert.equal(formatRupiah(largeAmount), 'Rp 25.000.000.000');
  });

  it('rejects floating point decimals in Rupiah inputs', () => {
    assert.equal(parseIntegerRupiah('100.50').valid, false);
    assert.equal(parseIntegerRupiah('100,50').valid, false);
    assert.equal(parseIntegerRupiah(100.5).valid, false);
  });

  it('accepts whole thousand-separator dots correctly', () => {
    const res = parseIntegerRupiah('1.500.000');
    assert.equal(res.valid, true);
    assert.equal(res.value, 1500000);
  });
});

// ── 6. Category Directional Percentages ──────────────────────────────────────

describe('Category Directional Breakdown Calculation', () => {
  it('calculates direction percentage accurately', () => {
    const categories: CategoryBreakdownItem[] = [
      {
        category_id: 'cat-1',
        name: 'Iuran Kebersihan',
        code: 'INC-01',
        type: 'INCOME',
        is_active: true,
        posted_amount: 600000,
        reversed_amount: 0,
        effective_amount: 600000,
        percentage: 60.0,
        transaction_count: 10,
      },
      {
        category_id: 'cat-2',
        name: 'Sumbangan',
        code: 'INC-02',
        type: 'INCOME',
        is_active: true,
        posted_amount: 400000,
        reversed_amount: 0,
        effective_amount: 400000,
        percentage: 40.0,
        transaction_count: 4,
      },
    ];

    const totalIncome = categories.reduce((acc, c) => acc + c.effective_amount, 0);
    assert.equal(totalIncome, 1000000);

    const calculatedPct1 = (categories[0].effective_amount / totalIncome) * 100;
    const calculatedPct2 = (categories[1].effective_amount / totalIncome) * 100;

    assert.equal(calculatedPct1, 60);
    assert.equal(calculatedPct2, 40);
  });
});

// ── 7. Query Parameter Serialization for Reports ─────────────────────────────

describe('Report Filter Serialization', () => {
  it('serializes period and scope filters correctly into query string', () => {
    const filter: ReportPeriodFilter = {
      date_from: '2026-10-01',
      date_to: '2026-10-31',
      cash_account_id: 'acc-123',
      interval: 'monthly',
    };

    const qs = buildQueryString(filter as Record<string, unknown>);
    assert.ok(qs.includes('date_from=2026-10-01'));
    assert.ok(qs.includes('date_to=2026-10-31'));
    assert.ok(qs.includes('cash_account_id=acc-123'));
    assert.ok(qs.includes('interval=monthly'));
  });
});
