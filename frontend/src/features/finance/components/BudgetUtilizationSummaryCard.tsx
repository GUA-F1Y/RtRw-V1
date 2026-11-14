import React from 'react';
import type { BudgetPlanSummary } from '../../../types/finance.ts';
import { formatRupiah } from '../utils/currency.ts';

interface BudgetUtilizationSummaryCardProps {
  summary?: BudgetPlanSummary | null | {
    total_planned: number;
    total_actual: number;
    total_commitments: number;
    total_remaining: number;
    total_variance: number;
    overall_utilization_percentage: number;
    plans_count?: number;
    lines_count?: number;
  };
  isLoading?: boolean;
  title?: string;
  subtitle?: string;
}

export const BudgetUtilizationSummaryCard: React.FC<BudgetUtilizationSummaryCardProps> = ({
  summary,
  isLoading = false,
  title = 'Ringkasan Pagu & Realisasi Anggaran',
  subtitle,
}) => {
  if (isLoading || !summary) {
    return (
      <div className="bg-slate-900/80 border border-slate-800 rounded-xl p-5 text-center text-xs text-slate-400">
        {isLoading ? 'Memuat ringkasan pagu dan realisasi anggaran...' : 'Belum ada data pagu anggaran.'}
      </div>
    );
  }

  const percentage = Math.min(100, Math.max(0, summary.overall_utilization_percentage || 0));

  let barColor = 'bg-emerald-500';
  let badgeColor = 'text-emerald-400 border-emerald-500/30 bg-emerald-500/10';
  if (percentage >= 90) {
    barColor = 'bg-rose-500';
    badgeColor = 'text-rose-400 border-rose-500/30 bg-rose-500/10';
  } else if (percentage >= 75) {
    barColor = 'bg-amber-500';
    badgeColor = 'text-amber-400 border-amber-500/30 bg-amber-500/10';
  }

  return (
    <div className="bg-slate-900/80 border border-slate-800 rounded-xl p-5 shadow-lg relative overflow-hidden backdrop-blur-sm">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
        <div>
          <h2 className="text-sm font-semibold text-white tracking-wide uppercase flex items-center gap-2">
            <svg className="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            {title}
          </h2>
          {subtitle && <p className="text-xs text-slate-400 mt-0.5">{subtitle}</p>}
        </div>
        <div className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border ${badgeColor}`}>
          <span>Realisasi:</span>
          <span>{summary.overall_utilization_percentage.toFixed(1)}%</span>
        </div>
      </div>

      {/* Progress Bar */}
      <div className="w-full bg-slate-800 rounded-full h-2.5 mb-5 overflow-hidden">
        <div
          className={`h-2.5 rounded-full transition-all duration-500 ${barColor}`}
          style={{ width: `${percentage}%` }}
        />
      </div>

      {/* Metric Cards Grid */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div className="bg-slate-800/60 border border-slate-700/60 rounded-lg p-3">
          <p className="text-[11px] font-medium text-slate-400">Total Pagu (Planned)</p>
          <p className="text-base font-bold text-white mt-1">
            {formatRupiah(summary.total_planned)}
          </p>
        </div>

        <div className="bg-slate-800/60 border border-slate-700/60 rounded-lg p-3">
          <p className="text-[11px] font-medium text-slate-400">Realisasi Efektif (Actual)</p>
          <p className="text-base font-bold text-emerald-400 mt-1">
            {formatRupiah(summary.total_actual)}
          </p>
        </div>

        <div className="bg-slate-800/60 border border-slate-700/60 rounded-lg p-3">
          <p className="text-[11px] font-medium text-slate-400">Komitmen Berjalan (Approved)</p>
          <p className="text-base font-bold text-cyan-400 mt-1">
            {formatRupiah(summary.total_commitments)}
          </p>
        </div>

        <div className="bg-slate-800/60 border border-slate-700/60 rounded-lg p-3">
          <p className="text-[11px] font-medium text-slate-400">Sisa Pagu Tersedia</p>
          <p className={`text-base font-bold mt-1 ${summary.total_remaining < 0 ? 'text-rose-400' : 'text-slate-200'}`}>
            {formatRupiah(summary.total_remaining)}
          </p>
        </div>
      </div>
    </div>
  );
};
