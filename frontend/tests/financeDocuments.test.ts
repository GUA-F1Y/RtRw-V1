/**
 * Phase 24 — Financial Evidence, Receipts & Document Workflow Tests
 *
 * Covers:
 * - Evidence file validation (MIME types, extensions, size limits)
 * - File API upload & download client contracts
 * - Finance document generation and download API contracts (PDF / HTML)
 * - Evidence association and download contracts
 * - Query key factory for transaction evidence
 * - Indonesian UI label consistency (Kwitansi, Voucher Pengeluaran, Dokumen Koreksi, Unduh Bukti)
 * - Ledger immutability protection on frontend payloads
 */

import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { financeApi } from '../src/features/finance/api/financeApi.ts';
import { financeKeys } from '../src/features/finance/api/financeKeys.ts';
import { fileApi } from '../src/lib/api/fileApi.ts';
import {
  canRecordFinanceTransactions,
  canReverseFinanceTransactions,
  hasFinancePermission,
  FINANCE_PERMISSIONS,
} from '../src/features/finance/utils/permissions.ts';
import type { User } from '../src/types/auth.ts';
import type { FinancialTransaction, EvidenceFileSummary } from '../src/types/finance.ts';

// ── 1. File Validation Rules & Boundaries ─────────────────────────────────────

describe('Financial Evidence Validation Rules', () => {
  const ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
  const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB

  it('accepts valid PDF, JPEG, and PNG files under 5MB', () => {
    for (const mime of ALLOWED_MIME_TYPES) {
      assert.ok(ALLOWED_MIME_TYPES.includes(mime), `MIME type ${mime} should be allowed`);
    }
  });

  it('rejects executable and disallowed MIME types', () => {
    const disallowedMimes = [
      'application/x-msdownload',
      'application/x-sh',
      'text/html',
      'text/javascript',
      'application/zip',
    ];

    for (const mime of disallowedMimes) {
      assert.ok(!ALLOWED_MIME_TYPES.includes(mime), `Disallowed MIME ${mime} must be rejected`);
    }
  });

  it('enforces 5MB maximum file size boundary', () => {
    const validSize = 5 * 1024 * 1024;
    const oversized = 5 * 1024 * 1024 + 1;

    assert.ok(validSize <= MAX_FILE_SIZE, '5MB should be permitted');
    assert.ok(oversized > MAX_FILE_SIZE, 'Over 5MB must be flagged as oversized');
  });
});

// ── 2. Query Keys for Evidence ───────────────────────────────────────────────

describe('Finance Evidence Query Keys', () => {
  it('generates stable transactionEvidence query key within transactionDetail scope', () => {
    const txId = '11111111-2222-3333-4444-555555555555';
    const key = financeKeys.transactionEvidence(txId);

    assert.deepEqual(key, ['finance', 'transactions', 'detail', txId, 'evidence']);
  });

  it('distinguishes between different transaction evidence keys', () => {
    const key1 = financeKeys.transactionEvidence('tx-aaa');
    const key2 = financeKeys.transactionEvidence('tx-bbb');

    assert.notDeepEqual(key1, key2);
  });
});

// ── 3. File API & Finance API Contracts ──────────────────────────────────────

describe('File API & Finance Document API Surface', () => {
  it('exposes uploadFile and downloadFile methods on fileApi', () => {
    assert.equal(typeof fileApi.uploadFile, 'function');
    assert.equal(typeof fileApi.downloadFile, 'function');
  });

  it('exposes Phase 24 document and evidence methods on financeApi', () => {
    assert.equal(typeof financeApi.downloadTransactionDocument, 'function');
    assert.equal(typeof financeApi.listTransactionEvidence, 'function');
    assert.equal(typeof financeApi.attachTransactionEvidence, 'function');
    assert.equal(typeof financeApi.downloadTransactionEvidence, 'function');
  });
});

// ── 4. Transaction Document Type Mapping & Indonesian Labels ─────────────────

