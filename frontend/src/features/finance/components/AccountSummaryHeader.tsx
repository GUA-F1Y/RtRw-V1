import React from 'react';
import type { AccountSummary } from '../../../types/finance.ts';
import { formatRupiah } from '../utils/currency.ts';

interface AccountSummaryHeaderProps {
  summary?: AccountSummary | null;
  isLoading?: boolean;
  title?: string;
  subtitle?: string;
}

export const AccountSummaryHeader: React.FC<AccountSummaryHeaderProps> = ({
  summary,
  isLoading = false,
  title = 'Ringkasan Keuangan Akun',
  subtitle = 'Statistik saldo dan mutasi kas riil sesuai audit ledger',
}) => {
  if (isLoading) {
    return (
      <div className="space-y-4">
        <div className="h-6 w-48 bg-slate-800 rounded animate-pulse" />
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="h-28 bg-slate-900/60 border border-slate-800 rounded-xl p-4 animate-pulse" />
          ))}
        </div>
      </div>
    );
  }

  const s = summary || {
    opening_balance: 0,
    current_balance: 0,
    total_income: 0,
    total_expense: 0,
    active_income: 0,
    active_expense: 0,
    reversed_income: 0,
    reversed_expense: 0,
    transactions_count: 0,
  };

  return (
    <div className="space-y-4">
      <div>
        <h2 className="text-base font-semibold text-slate-100">{title}</h2>
        <p className="text-xs text-slate-400 mt-0.5">{subtitle}</p>
      </div>

      {/* Primary KPI Row */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Saldo Saat Ini */}
        <div className="bg-gradient-to-br from-emerald-950/60 to-slate-900 border border-emerald-800/50 rounded-xl p-4 shadow-lg shadow-emerald-950/30">
          <div className="flex items-center justify-between text-xs text-emerald-400 font-medium">
            <span>Saldo Kas Saat Ini</span>
            <span className="p-1 rounded-md bg-emerald-900/50 text-emerald-300">
              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </span>
          </div>
          <div className="mt-2 text-2xl font-bold text-white tracking-tight">
            {formatRupiah(s.current_balance)}
          </div>
          <div className="mt-1 text-[11px] text-emerald-300/80">
            Saldo riil kas siap pakai (termasuk koreksi)
          </div>
        </div>

        {/* Saldo Awal */}
        <div className="bg-slate-900/70 border border-slate-800 rounded-xl p-4 shadow-md">
          <div className="flex items-center justify-between text-xs text-slate-400 font-medium">
            <span>Saldo Awal (Opening)</span>
            <span className="p-1 rounded-md bg-slate-800 text-slate-300">
              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </span>
          </div>
          <div className="mt-2 text-xl font-bold text-slate-200">
            {formatRupiah(s.opening_balance)}
          </div>
          <div className="mt-1 text-[11px] text-slate-500">
            Saldo pembukaan awal akun
          </div>
        </div>

        {/* Penerimaan Bersih */}
        <div className="bg-slate-900/70 border border-slate-800 rounded-xl p-4 shadow-md">
          <div className="flex items-center justify-between text-xs text-emerald-400 font-medium">
            <span>Penerimaan Bersih</span>
            <span className="p-1 rounded-md bg-emerald-950/60 text-emerald-400 border border-emerald-900/40">
              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 11l5-5m0 0l5 5m-5-5v12" />
              </svg>
            </span>
          </div>
          <div className="mt-2 text-xl font-bold text-emerald-300">
            {formatRupiah(s.active_income)}
          </div>
          <div className="mt-1 flex items-center justify-between text-[11px] text-slate-400">
            <span>Kotor: {formatRupiah(s.total_income)}</span>
            {s.reversed_income > 0 && (
              <span className="text-amber-400/90 font-mono">
                Rev: -{formatRupiah(s.reversed_income)}
              </span>
            )}
          </div>
        </div>

        {/* Pengeluaran Bersih */}
        <div className="bg-slate-900/70 border border-slate-800 rounded-xl p-4 shadow-md">
          <div className="flex items-center justify-between text-xs text-rose-400 font-medium">
            <span>Pengeluaran Bersih</span>
            <span className="p-1 rounded-md bg-rose-950/60 text-rose-400 border border-rose-900/40">
              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 13l-5 5m0 0l-5-5m5 5V6" />
              </svg>
            </span>
          </div>
          <div className="mt-2 text-xl font-bold text-rose-300">
            {formatRupiah(s.active_expense)}
          </div>
          <div className="mt-1 flex items-center justify-between text-[11px] text-slate-400">
            <span>Kotor: {formatRupiah(s.total_expense)}</span>
            {s.reversed_expense > 0 && (
              <span className="text-amber-400/90 font-mono">
                Rev: -{formatRupiah(s.reversed_expense)}
              </span>
            )}
          </div>
        </div>
      </div>

      {/* Secondary Metrics Bar */}
      <div className="bg-slate-900/50 border border-slate-800/80 rounded-lg px-4 py-2.5 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-400">
        <div className="flex items-center gap-2">
          <span className="w-2 h-2 rounded-full bg-emerald-500" />
          <span>Total Transaksi Tercatat:</span>
          <span className="font-semibold text-slate-200">{s.transactions_count} entri</span>
        </div>
        <div className="flex items-center gap-4 text-[11px]">
          <span className="text-slate-400">
            Reversal Penerimaan: <span className="font-mono text-amber-300">{formatRupiah(s.reversed_income)}</span>
          </span>
          <span className="text-slate-400">
            Reversal Pengeluaran: <span className="font-mono text-amber-300">{formatRupiah(s.reversed_expense)}</span>
          </span>
        </div>
      </div>
    </div>
  );
};
