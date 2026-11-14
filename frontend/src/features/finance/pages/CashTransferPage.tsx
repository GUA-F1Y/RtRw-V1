import React, { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../../app/providers/useAuth.ts';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Badge } from '../../../components/ui/Badge.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import type { CashTransfer, TransferQueryParams } from '../../../types/finance.ts';
import { useCashAccounts, useCashTransfers } from '../api/financeQueries.ts';
import {
  RecordTransferDialog,
  ReverseTransferDialog,
  TransferDetailModal,
  TransferHistoryTable,
} from '../components/index.ts';
import { formatRupiah } from '../utils/currency.ts';
import {
  canCreateFinanceTransfer,
  canReverseFinanceTransfer,
} from '../utils/permissions.ts';

export const CashTransferPage: React.FC = () => {
  const { user, activeScope } = useAuth();

  const canCreate = canCreateFinanceTransfer(user);
  const canReverse = canReverseFinanceTransfer(user);

  // Filters state
  const [filters, setFilters] = useState<TransferQueryParams>({
    page: 1,
    per_page: 15,
  });

  // Modals state
  const [isRecordOpen, setIsRecordOpen] = useState(false);
  const [detailTransfer, setDetailTransfer] = useState<CashTransfer | null>(null);
  const [reversingTransfer, setReversingTransfer] = useState<CashTransfer | null>(null);

  // Queries
  const {
    data: transfersResponse,
    isLoading: transfersLoading,
    error: transfersError,
    refetch: refetchTransfers,
  } = useCashTransfers(filters);

  const { data: accountsResponse } = useCashAccounts({ status: 'active', per_page: 100 });
  const accounts = useMemo(() => accountsResponse?.data ?? [], [accountsResponse?.data]);

  const transfers = useMemo(() => transfersResponse?.data ?? [], [transfersResponse?.data]);
  const pagination = transfersResponse?.meta?.pagination;

  // Computed summary metrics
  const stats = useMemo(() => {
    let totalVolume = 0;
    let normalCount = 0;
    let reversalCount = 0;

    for (const tx of transfers) {
      totalVolume += tx.amount;
      if (tx.is_reversal) reversalCount++;
      else normalCount++;
    }

    return {
      totalVolume,
      totalCount: Number(pagination?.total ?? transfers.length),
      normalCount,
      reversalCount,
    };
  }, [transfers, pagination?.total]);

  const handleOpenReverse = (tx: CashTransfer) => {
    setDetailTransfer(null);
    setReversingTransfer(tx);
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
        <div>
          <div className="flex items-center gap-2.5">
            <h1 className="text-xl font-bold text-white tracking-tight">Transfer Kas Antar Rekening</h1>
            <Badge variant="indigo" size="sm">
              {activeScope?.scopeLevel === 'rt' ? 'Cakupan RT' : 'Cakupan RW'}
            </Badge>
          </div>
          <p className="text-xs text-slate-400 mt-1">
            Pencatatan mutasi internal antar rekening kas RW dan RT tanpa menghasilkan pendapatan atau belanja operasional semu.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Link
            to="/app/finance"
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white border border-slate-700/80 transition-colors"
          >
            <svg className="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
            </svg>
            <span>Ikhtisar Kas</span>
          </Link>

          <Link
            to="/app/finance/reports"
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white border border-slate-700/80 transition-colors"
          >
            <svg className="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span>Laporan & Rekap</span>
          </Link>

          <Link
            to="/app/finance/periods"
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white border border-slate-700/80 transition-colors"
          >
            <svg className="w-3.5 h-3.5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            <span>Tutup Buku</span>
          </Link>

          {canCreate && (
            <Button
              type="button"
              variant="primary"
              size="sm"
              onClick={() => setIsRecordOpen(true)}
              className="text-xs"
            >
              + Transfer Kas
            </Button>
          )}
        </div>
      </div>

      {/* KPI Overview Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {/* Total Volume Transfer */}
        <div className="bg-gradient-to-br from-indigo-950/50 via-slate-900 to-slate-900 border border-indigo-800/40 rounded-xl p-4 shadow-lg shadow-indigo-950/20">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-indigo-400">Total Volume Transfer</span>
            <span className="text-[11px] text-slate-500 font-mono">Halaman Ini</span>
          </div>
          <div className="mt-2 text-2xl font-bold text-white tracking-tight font-mono">
            {formatRupiah(stats.totalVolume)}
          </div>
          <div className="mt-1 text-[11px] text-slate-400">
            Total perputaran dana internal pada tabel aktif
          </div>
        </div>

        {/* Frekuensi Transaksi */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-4">
          <div className="text-xs font-medium text-slate-400">Total Mutasi Transfer</div>
          <div className="mt-2 text-2xl font-bold text-white tracking-tight">
            {stats.totalCount} <span className="text-sm font-normal text-slate-400">transaksi</span>
          </div>
          <div className="mt-1 text-[11px] text-slate-400">
            Terdaftar di buku mutasi kas antar rekening
          </div>
        </div>

        {/* Info Prinsip Akuntansi */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-4 flex flex-col justify-between">
          <div className="flex items-center gap-1.5 text-xs font-medium text-emerald-400">
            <svg className="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Integritas Arus Kas</span>
          </div>
          <p className="mt-2 text-[11px] text-slate-300 leading-relaxed">
            Transfer antar kas bersifat netral terhadap laba/rugi operasional RW dan tidak dihitung ganda pada laporan konsolidasi.
          </p>
        </div>
      </div>

      {/* Error state */}
      {transfersError && (
        <Alert
          type="error"
          title="Gagal Memuat Data Transfer"
          message={transfersError.message || 'Terjadi kesalahan sistem saat memuat riwayat transfer kas.'}
        />
      )}

      {/* Main Transfer History Table */}
      <div className="space-y-3">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-semibold text-slate-200">Riwayat Mutasi Transfer Kas</h2>
          <button
            type="button"
            onClick={() => refetchTransfers()}
            className="text-xs text-slate-400 hover:text-slate-200 flex items-center gap-1"
          >
            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span>Segarkan</span>
          </button>
        </div>

        <TransferHistoryTable
          transfers={transfers}
          pagination={pagination}
          isLoading={transfersLoading}
          filters={filters}
          onFilterChange={setFilters}
          onPageChange={(page) => setFilters((prev) => ({ ...prev, page }))}
          onViewDetail={(tx) => setDetailTransfer(tx)}
          onReverseClick={handleOpenReverse}
          canReverse={canReverse}
          accounts={accounts}
        />
      </div>

      {/* Modals */}
      <RecordTransferDialog
        open={isRecordOpen}
        onClose={() => setIsRecordOpen(false)}
        onSuccess={() => {
          refetchTransfers();
        }}
      />

      <ReverseTransferDialog
        transfer={reversingTransfer}
        open={Boolean(reversingTransfer)}
        onClose={() => setReversingTransfer(null)}
        onSuccess={() => {
          refetchTransfers();
        }}
      />

      <TransferDetailModal
        transfer={detailTransfer}
        open={Boolean(detailTransfer)}
        onClose={() => setDetailTransfer(null)}
        onReverseClick={handleOpenReverse}
        canReverse={canReverse}
      />
    </div>
  );
};
