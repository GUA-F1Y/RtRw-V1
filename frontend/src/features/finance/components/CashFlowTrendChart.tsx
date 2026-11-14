import React, { useState } from 'react';
import type { CashFlowBucket } from '../../../types/financeReports.ts';
import { formatRupiah } from '../utils/currency.ts';

interface CashFlowTrendChartProps {
  buckets: CashFlowBucket[];
  interval: 'daily' | 'monthly';
  isLoading?: boolean;
}

export const CashFlowTrendChart: React.FC<CashFlowTrendChartProps> = ({
  buckets,
  interval,
  isLoading = false,
}) => {
  const [viewMode, setViewMode] = useState<'chart' | 'table'>('chart');
  const [hoveredIndex, setHoveredIndex] = useState<number | null>(null);

  if (isLoading) {
    return (
      <div className="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
        <div className="h-6 w-48 bg-slate-800 rounded animate-pulse" />
        <div className="h-64 bg-slate-950/60 rounded-xl animate-pulse" />
      </div>
    );
  }

  // Calculate maximum value for chart scaling
  const maxVal = buckets.reduce((acc, b) => {
    return Math.max(acc, b.effective_income, b.effective_expense, Math.abs(b.net_movement));
  }, 0);

  const chartCeiling = maxVal > 0 ? maxVal * 1.15 : 100000;

  return (
    <div className="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
      {/* Chart Card Header */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h3 className="text-sm font-semibold text-slate-100 flex items-center gap-2">
            <span>Tren Arus Kas (Cash Flow)</span>
            <span className="text-[11px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 font-normal">
              Agregasi {interval === 'monthly' ? 'Bulanan' : 'Harian'}
            </span>
          </h3>
          <p className="text-xs text-slate-400 mt-0.5">
            Perbandingan pemasukan, pengeluaran, dan pergerakan kas bersih per periode
          </p>
        </div>

        {/* View Mode Toggle */}
        <div className="flex rounded-lg bg-slate-950 border border-slate-800 p-0.5 text-xs">
          <button
            type="button"
            onClick={() => setViewMode('chart')}
            className={`px-3 py-1 rounded font-medium transition-colors ${
              viewMode === 'chart'
                ? 'bg-slate-800 text-white shadow-sm'
                : 'text-slate-400 hover:text-slate-200'
            }`}
          >
            Grafik
          </button>
          <button
            type="button"
            onClick={() => setViewMode('table')}
            className={`px-3 py-1 rounded font-medium transition-colors ${
              viewMode === 'table'
                ? 'bg-slate-800 text-white shadow-sm'
                : 'text-slate-400 hover:text-slate-200'
            }`}
          >
            Tabel Data
          </button>
        </div>
      </div>

      {buckets.length === 0 ? (
        <div className="h-48 flex items-center justify-center text-xs text-slate-500 border border-dashed border-slate-800 rounded-xl">
          Tidak ada data transaksi pada rentang periode ini.
        </div>
      ) : viewMode === 'chart' ? (
        <div className="space-y-3">
          {/* Legend */}
          <div className="flex flex-wrap items-center gap-4 text-xs text-slate-400 px-1">
            <div className="flex items-center gap-1.5">
              <span className="w-3 h-3 rounded bg-emerald-500" />
              <span>Pemasukan Efektif</span>
            </div>
            <div className="flex items-center gap-1.5">
              <span className="w-3 h-3 rounded bg-rose-500" />
              <span>Pengeluaran Efektif</span>
            </div>
            <div className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-full bg-blue-400 ring-2 ring-blue-900" />
              <span>Arus Bersih (Surplus / Defisit)</span>
            </div>
          </div>

          {/* SVG & HTML Interactive Bar Chart */}
          <div className="relative pt-4 pb-2 bg-slate-950/60 border border-slate-800/80 rounded-xl px-3 overflow-x-auto">
            <div
              className="flex items-end justify-between gap-1.5 sm:gap-2 h-56 min-w-[500px]"
              onMouseLeave={() => setHoveredIndex(null)}
            >
              {buckets.map((bucket, idx) => {
                const incomeHeightPct = Math.min(100, Math.round((bucket.effective_income / chartCeiling) * 100));
                const expenseHeightPct = Math.min(100, Math.round((bucket.effective_expense / chartCeiling) * 100));
                const isHovered = hoveredIndex === idx;

                return (
                  <div
                    key={bucket.period}
                    onMouseEnter={() => setHoveredIndex(idx)}
                    className={`flex-1 flex flex-col items-center h-full justify-end relative cursor-pointer group transition-all duration-150 ${
                      isHovered ? 'scale-[1.02]' : 'opacity-90'
                    }`}
                  >
                    {/* Tooltip on Hover */}
                    {isHovered && (
                      <div className="absolute -top-12 z-20 pointer-events-none bg-slate-900 border border-slate-700 text-white rounded-lg p-2 shadow-xl text-[11px] whitespace-nowrap min-w-[150px]">
                        <div className="font-semibold text-slate-200 border-b border-slate-800 pb-1 mb-1">
                          {bucket.label} ({bucket.period})
                        </div>
                        <div className="text-emerald-400">
                          Masuk: {formatRupiah(bucket.effective_income)}
                        </div>
                        <div className="text-rose-400">
                          Keluar: {formatRupiah(bucket.effective_expense)}
                        </div>
                        <div className={`font-medium ${bucket.net_movement >= 0 ? 'text-emerald-300' : 'text-rose-300'}`}>
                          Net: {formatRupiah(bucket.net_movement)}
                        </div>
                        <div className="text-slate-500 text-[10px] mt-0.5">
                          {bucket.transaction_count} transaksi
                        </div>
                      </div>
                    )}

                    {/* Bars Container */}
                    <div className="w-full flex items-end justify-center gap-0.5 sm:gap-1 h-44 px-0.5 border-b border-slate-800">
                      {/* Income Bar */}
                      <div
                        style={{ height: `${Math.max(incomeHeightPct, 2)}%` }}
                        className={`w-full max-w-[14px] rounded-t transition-all ${
                          bucket.effective_income > 0
                            ? 'bg-emerald-500 hover:bg-emerald-400'
                            : 'bg-emerald-950/20'
                        }`}
                      />
                      {/* Expense Bar */}
                      <div
                        style={{ height: `${Math.max(expenseHeightPct, 2)}%` }}
                        className={`w-full max-w-[14px] rounded-t transition-all ${
                          bucket.effective_expense > 0
                            ? 'bg-rose-500 hover:bg-rose-400'
                            : 'bg-rose-950/20'
                        }`}
                      />
                    </div>

                    {/* X-axis Label */}
                    <div className="mt-1.5 text-[10px] text-slate-400 truncate max-w-[42px] text-center select-none">
                      {bucket.label}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      ) : (
        /* Accessible Table Equivalent */
        <div className="overflow-x-auto border border-slate-800 rounded-xl">
          <table className="w-full text-left text-xs text-slate-300">
            <thead className="bg-slate-950 text-slate-400 uppercase tracking-wider text-[10px] border-b border-slate-800">
              <tr>
                <th scope="col" className="px-3.5 py-2.5">Periode</th>
                <th scope="col" className="px-3.5 py-2.5 text-right">Pemasukan Efektif</th>
                <th scope="col" className="px-3.5 py-2.5 text-right">Pengeluaran Efektif</th>
                <th scope="col" className="px-3.5 py-2.5 text-right">Arus Bersih (Net)</th>
                <th scope="col" className="px-3.5 py-2.5 text-center">Transaksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800/60 font-mono">
              {buckets.map((b) => (
                <tr key={b.period} className="hover:bg-slate-800/30 transition-colors">
                  <td className="px-3.5 py-2 font-sans text-slate-200">
                    <span className="font-medium">{b.label}</span>
                    <span className="text-[10px] text-slate-500 ml-1.5">({b.period})</span>
                  </td>
                  <td className="px-3.5 py-2 text-right text-emerald-400">
                    {formatRupiah(b.effective_income)}
                  </td>
                  <td className="px-3.5 py-2 text-right text-rose-400">
                    {formatRupiah(b.effective_expense)}
                  </td>
                  <td className={`px-3.5 py-2 text-right font-semibold ${
                    b.net_movement >= 0 ? 'text-emerald-300' : 'text-rose-300'
                  }`}>
                    {formatRupiah(b.net_movement)}
                  </td>
                  <td className="px-3.5 py-2 text-center text-slate-400">
                    {b.transaction_count}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
};
