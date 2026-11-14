import React from 'react';
import { Badge } from '../../../components/ui/Badge.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import type { PaginationMeta } from '../../../lib/api/types.ts';
import type { CashAccount, CashTransfer, TransferQueryParams } from '../../../types/finance.ts';
import { formatRupiah } from '../utils/currency.ts';

interface TransferHistoryTableProps {
  transfers: CashTransfer[];
  pagination?: PaginationMeta;
  isLoading?: boolean;
  filters: TransferQueryParams;
  onFilterChange: (filters: TransferQueryParams) => void;
  onPageChange: (page: number) => void;
  onViewDetail: (transfer: CashTransfer) => void;
  onReverseClick?: (transfer: CashTransfer) => void;
  canReverse?: boolean;
  accounts?: CashAccount[];
}

export const TransferHistoryTable: React.FC<TransferHistoryTableProps> = ({
  transfers,
  pagination,
  isLoading = false,
  filters,
  onFilterChange,
  onPageChange,
  onViewDetail,
  onReverseClick,
  canReverse = false,
  accounts = [],
}) => {
  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    onFilterChange({
      ...filters,
      q: e.target.value || undefined,
      page: 1,
    });
  };

  const handleDateFromChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    onFilterChange({
      ...filters,
      date_from: e.target.value || undefined,
      page: 1,
    });
  };

  const handleDateToChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    onFilterChange({
      ...filters,
      date_to: e.target.value || undefined,
      page: 1,
    });
  };

  const handleSourceAccountFilter = (e: React.ChangeEvent<HTMLSelectElement>) => {
    onFilterChange({
      ...filters,
      source_cash_account_id: e.target.value || undefined,
      page: 1,
    });
  };

  const handleDestinationAccountFilter = (e: React.ChangeEvent<HTMLSelectElement>) => {
    onFilterChange({
      ...filters,
      destination_cash_account_id: e.target.value || undefined,
      page: 1,
    });
  };

  const handleReversalFilter = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const val = e.target.value;
    onFilterChange({
      ...filters,
      is_reversal: val === 'true' ? true : val === 'false' ? false : undefined,
      page: 1,
    });
  };

  const handleResetFilters = () => {
    onFilterChange({
      page: 1,
      per_page: filters.per_page,
    });
  };

  const hasActiveFilters = Boolean(
    filters.q ||
    filters.date_from ||
    filters.date_to ||
    filters.source_cash_account_id ||
    filters.destination_cash_account_id ||
    filters.is_reversal !== undefined
  );

  const getStatusBadge = (tx: CashTransfer) => {
    if (tx.is_reversed || tx.reversal) {
      return <Badge variant="rose" size="sm">Dibalikkan</Badge>;
    }
    if (tx.is_reversal) {
      return <Badge variant="amber" size="sm">Pembalikan</Badge>;
    }
    return <Badge variant="emerald" size="sm">Normal</Badge>;
  };

  return (
    <div className="space-y-4">
      {/* Filter Bar */}
      <div className="p-3.5 rounded-xl bg-slate-900/60 border border-slate-800 flex flex-wrap items-center justify-between gap-3 text-xs">
        <div className="flex flex-wrap items-center gap-2.5 flex-1 min-w-[280px]">
          {/* Search */}
          <div className="relative min-w-[180px] flex-1">
            <span className="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500">
              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </span>
            <input
              type="text"
              placeholder="Cari deskripsi / referensi..."
              value={filters.q || ''}
              onChange={handleSearchChange}
              className="w-full pl-8 pr-3 py-1.5 rounded-lg bg-slate-950/80 border border-slate-700/80 text-slate-100 placeholder-slate-500 text-xs focus:ring-1 focus:ring-emerald-500 focus:outline-none"
            />
          </div>

          {/* Date range */}
          <div className="flex items-center gap-1.5">
            <input
              type="date"
              aria-label="Dari Tanggal"
              value={filters.date_from || ''}
              onChange={handleDateFromChange}
              className="px-2 py-1.5 rounded-lg bg-slate-950/80 border border-slate-700/80 text-slate-100 text-xs focus:ring-1 focus:ring-emerald-500 focus:outline-none"
            />
            <span className="text-slate-500">&ndash;</span>
            <input
              type="date"
              aria-label="Sampai Tanggal"
              value={filters.date_to || ''}
              onChange={handleDateToChange}
              className="px-2 py-1.5 rounded-lg bg-slate-950/80 border border-slate-700/80 text-slate-100 text-xs focus:ring-1 focus:ring-emerald-500 focus:outline-none"
            />
          </div>

          {/* Source Account Filter */}
          {accounts.length > 0 && (
            <select
              aria-label="Filter Rekening Asal"
              value={filters.source_cash_account_id || ''}
              onChange={handleSourceAccountFilter}
              className="px-2.5 py-1.5 rounded-lg bg-slate-950/80 border border-slate-700/80 text-slate-200 text-xs focus:ring-1 focus:ring-emerald-500 focus:outline-none"
            >
              <option value="">Semua Akun Asal</option>
              {accounts.map((acc) => (
                <option key={acc.id} value={acc.id}>
                  Asal: {acc.name}
                </option>
              ))}
            </select>
          )}

          {/* Destination Account Filter */}
          {accounts.length > 0 && (
            <select
              aria-label="Filter Rekening Tujuan"
              value={filters.destination_cash_account_id || ''}
              onChange={handleDestinationAccountFilter}
              className="px-2.5 py-1.5 rounded-lg bg-slate-950/80 border border-slate-700/80 text-slate-200 text-xs focus:ring-1 focus:ring-emerald-500 focus:outline-none"
            >
              <option value="">Semua Akun Tujuan</option>
              {accounts.map((acc) => (
                <option key={acc.id} value={acc.id}>
                  Tujuan: {acc.name}
                </option>
              ))}
            </select>
          )}

          {/* Reversal / Status Filter */}
          <select
            aria-label="Filter Status Transfer"
            value={filters.is_reversal === undefined ? '' : String(filters.is_reversal)}
            onChange={handleReversalFilter}
            className="px-2.5 py-1.5 rounded-lg bg-slate-950/80 border border-slate-700/80 text-slate-200 text-xs focus:ring-1 focus:ring-emerald-500 focus:outline-none"
          >
            <option value="">Semua Jenis</option>
            <option value="false">Hanya Normal</option>
            <option value="true">Hanya Pembalikan</option>
          </select>
        </div>

        {hasActiveFilters && (
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={handleResetFilters}
            className="text-xs text-slate-400 hover:text-slate-200 shrink-0"
          >
            Reset Filter
          </Button>
        )}
      </div>

      {/* Table Container */}
      <div className="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900/40">
        <table className="w-full text-left text-xs text-slate-300">
          <thead className="bg-slate-900/90 text-[11px] font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-800">
            <tr>
              <th scope="col" className="px-4 py-3">Tanggal</th>
              <th scope="col" className="px-4 py-3">No. Referensi / Keterangan</th>
              <th scope="col" className="px-4 py-3">Rekening Asal</th>
              <th scope="col" className="px-4 py-3">Rekening Tujuan</th>
              <th scope="col" className="px-4 py-3 text-right">Nominal</th>
              <th scope="col" className="px-4 py-3 text-center">Status</th>
              <th scope="col" className="px-4 py-3">Pencatat</th>
              <th scope="col" className="px-4 py-3 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-800/60">
            {isLoading ? (
              Array.from({ length: 5 }).map((_, idx) => (
                <tr key={idx} className="animate-pulse">
                  <td className="px-4 py-3.5"><div className="h-3.5 bg-slate-800 rounded w-16" /></td>
                  <td className="px-4 py-3.5"><div className="h-3.5 bg-slate-800 rounded w-36" /></td>
                  <td className="px-4 py-3.5"><div className="h-3.5 bg-slate-800 rounded w-24" /></td>
                  <td className="px-4 py-3.5"><div className="h-3.5 bg-slate-800 rounded w-24" /></td>
                  <td className="px-4 py-3.5 text-right"><div className="h-3.5 bg-slate-800 rounded w-20 ml-auto" /></td>
                  <td className="px-4 py-3.5 text-center"><div className="h-3.5 bg-slate-800 rounded w-14 mx-auto" /></td>
                  <td className="px-4 py-3.5"><div className="h-3.5 bg-slate-800 rounded w-20" /></td>
                  <td className="px-4 py-3.5 text-center"><div className="h-3.5 bg-slate-800 rounded w-12 mx-auto" /></td>
                </tr>
              ))
            ) : transfers.length === 0 ? (
              <tr>
                <td colSpan={8} className="px-4 py-12 text-center text-slate-500">
                  <div className="flex flex-col items-center justify-center space-y-2">
                    <svg className="w-8 h-8 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                    <p className="text-sm font-medium text-slate-400">Belum ada data transfer kas</p>
                    <p className="text-xs text-slate-500 max-w-sm">
                      {hasActiveFilters
                        ? 'Tidak ada mutasi transfer kas yang sesuai dengan kriteria filter.'
                        : 'Belum ada transaksi transfer internal antar rekening kas RW atau RT.'}
                    </p>
                    {hasActiveFilters && (
                      <button
                        type="button"
                        onClick={handleResetFilters}
                        className="mt-2 text-xs text-emerald-400 hover:text-emerald-300 underline"
                      >
                        Reset semua filter
                      </button>
                    )}
                  </div>
                </td>
              </tr>
            ) : (
              transfers.map((tx) => {
                const canBeReversed = canReverse && !tx.is_reversal && !tx.is_reversed && !tx.reversal;

                return (
                  <tr
                    key={tx.id}
                    className="hover:bg-slate-800/30 transition-colors group"
                  >
                    {/* Tanggal */}
                    <td className="px-4 py-3 font-mono text-[11px] text-slate-300 whitespace-nowrap">
                      {tx.transfer_date}
                    </td>

                    {/* No. Referensi & Keterangan */}
                    <td className="px-4 py-3 max-w-[240px]">
                      <div className="font-medium text-slate-200 truncate">
                        {tx.description}
                      </div>
                      <div className="text-[11px] text-slate-500 font-mono mt-0.5 truncate">
                        {tx.reference_number || `TRF-${tx.id.slice(0, 8)}`}
                      </div>
                    </td>

                    {/* Rekening Asal */}
                    <td className="px-4 py-3 whitespace-nowrap">
                      <div className="text-slate-200 font-medium truncate max-w-[140px]">
                        {tx.source_account?.name || 'Rekening Asal'}
                      </div>
                      <span className="text-[10px] text-rose-400 font-mono">
                        {tx.source_account?.scope_type === 'RT'
                          ? `RT ${tx.source_account.rt_number || ''}`
                          : 'RW'}
                      </span>
                    </td>

                    {/* Rekening Tujuan */}
                    <td className="px-4 py-3 whitespace-nowrap">
                      <div className="text-slate-200 font-medium truncate max-w-[140px]">
                        {tx.destination_account?.name || 'Rekening Tujuan'}
                      </div>
                      <span className="text-[10px] text-emerald-400 font-mono">
                        {tx.destination_account?.scope_type === 'RT'
                          ? `RT ${tx.destination_account.rt_number || ''}`
                          : 'RW'}
                      </span>
                    </td>

                    {/* Nominal */}
                    <td className="px-4 py-3 text-right font-mono font-semibold text-slate-100 whitespace-nowrap">
                      {formatRupiah(tx.amount)}
                    </td>

                    {/* Status */}
                    <td className="px-4 py-3 text-center whitespace-nowrap">
                      {getStatusBadge(tx)}
                    </td>

                    {/* Pencatat */}
                    <td className="px-4 py-3 text-[11px] text-slate-400 whitespace-nowrap truncate max-w-[120px]">
                      {tx.recorder?.name || 'Admin'}
                    </td>

                    {/* Aksi */}
                    <td className="px-4 py-3 text-center whitespace-nowrap">
                      <div className="flex items-center justify-center gap-1.5">
                        <Button
                          type="button"
                          variant="secondary"
                          size="sm"
                          onClick={() => onViewDetail(tx)}
                          className="text-[11px] px-2 py-1 h-auto"
                        >
                          Detail
                        </Button>
                        {canBeReversed && onReverseClick && (
                          <Button
                            type="button"
                            variant="danger"
                            size="sm"
                            onClick={() => onReverseClick(tx)}
                            className="text-[11px] px-2 py-1 h-auto"
                          >
                            Batalkan
                          </Button>
                        )}
                      </div>
                    </td>
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>

      {/* Pagination Bar */}
      {pagination && pagination.total > 0 && (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-400 pt-2">
          <div>
            Menampilkan{' '}
            <span className="text-slate-200 font-medium">
              {(pagination.current_page - 1) * pagination.per_page + 1}
            </span>{' '}
            hingga{' '}
            <span className="text-slate-200 font-medium">
              {Math.min(pagination.current_page * pagination.per_page, pagination.total)}
            </span>{' '}
            dari <span className="text-slate-200 font-medium">{pagination.total}</span> data transfer
          </div>

          <div className="flex items-center gap-1.5">
            <Button
              type="button"
              variant="secondary"
              size="sm"
              disabled={pagination.current_page <= 1 || isLoading}
              onClick={() => onPageChange(pagination.current_page - 1)}
              className="text-xs"
            >
              Sebelumnya
            </Button>
            <span className="px-2 text-slate-300">
              Hal {pagination.current_page} dari {pagination.last_page}
            </span>
            <Button
              type="button"
              variant="secondary"
              size="sm"
              disabled={pagination.current_page >= pagination.last_page || isLoading}
              onClick={() => onPageChange(pagination.current_page + 1)}
              className="text-xs"
            >
              Selanjutnya
            </Button>
          </div>
        </div>
      )}
    </div>
  );
};
