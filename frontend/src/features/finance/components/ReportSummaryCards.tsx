import React from 'react';
import type { ReportSummaryData } from '../../../types/financeReports.ts';
import { formatRupiah } from '../utils/currency.ts';

interface ReportSummaryCardsProps {
  summary?: ReportSummaryData | null;
  isLoading?: boolean;
}

export const ReportSummaryCards: React.FC<ReportSummaryCardsProps> = ({
  summary,
  isLoading = false,
}) => {
  if (isLoading) {
    return (
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        {[1, 2, 3, 4, 5].map((i) => (
          <div
            key={i}
            className="h-28 bg-slate-900/60 border border-slate-800 rounded-xl p-4 animate-pulse"
          />
        ))}
      </div>
    );
  }

  const s = summary || {
    period: { date_from: '-', date_to: '-' },
    opening_balance: 0,
    posted_income: 0,
    reversed_income: 0,
    effective_income: 0,
    posted_expense: 0,
    reversed_expense: 0,
    effective_expense: 0,
    net_movement: 0,
    closing_balance: 0,
    total_transactions_count: 0,
    reversed_transactions_count: 0,
    effective_transactions_count: 0,
    cash_account_count: 0,
  };

  const isNetPositive = s.net_movement >= 0;

  return (
    <div className="space-y-3">
      {/* Primary 5-KPI Cards Row */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        {/* 1. Saldo Awal Periode */}
        <div className="bg-slate-900/70 border border-slate-800 rounded-xl p-4 shadow-sm flex flex-col justify-between">
          <div className="flex items-center justify-between text-xs text-slate-400 font-medium">
            <span>Saldo Awal Periode</span>
            <span className="p-1 rounded-md bg-slate-800 text-slate-300">
              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </span>
          </div>
          <div className="my-2 text-xl font-bold text-white tracking-tight">
            {formatRupiah(s.opening_balance)}
          </div>
          <div className="text-[11px] text-slate-500 truncate">
            Posisi sebelum {s.period.date_from}
          </div>
        </div>

        {/* 2. Pemasukan Efektif */}
        <div className="bg-slate-900/70 border border-emerald-900/40 rounded-xl p-4 shadow-sm flex flex-col justify-between">
          <div className="flex items-center justify-between text-xs text-emerald-400 font-medium">
            <span>Pemasukan Efektif</span>
            <span className="p-1 rounded-md bg-emerald-950 text-emerald-400 border border-emerald-800/40">
              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 11l5-5m0 0l5 5m-5-5v12" />
              </svg>
            </span>
          </div>
          <div className="my-2 text-xl font-bold text-emerald-300 tracking-tight">
            {formatRupiah(s.effective_income)}
          </div>
          <div className="text-[11px] text-slate-500 truncate" title={`Posted: ${formatRupiah(s.posted_income)} | Reversal: -${formatRupiah(s.reversed_income)}`}>
            {s.reversed_income > 0 ? (
              <span className="text-amber-400/90 font-medium">
                Koreksi reversal: -{formatRupiah(s.reversed_income)}
              </span>
            ) : (
              `Bruto: ${formatRupiah(s.posted_income)}`
            )}
          </div>
        </div>

        {/* 3. Pengeluaran Efektif */}
        <div className="bg-slate-900/70 border border-rose-900/40 rounded-xl p-4 shadow-sm flex flex-col justify-between">
          <div className="flex items-center justify-between text-xs text-rose-400 font-medium">
            <span>Pengeluaran Efektif</span>
            <span className="p-1 rounded-md bg-rose-950 text-rose-400 border border-rose-800/40">
              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 13l-5 5m0 0l-5-5m5 5V6" />
              </svg>
            </span>
          </div>
          <div className="my-2 text-xl font-bold text-rose-300 tracking-tight">
            {formatRupiah(s.effective_expense)}
          </div>
          <div className="text-[11px] text-slate-500 truncate" title={`Posted: ${formatRupiah(s.posted_expense)} | Reversal: -${formatRupiah(s.reversed_expense)}`}>
            {s.reversed_expense > 0 ? (
              <span className="text-amber-400/90 font-medium">
                Koreksi reversal: -{formatRupiah(s.reversed_expense)}
              </span>
            ) : (
              `Bruto: ${formatRupiah(s.posted_expense)}`
            )}
          </div>
        </div>

        {/* 4. Arus Kas Bersih (Net) */}
        <div className={`border rounded-xl p-4 shadow-sm flex flex-col justify-between ${
          isNetPositive
            ? 'bg-gradient-to-br from-emerald-950/40 to-slate-900/80 border-emerald-700/50'
            : 'bg-gradient-to-br from-rose-950/40 to-slate-900/80 border-rose-700/50'
        }`}>
          <div className="flex items-center justify-between text-xs font-medium">
            <span className={isNetPositive ? 'text-emerald-300' : 'text-rose-300'}>
              Arus Kas Bersih (Net)
            </span>
            <span className={`p-1 rounded-md text-xs font-semibold ${
              isNetPositive ? 'bg-emerald-900/60 text-emerald-200' : 'bg-rose-900/60 text-rose-200'
            }`}>
              {isNetPositive ? '+Surplus' : '-Defisit'}
            </span>
          </div>
          <div className={`my-2 text-xl font-bold tracking-tight ${
            isNetPositive ? 'text-emerald-200' : 'text-rose-200'
          }`}>
            {formatRupiah(s.net_movement)}
          </div>
          <div className="text-[11px] text-slate-400 truncate">
            Pemasukan - Pengeluaran
          </div>
        </div>

        {/* 5. Saldo Akhir Periode */}
        <div className="bg-gradient-to-br from-blue-950/40 to-slate-900/90 border border-blue-700/50 rounded-xl p-4 shadow-sm flex flex-col justify-between">
          <div className="flex items-center justify-between text-xs text-blue-300 font-medium">
            <span>Saldo Akhir Periode</span>
            <span className="p-1 rounded-md bg-blue-900/50 text-blue-300">
              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </span>
          </div>
          <div className="my-2 text-xl font-bold text-white tracking-tight">
            {formatRupiah(s.closing_balance)}
          </div>
          <div className="text-[11px] text-blue-300/80 truncate">
            Saldo per {s.period.date_to}
          </div>
        </div>
      </div>

      {/* Audit & Transaction Activity Pill Bar */}
      <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 bg-slate-900/40 border border-slate-800/60 rounded-xl text-xs text-slate-400">
        <div className="flex flex-wrap items-center gap-4">
          <div className="flex items-center gap-1.5">
            <span className="w-2 h-2 rounded-full bg-slate-500" />
            <span>Akun Terlibat: <strong className="text-slate-200">{s.cash_account_count}</strong></span>
          </div>
          <div className="flex items-center gap-1.5">
            <span className="w-2 h-2 rounded-full bg-emerald-500" />
            <span>Transaksi Efektif: <strong className="text-slate-200">{s.effective_transactions_count}</strong></span>
          </div>
          {s.reversed_transactions_count > 0 && (
            <div className="flex items-center gap-1.5">
              <span className="w-2 h-2 rounded-full bg-amber-500" />
              <span>Entri Pembalik (Reversal): <strong className="text-amber-300">{s.reversed_transactions_count}</strong></span>
            </div>
          )}
          <div className="flex items-center gap-1.5 text-slate-500">
            <span>Total Entri Ledger: <strong className="text-slate-400">{s.total_transactions_count}</strong></span>
          </div>
        </div>

        <div className="text-[11px] text-slate-500 font-mono">
          Saldo Akhir = Saldo Awal + Arus Bersih
        </div>
      </div>
    </div>
  );
};
