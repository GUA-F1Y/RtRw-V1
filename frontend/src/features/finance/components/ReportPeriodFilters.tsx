import React from 'react';
import { Button } from '../../../components/ui/Button.tsx';
import type { CashAccount } from '../../../types/finance.ts';
import type { ReportPeriodFilter } from '../../../types/financeReports.ts';
import type { RtUnit } from '../../../types/organization.ts';

interface ReportPeriodFiltersProps {
  filters: ReportPeriodFilter;
  onChange: (newFilters: ReportPeriodFilter) => void;
  onExportCsv: () => void;
  isExporting?: boolean;
  accounts?: CashAccount[];
  rts?: RtUnit[];
  canFilterRt?: boolean;
}

export const ReportPeriodFilters: React.FC<ReportPeriodFiltersProps> = ({
  filters,
  onChange,
  onExportCsv,
  isExporting = false,
  accounts = [],
  rts = [],
  canFilterRt = false,
}) => {
  // Preset date ranges
  const applyPreset = (preset: 'this_month' | 'last_month' | 'this_year' | 'last_30_days') => {
    const today = new Date();
    let from = '';
    let to = today.toISOString().split('T')[0];

    if (preset === 'this_month') {
      const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
      const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
      from = firstDay.toISOString().split('T')[0];
      to = lastDay.toISOString().split('T')[0];
    } else if (preset === 'last_month') {
      const firstDay = new Date(today.getFullYear(), today.getMonth() - 1, 1);
      const lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
      from = firstDay.toISOString().split('T')[0];
      to = lastDay.toISOString().split('T')[0];
    } else if (preset === 'this_year') {
      from = `${today.getFullYear()}-01-01`;
      to = `${today.getFullYear()}-12-31`;
    } else if (preset === 'last_30_days') {
      const past30 = new Date();
      past30.setDate(past30.getDate() - 30);
      from = past30.toISOString().split('T')[0];
    }

    onChange({
      ...filters,
      date_from: from,
      date_to: to,
    });
  };

  const handleReset = () => {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);

    onChange({
      date_from: firstDay.toISOString().split('T')[0],
      date_to: lastDay.toISOString().split('T')[0],
      cash_account_id: undefined,
      rt_id: undefined,
      interval: 'daily',
    });
  };

  return (
    <div className="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
      {/* Top Header: Quick Presets & Actions */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <span className="text-xs font-medium text-slate-400">Periode Cepat:</span>
          <div className="flex flex-wrap gap-1.5">
            <button
              type="button"
              onClick={() => applyPreset('this_month')}
              className="px-2.5 py-1 text-xs rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition-colors"
            >
              Bulan Ini
            </button>
            <button
              type="button"
              onClick={() => applyPreset('last_month')}
              className="px-2.5 py-1 text-xs rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition-colors"
            >
              Bulan Lalu
            </button>
            <button
              type="button"
              onClick={() => applyPreset('last_30_days')}
              className="px-2.5 py-1 text-xs rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition-colors"
            >
              30 Hari
            </button>
            <button
              type="button"
              onClick={() => applyPreset('this_year')}
              className="px-2.5 py-1 text-xs rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition-colors"
            >
              Tahun Ini
            </button>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={handleReset}
            className="px-2.5 py-1 text-xs rounded-lg text-slate-400 hover:text-slate-200 hover:bg-slate-800 transition-colors"
          >
            Reset
          </button>

          <Button
            variant="secondary"
            size="sm"
            onClick={onExportCsv}
            disabled={isExporting}
            className="text-xs flex items-center gap-1.5 bg-emerald-950/60 hover:bg-emerald-900/60 border border-emerald-800/60 text-emerald-300"
          >
            {isExporting ? (
              <>
                <svg className="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                </svg>
                <span>Mengekspor...</span>
              </>
            ) : (
              <>
                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Unduh CSV</span>
              </>
            )}
          </Button>
        </div>
      </div>

      {/* Main Filter Controls Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3 pt-2 border-t border-slate-800/80">
        {/* Tanggal Mulai */}
        <div>
          <label className="block text-xs font-medium text-slate-400 mb-1">
            Dari Tanggal
          </label>
          <input
            type="date"
            value={filters.date_from || ''}
            onChange={(e) => onChange({ ...filters, date_from: e.target.value })}
            className="w-full px-3 py-1.5 text-xs bg-slate-950 border border-slate-800 rounded-lg text-slate-200 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
          />
        </div>

        {/* Tanggal Akhir */}
        <div>
          <label className="block text-xs font-medium text-slate-400 mb-1">
            Sampai Tanggal
          </label>
          <input
            type="date"
            value={filters.date_to || ''}
            onChange={(e) => onChange({ ...filters, date_to: e.target.value })}
            className="w-full px-3 py-1.5 text-xs bg-slate-950 border border-slate-800 rounded-lg text-slate-200 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
          />
        </div>

        {/* Akun Kas */}
        <div>
          <label className="block text-xs font-medium text-slate-400 mb-1">
            Akun Kas
          </label>
          <select
            value={filters.cash_account_id || ''}
            onChange={(e) => onChange({ ...filters, cash_account_id: e.target.value || undefined })}
            className="w-full px-3 py-1.5 text-xs bg-slate-950 border border-slate-800 rounded-lg text-slate-200 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
          >
            <option value="">Semua Akun Kas</option>
            {accounts.map((acc) => (
              <option key={acc.id} value={acc.id}>
                {acc.name} {acc.rt_id ? '(RT)' : '(RW)'}
              </option>
            ))}
          </select>
        </div>

        {/* RT Filter (If authorized) */}
        {canFilterRt && (
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1">
              Lingkup Unit RT
            </label>
            <select
              value={filters.rt_id || ''}
              onChange={(e) => onChange({ ...filters, rt_id: e.target.value || undefined })}
              className="w-full px-3 py-1.5 text-xs bg-slate-950 border border-slate-800 rounded-lg text-slate-200 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
            >
              <option value="">Semua Lingkup (RW & RT)</option>
              <option value="null">Hanya Kas Tingkat RW</option>
              {rts.map((rt) => (
                <option key={rt.id} value={rt.id}>
                  RT {String(rt.number).padStart(3, '0')}
                </option>
              ))}
            </select>
          </div>
        )}

        {/* Agregasi Interval */}
        <div>
          <label className="block text-xs font-medium text-slate-400 mb-1">
            Interval Grafik
          </label>
          <div className="flex rounded-lg bg-slate-950 border border-slate-800 p-0.5">
            <button
              type="button"
              onClick={() => onChange({ ...filters, interval: 'daily' })}
              className={`flex-1 py-1 text-xs rounded font-medium transition-colors ${
                (filters.interval || 'daily') === 'daily'
                  ? 'bg-slate-800 text-white shadow-sm'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              Harian
            </button>
            <button
              type="button"
              onClick={() => onChange({ ...filters, interval: 'monthly' })}
              className={`flex-1 py-1 text-xs rounded font-medium transition-colors ${
                filters.interval === 'monthly'
                  ? 'bg-slate-800 text-white shadow-sm'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              Bulanan
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
