import React from 'react';
import { Link } from 'react-router-dom';
import { Badge } from '../../../components/ui/Badge.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import type { PaginationMeta } from '../../../lib/api/types.ts';
import type { FinancialTransaction, FinancialTransactionQueryParams } from '../../../types/finance.ts';
import { formatRupiah } from '../utils/currency.ts';

interface TransactionLedgerTableProps {
  transactions: FinancialTransaction[];
  pagination?: PaginationMeta;
  isLoading?: boolean;
  filters: FinancialTransactionQueryParams;
  onFilterChange: (filters: FinancialTransactionQueryParams) => void;
  onPageChange: (page: number) => void;
  onReverseClick?: (transaction: FinancialTransaction) => void;
  canReverse?: boolean;
  hideAccountColumn?: boolean;
}

export const TransactionLedgerTable: React.FC<TransactionLedgerTableProps> = ({
  transactions,
  pagination,
  isLoading = false,
  filters,
  onFilterChange,
  onPageChange,
  onReverseClick,
  canReverse = false,
  hideAccountColumn = false,
}) => {
  const handleTypeFilter = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const val = e.target.value as 'INCOME' | 'EXPENSE' | '';
    onFilterChange({
      ...filters,
      type: val ? val : undefined,
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

  const handleResetFilters = () => {
    onFilterChange({
      cash_account_id: filters.cash_account_id,
      page: 1,
      per_page: filters.per_page,
    });
  };

  const hasActiveFilters = Boolean(
    filters.type ||
    filters.is_reversal !== undefined ||
    filters.date_from ||
    filters.date_to ||
    filters.q
  );

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
              placeholder="Cari deskripsi / no. ref..."
              value={filters.q || ''}
              onChange={handleSearchChange}
              className="w-full pl-8 pr-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs placeholder-slate-500 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
          </div>

          {/* Type Filter */}
          <select
            value={filters.type || ''}
            onChange={handleTypeFilter}
            className="px-2.5 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-200 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
          >
            <option value="">Semua Arah</option>
            <option value="INCOME">Pemasukan (+)</option>
            <option value="EXPENSE">Pengeluaran (-)</option>
          </select>

          {/* Reversal Filter */}
          <select
            value={filters.is_reversal === undefined ? '' : String(filters.is_reversal)}
            onChange={handleReversalFilter}
            className="px-2.5 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-200 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
          >
            <option value="">Semua Status</option>
            <option value="false">Transaksi Reguler</option>
            <option value="true">Entri Reversal</option>
          </select>

          {/* Date Range */}
          <div className="flex items-center gap-1 text-slate-400">
            <input
              type="date"
              aria-label="Dari tanggal"
              value={filters.date_from || ''}
              onChange={handleDateFromChange}
              className="px-2 py-1 rounded bg-slate-800 border border-slate-700 text-slate-200 text-xs focus:ring-1 focus:ring-emerald-500"
            />
            <span>–</span>
            <input
              type="date"
              aria-label="Sampai tanggal"
              value={filters.date_to || ''}
              onChange={handleDateToChange}
              className="px-2 py-1 rounded bg-slate-800 border border-slate-700 text-slate-200 text-xs focus:ring-1 focus:ring-emerald-500"
            />
          </div>
        </div>

        {hasActiveFilters && (
          <button
            type="button"
            onClick={handleResetFilters}
            className="text-xs text-rose-400 hover:text-rose-300 font-medium underline transition-colors"
          >
            Reset Filter
          </button>
        )}
      </div>

      {/* Table Container */}
      <div className="rounded-xl border border-slate-800 overflow-hidden bg-slate-900/50">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse text-xs">
            <thead>
              <tr className="border-b border-slate-800 bg-slate-950/60 text-slate-400 font-semibold uppercase tracking-wider text-[10px]">
                <th className="py-3 px-4">Tanggal</th>
                {!hideAccountColumn && <th className="py-3 px-4">Akun Kas</th>}
                <th className="py-3 px-4">Kategori Pos</th>
                <th className="py-3 px-4">Deskripsi / Ref</th>
                <th className="py-3 px-4">Arah</th>
                <th className="py-3 px-4 text-right">Nominal (IDR)</th>
                <th className="py-3 px-4">Status Ledger</th>
                <th className="py-3 px-4 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800/60">
              {isLoading ? (
                <tr>
                  <td colSpan={hideAccountColumn ? 7 : 8} className="py-12 text-center">
                    <div className="flex items-center justify-center gap-2 text-slate-400">
                      <svg className="animate-spin h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                      </svg>
                      <span>Memuat buku kas ledger...</span>
                    </div>
                  </td>
                </tr>
              ) : transactions.length === 0 ? (
                <tr>
                  <td colSpan={hideAccountColumn ? 7 : 8} className="py-12 text-center text-slate-400">
                    <div className="max-w-xs mx-auto space-y-2">
                      <div className="w-10 h-10 rounded-full bg-slate-800 mx-auto flex items-center justify-center text-slate-500">
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                      </div>
                      <p className="font-medium text-slate-300">Belum ada transaksi ditemukan</p>
                      <p className="text-[11px] text-slate-500">
                        {hasActiveFilters
                          ? 'Coba sesuaikan filter pencarian untuk melihat data lain.'
                          : 'Belum ada mutasi keuangan tercatat pada akun ini.'}
                      </p>
                    </div>
                  </td>
                </tr>
              ) : (
                transactions.map((tx) => {
                  const isIncome = tx.type === 'INCOME';
                  const isReversalEntry = tx.is_reversal;
                  const revId = tx.reversal?.id || tx.reversal_transaction_id;
                  const hasBeenReversed = Boolean(tx.is_reversed || revId);

                  return (
                    <tr
                      key={tx.id}
                      className={`hover:bg-slate-800/30 transition-colors ${
                        hasBeenReversed ? 'opacity-60 bg-slate-950/20' : ''
                      }`}
                    >
                      {/* Date */}
                      <td className="py-3 px-4 font-mono text-slate-300 whitespace-nowrap">
                        {tx.transaction_date}
                      </td>

                      {/* Account Name */}
                      {!hideAccountColumn && (
                        <td className="py-3 px-4 whitespace-nowrap">
                          <span className="font-medium text-slate-200">
                            {tx.cash_account?.name || tx.account?.name || '-'}
                          </span>
                        </td>
                      )}

                      {/* Category */}
                      <td className="py-3 px-4 whitespace-nowrap">
                        <span className="text-slate-300 font-medium">
                          {tx.category?.name || '-'}
                        </span>
                      </td>

                      {/* Description & Ref */}
                      <td className="py-3 px-4 max-w-xs">
                        <div className="text-slate-200 line-clamp-1">{tx.description}</div>
                        {tx.reference_number && (
                          <div className="text-[10px] font-mono text-slate-400">
                            Ref: {tx.reference_number}
                          </div>
                        )}
                      </td>

                      {/* Direction Badge */}
                      <td className="py-3 px-4 whitespace-nowrap">
                        <Badge variant={isIncome ? 'emerald' : 'rose'} size="sm">
                          {isIncome ? 'INCOME' : 'EXPENSE'}
                        </Badge>
                      </td>

                      {/* Amount */}
                      <td className="py-3 px-4 text-right font-mono font-bold whitespace-nowrap">
                        <span
                          className={
                            isReversalEntry
                              ? 'text-amber-400'
                              : isIncome
                              ? 'text-emerald-400'
                              : 'text-rose-400'
                          }
                        >
                          {isIncome ? '+' : '-'}
                          {formatRupiah(tx.amount)}
                        </span>
                      </td>

                      {/* Status / Reversal Linkage */}
                      <td className="py-3 px-4 whitespace-nowrap">
                        {isReversalEntry ? (
                          <div className="flex flex-col gap-0.5">
                            <Badge variant="amber" size="sm">
                              REVERSAL ENTRY
                            </Badge>
                            {tx.original_transaction_id && (
                              <Link
                                to={`/app/finance/transactions/${tx.original_transaction_id}`}
                                className="text-[10px] text-amber-400/80 hover:underline font-mono"
                              >
                                Asli: #{tx.original_transaction_id.slice(0, 8)}…
                              </Link>
                            )}
                          </div>
                        ) : hasBeenReversed ? (
                          <div className="flex flex-col gap-0.5">
                            <Badge variant="slate" size="sm">
                              DIBATALKAN
                            </Badge>
                            {revId && (
                              <Link
                                to={`/app/finance/transactions/${revId}`}
                                className="text-[10px] text-slate-400 hover:underline font-mono"
                              >
                                Rev: #{revId.slice(0, 8)}…
                              </Link>
                            )}
                          </div>
                        ) : (
                          <Badge variant="slate" size="sm">
                            POSTED
                          </Badge>
                        )}
                      </td>

                      {/* Actions */}
                      <td className="py-3 px-4 text-right whitespace-nowrap">
                        <div className="flex items-center justify-end gap-1.5">
                          <Link
                            to={`/app/finance/transactions/${tx.id}`}
                            className="text-xs text-slate-300 hover:text-emerald-400 font-medium px-2 py-1 rounded bg-slate-800 hover:bg-slate-700 transition-colors"
                          >
                            Detail
                          </Link>
                          {canReverse && !isReversalEntry && !hasBeenReversed && onReverseClick && (
                            <Button
                              type="button"
                              variant="outline"
                              size="sm"
                              onClick={() => onReverseClick(tx)}
                              className="text-xs px-2 py-1 text-rose-400 border-rose-900/40 hover:bg-rose-950/40"
                              title="Batalkan transaksi ini dengan entri pembalik"
                            >
                              Reverse
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
      </div>

      {/* Pagination */}
      {pagination && pagination.last_page > 1 && (
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-2 text-xs text-slate-400">
          <div>
            Menampilkan {((pagination.current_page - 1) * pagination.per_page) + 1}–
            {Math.min(pagination.current_page * pagination.per_page, pagination.total)} dari{' '}
            <strong className="text-slate-200">{pagination.total.toLocaleString('id-ID')}</strong> transaksi
          </div>
          <div className="flex items-center gap-1.5">
            <button
              type="button"
              onClick={() => onPageChange(pagination.current_page - 1)}
              disabled={pagination.current_page <= 1}
              className="px-2.5 py-1 rounded bg-slate-800 border border-slate-700 text-slate-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-700 transition-colors"
            >
              ← Sebelumnya
            </button>
            <span className="px-2 text-slate-400">
              Hal. {pagination.current_page} / {pagination.last_page}
            </span>
            <button
              type="button"
              onClick={() => onPageChange(pagination.current_page + 1)}
              disabled={pagination.current_page >= pagination.last_page}
              className="px-2.5 py-1 rounded bg-slate-800 border border-slate-700 text-slate-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-700 transition-colors"
            >
              Berikutnya →
            </button>
          </div>
        </div>
      )}
    </div>
  );
};
