import React, { useState } from 'react';
import { Badge } from '../../../components/ui/Badge.tsx';
import type { CategoryBreakdownData } from '../../../types/financeReports.ts';
import { formatRupiah } from '../utils/currency.ts';

interface CategoryBreakdownCardProps {
  data?: CategoryBreakdownData | null;
  isLoading?: boolean;
}

export const CategoryBreakdownCard: React.FC<CategoryBreakdownCardProps> = ({
  data,
  isLoading = false,
}) => {
  const [filterDirection, setFilterDirection] = useState<'ALL' | 'INCOME' | 'EXPENSE'>('ALL');

  if (isLoading) {
    return (
      <div className="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
        <div className="h-6 w-48 bg-slate-800 rounded animate-pulse" />
        <div className="space-y-3">
          {[1, 2, 3].map((i) => (
            <div key={i} className="h-14 bg-slate-950/60 rounded-xl animate-pulse" />
          ))}
        </div>
      </div>
    );
  }

  const categories = data?.categories ?? [];
  const summary = data?.summary ?? { total_effective_income: 0, total_effective_expense: 0 };

  const filtered = categories.filter((c) => {
    if (filterDirection === 'ALL') return true;
    return c.type === filterDirection;
  });

  return (
    <div className="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
      {/* Header and Direction Filter Tabs */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h3 className="text-sm font-semibold text-slate-100 flex items-center gap-2">
            <span>Distribusi Pos Anggaran (Kategori)</span>
            <span className="text-[11px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 font-normal">
              {filtered.length} Kategori
            </span>
          </h3>
          <p className="text-xs text-slate-400 mt-0.5">
            Rincian realisasi per pos anggaran, nominal efektif, dan persentase kontribusi
          </p>
        </div>

        {/* Direction Filter */}
        <div className="flex rounded-lg bg-slate-950 border border-slate-800 p-0.5 text-xs">
          <button
            type="button"
            onClick={() => setFilterDirection('ALL')}
            className={`px-2.5 py-1 rounded font-medium transition-colors ${
              filterDirection === 'ALL'
                ? 'bg-slate-800 text-white shadow-sm'
                : 'text-slate-400 hover:text-slate-200'
            }`}
          >
            Semua
          </button>
          <button
            type="button"
            onClick={() => setFilterDirection('INCOME')}
            className={`px-2.5 py-1 rounded font-medium transition-colors ${
              filterDirection === 'INCOME'
                ? 'bg-emerald-900/70 text-emerald-200 shadow-sm'
                : 'text-slate-400 hover:text-slate-200'
            }`}
          >
            Pemasukan
          </button>
          <button
            type="button"
            onClick={() => setFilterDirection('EXPENSE')}
            className={`px-2.5 py-1 rounded font-medium transition-colors ${
              filterDirection === 'EXPENSE'
                ? 'bg-rose-900/70 text-rose-200 shadow-sm'
                : 'text-slate-400 hover:text-slate-200'
            }`}
          >
            Pengeluaran
          </button>
        </div>
      </div>

      {/* Direction Aggregate Summary Badges */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
        <div className="px-3.5 py-2 rounded-xl bg-emerald-950/30 border border-emerald-900/40 flex items-center justify-between text-xs">
          <span className="text-emerald-400 font-medium">Total Pos Pemasukan Efektif:</span>
          <span className="font-mono font-bold text-emerald-300">
            {formatRupiah(summary.total_effective_income)}
          </span>
        </div>
        <div className="px-3.5 py-2 rounded-xl bg-rose-950/30 border border-rose-900/40 flex items-center justify-between text-xs">
          <span className="text-rose-400 font-medium">Total Pos Pengeluaran Efektif:</span>
          <span className="font-mono font-bold text-rose-300">
            {formatRupiah(summary.total_effective_expense)}
          </span>
        </div>
      </div>

      {/* Category List */}
      {filtered.length === 0 ? (
        <div className="py-8 text-center text-xs text-slate-500 border border-dashed border-slate-800 rounded-xl">
          Tidak ada pos anggaran yang tercatat pada periode ini.
        </div>
      ) : (
        <div className="space-y-3">
          {filtered.map((cat) => {
            const isIncome = cat.type === 'INCOME';
            const barColor = isIncome ? 'bg-emerald-500' : 'bg-rose-500';

            return (
              <div
                key={cat.category_id}
                className="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800/80 hover:border-slate-700/80 transition-colors space-y-2"
              >
                <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
                  <div className="flex items-center gap-2 min-w-0">
                    <span
                      className={`w-2 h-2 rounded-full flex-shrink-0 ${
                        isIncome ? 'bg-emerald-400' : 'bg-rose-400'
                      }`}
                    />
                    <span className="font-semibold text-slate-200 truncate">
                      {cat.name}
                    </span>
                    {cat.code && (
                      <span className="font-mono text-[10px] text-slate-500">
                        ({cat.code})
                      </span>
                    )}
                    {!cat.is_active && (
                      <Badge variant="amber" size="sm">
                        Arsip
                      </Badge>
                    )}
                  </div>

                  <div className="flex items-center gap-3 font-mono">
                    <span className="text-slate-400 text-[11px]">
                      {cat.transaction_count} transaksi
                    </span>
                    <span
                      className={`font-bold ${
                        isIncome ? 'text-emerald-300' : 'text-rose-300'
                      }`}
                    >
                      {formatRupiah(cat.effective_amount)}
                    </span>
                    <span className="text-[11px] font-semibold text-slate-400 w-12 text-right">
                      {cat.percentage}%
                    </span>
                  </div>
                </div>

                {/* Contribution Progress Bar */}
                <div className="w-full bg-slate-800/80 rounded-full h-1.5 overflow-hidden">
                  <div
                    className={`h-full rounded-full transition-all duration-300 ${barColor}`}
                    style={{ width: `${Math.min(100, Math.max(0, cat.percentage))}%` }}
                  />
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
};