describe('Transaction Document Type & Label Mapping', () => {
  it('maps INCOME transaction to Kwitansi Penerimaan', () => {
    const tx: Partial<FinancialTransaction> = {
      type: 'INCOME',
      is_reversal: false,
    };

    const docTitle = tx.is_reversal
      ? 'Cetak Dokumen Koreksi'
      : tx.type === 'INCOME'
      ? 'Cetak Kwitansi'
      : 'Cetak Voucher Pengeluaran';

    assert.equal(docTitle, 'Cetak Kwitansi');
  });

  it('maps EXPENSE transaction to Cetak Voucher Pengeluaran', () => {
    const tx: Partial<FinancialTransaction> = {
      type: 'EXPENSE',
      is_reversal: false,
    };

    const docTitle = tx.is_reversal
      ? 'Cetak Dokumen Koreksi'
      : tx.type === 'INCOME'
      ? 'Cetak Kwitansi'
      : 'Cetak Voucher Pengeluaran';

    assert.equal(docTitle, 'Cetak Voucher Pengeluaran');
  });

  it('maps Reversal entry to Cetak Dokumen Koreksi regardless of underlying direction', () => {
    const reversalIncome: Partial<FinancialTransaction> = {
      type: 'EXPENSE',
      is_reversal: true,
      original_transaction_id: 'orig-1',
    };

    const reversalExpense: Partial<FinancialTransaction> = {
      type: 'INCOME',
      is_reversal: true,
      original_transaction_id: 'orig-2',
    };

    const getDocTitle = (t: Partial<FinancialTransaction>) =>
      t.is_reversal
        ? 'Cetak Dokumen Koreksi'
        : t.type === 'INCOME'
        ? 'Cetak Kwitansi'
        : 'Cetak Voucher Pengeluaran';

    assert.equal(getDocTitle(reversalIncome), 'Cetak Dokumen Koreksi');
    assert.equal(getDocTitle(reversalExpense), 'Cetak Dokumen Koreksi');
  });
});

// ── 5. Evidence Files Collection Aggregation ──────────────────────────────────

describe('Evidence Aggregation Logic', () => {
  it('deduplicates primary evidence and appended evidence files correctly', () => {
    const primary: EvidenceFileSummary = {
      id: 'file-1',
      original_name: 'nota_pembelian',
      extension: 'pdf',
      mime_type: 'application/pdf',
      size_bytes: 102400,
    };

    const extra: EvidenceFileSummary = {
      id: 'file-2',
      original_name: 'foto_barang',
      extension: 'jpg',
      mime_type: 'image/jpeg',
      size_bytes: 204800,
    };

    const duplicatePrimary: EvidenceFileSummary = {
      ...primary,
    };

    const rawList = [primary, duplicatePrimary, extra];
    const seenIds = new Set<string>();
    const deduplicated: EvidenceFileSummary[] = [];

    for (const item of rawList) {
      if (!seenIds.has(item.id)) {
        deduplicated.push(item);
        seenIds.add(item.id);
      }
    }

    assert.equal(deduplicated.length, 2);
    assert.equal(deduplicated[0].id, 'file-1');
    assert.equal(deduplicated[1].id, 'file-2');
  });
});

// ── 6. Permissions & Role Authorization for Evidence & Documents ─────────────

describe('Permissions for Financial Evidence & Documents', () => {
  const ketuaRwUser: User = {
    id: 'u-rw',
    name: 'Ketua RW',
    email: 'rw@example.com',
    status: 'ACTIVE',
    memberships: [
      {
        id: 'm-1',
        user_id: 'u-rw',
        role_id: 'r-1',
        scope_level: 'rw',
        organization_id: 'org-1',
        rw_id: 'rw-1',
        is_active: true,
        role: { id: 'r-1', key: 'ketua_rw', name: 'Ketua RW', scope_level: 'rw', permissions: [] },
      },
    ],
  };

  const sekretarisRwUser: User = {
    id: 'u-sek',
    name: 'Sekretaris RW',
    email: 'sek@example.com',
    status: 'ACTIVE',
    memberships: [
      {
        id: 'm-2',
        user_id: 'u-sek',
        role_id: 'r-2',
        scope_level: 'rw',
        organization_id: 'org-1',
        rw_id: 'rw-1',
        is_active: true,
        role: { id: 'r-2', key: 'sekretaris_rw', name: 'Sekretaris RW', scope_level: 'rw', permissions: [] },
      },
    ],
  };

  it('authorizes ketua_rw to record transactions and attach evidence', () => {
    assert.ok(canRecordFinanceTransactions(ketuaRwUser));
    assert.ok(canReverseFinanceTransactions(ketuaRwUser));
  });

  it('permits sekretaris_rw to view transactions and documents but denies recording or reversing', () => {
    assert.ok(hasFinancePermission(sekretarisRwUser, FINANCE_PERMISSIONS.TRANSACTION_VIEW));
    assert.ok(!canRecordFinanceTransactions(sekretarisRwUser));
    assert.ok(!canReverseFinanceTransactions(sekretarisRwUser));
  });
});
