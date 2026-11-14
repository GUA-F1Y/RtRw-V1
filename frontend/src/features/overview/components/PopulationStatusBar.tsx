import React from 'react';
import type { OverviewPopulationMetrics } from '../../../types/overview.ts';

interface PopulationStatusBarProps {
  population: OverviewPopulationMetrics;
  loading?: boolean;
}

interface Segment {
  key: keyof OverviewPopulationMetrics;
  label: string;
  color: string;
  bgColor: string;
  textColor: string;
}

const SEGMENTS: Segment[] = [
  {
    key: 'active',
    label: 'Aktif',
    color: 'bg-emerald-500',
    bgColor: 'bg-emerald-950/40',
    textColor: 'text-emerald-400',
  },
  {
    key: 'prospective',
    label: 'Calon',
    color: 'bg-blue-500',
    bgColor: 'bg-blue-950/40',
    textColor: 'text-blue-400',
  },
  {
    key: 'inactive',
    label: 'Tidak Aktif',
    color: 'bg-amber-500',
    bgColor: 'bg-amber-950/40',
    textColor: 'text-amber-400',
  },
  {
    key: 'moved',
    label: 'Pindah',
    color: 'bg-slate-500',
    bgColor: 'bg-slate-800/60',
    textColor: 'text-slate-400',
  },
  {
    key: 'deceased',
    label: 'Meninggal',
    color: 'bg-rose-700',
    bgColor: 'bg-rose-950/40',
    textColor: 'text-rose-400',
  },
];

export const PopulationStatusBar: React.FC<PopulationStatusBarProps> = ({
  population,
  loading = false,
}) => {
  const total = population.total || 1; // avoid division by zero

  if (loading) {
    return (
      <div className="space-y-4">
        <div className="h-3 w-full bg-slate-800 rounded-full animate-pulse" />
        <div className="grid grid-cols-5 gap-2">
          {[...Array(5)].map((_, i) => (
            <div key={i} className="space-y-1">
              <div className="h-2 bg-slate-800 rounded animate-pulse" />
              <div className="h-3 bg-slate-800/60 rounded animate-pulse" />
            </div>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* Stacked Bar */}
      <div className="flex h-3 rounded-full overflow-hidden gap-px">
        {SEGMENTS.map((seg) => {
          const count = population[seg.key];
          const pct = total > 0 ? (count / total) * 100 : 0;
          if (pct === 0) return null;
          return (
            <div
              key={seg.key}
              className={`${seg.color} transition-all duration-700 first:rounded-l-full last:rounded-r-full`}
              style={{ width: `${pct}%` }}
              title={`${seg.label}: ${count} (${pct.toFixed(1)}%)`}
            />
          );
        })}
      </div>

      {/* Legend */}
      <div className="grid grid-cols-2 sm:grid-cols-5 gap-2">
        {SEGMENTS.map((seg) => {
          const count = population[seg.key];
          const pct = total > 0 ? ((count / total) * 100).toFixed(1) : '0.0';
          return (
            <div key={seg.key} className={`rounded-lg ${seg.bgColor} border border-slate-800/40 p-2.5`}>
              <div className={`text-xs font-bold tabular-nums ${seg.textColor}`}>
                {count.toLocaleString('id-ID')}
              </div>
              <div className="text-[10px] text-slate-500 mt-0.5">{seg.label}</div>
              <div className="text-[10px] text-slate-600">{pct}%</div>
            </div>
          );
        })}
      </div>
    </div>
  );
};
