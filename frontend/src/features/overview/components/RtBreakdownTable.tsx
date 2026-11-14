import React from 'react';
import type { RtOverviewMetric } from '../../../types/overview.ts';

interface RtBreakdownTableProps {
  rtBreakdown: RtOverviewMetric[];
  loading?: boolean;
}

export const RtBreakdownTable: React.FC<RtBreakdownTableProps> = ({ rtBreakdown, loading = false }) => {
  if (loading) {
    return (
      <div className="space-y-2">
        {[...Array(4)].map((_, i) => (
          <div key={i} className="h-12 bg-slate-800/40 rounded-lg animate-pulse" />
        ))}
      </div>
    );
  }

  if (rtBreakdown.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center py-8 text-slate-500">
        <svg className="w-8 h-8 mb-2 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
        </svg>
        <p className="text-xs">Belum ada data RT</p>
      </div>
    );
  }

  const maxResidents = Math.max(...rtBreakdown.map((r) => r.resident_count), 1);

  return (
    <div className="overflow-x-auto -mx-1">
      <table className="w-full text-xs min-w-[400px]">
        <thead>
          <tr className="border-b border-slate-800/80">
            <th className="text-left py-2 px-3 text-slate-500 font-medium uppercase tracking-wider text-[10px]">
              RT
            </th>
            <th className="text-right py-2 px-3 text-slate-500 font-medium uppercase tracking-wider text-[10px]">
              Warga
            </th>
            <th className="text-right py-2 px-3 text-slate-500 font-medium uppercase tracking-wider text-[10px]">
              KK
            </th>
            <th className="text-right py-2 px-3 text-slate-500 font-medium uppercase tracking-wider text-[10px]">
              Vakant
            </th>
            <th className="py-2 px-3 text-slate-500 font-medium uppercase tracking-wider text-[10px] w-32">
              Kepadatan
            </th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-800/40">
          {rtBreakdown.map((rt) => {
            const barWidth = maxResidents > 0 ? (rt.resident_count / maxResidents) * 100 : 0;
            const hasVacant = rt.head_vacant_household_count > 0;

            return (
              <tr
                key={rt.rt_id}
                className="hover:bg-slate-800/20 transition-colors group"
              >
                {/* RT Code */}
                <td className="py-3 px-3">
                  <div className="flex items-center gap-2">
                    <span className="w-6 h-6 rounded-md bg-emerald-950/50 border border-emerald-900/30 text-emerald-400 text-[10px] font-bold flex items-center justify-center flex-shrink-0">
                      {rt.rt_code}
                    </span>
                    <span className="text-slate-300 font-medium">RT {rt.rt_code}</span>
                  </div>
                </td>

                {/* Residents */}
                <td className="py-3 px-3 text-right">
                  <span className="font-semibold text-slate-200 tabular-nums">
                    {rt.resident_count.toLocaleString('id-ID')}
                  </span>
                </td>

                {/* Households */}
                <td className="py-3 px-3 text-right">
                  <span className="font-medium text-slate-300 tabular-nums">
                    {rt.household_count.toLocaleString('id-ID')}
                  </span>
                </td>

                {/* Head Vacant */}
                <td className="py-3 px-3 text-right">
                  {hasVacant ? (
                    <span className="inline-flex items-center gap-1 text-amber-400 font-semibold tabular-nums">
                      <span className="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse" />
                      {rt.head_vacant_household_count}
                    </span>
                  ) : (
                    <span className="text-emerald-500 text-[10px] font-medium">✓ Lengkap</span>
                  )}
                </td>

                {/* Density Bar */}
                <td className="py-3 px-3">
                  <div className="flex items-center gap-2">
                    <div className="flex-1 h-1.5 bg-slate-800 rounded-full overflow-hidden">
                      <div
                        className="h-full bg-gradient-to-r from-emerald-600 to-emerald-400 rounded-full transition-all duration-500"
                        style={{ width: `${barWidth}%` }}
                      />
                    </div>
                    <span className="text-[10px] text-slate-500 tabular-nums w-8 text-right">
                      {barWidth.toFixed(0)}%
                    </span>
                  </div>
                </td>
              </tr>
            );
          })}
        </tbody>

        {/* Summary Footer */}
        {rtBreakdown.length > 1 && (
          <tfoot>
            <tr className="border-t border-slate-700/60">
              <td className="py-2.5 px-3 text-[10px] text-slate-500 font-medium uppercase tracking-wider">
                Total ({rtBreakdown.length} RT)
              </td>
              <td className="py-2.5 px-3 text-right font-bold text-slate-200 tabular-nums">
                {rtBreakdown.reduce((s, r) => s + r.resident_count, 0).toLocaleString('id-ID')}
              </td>
              <td className="py-2.5 px-3 text-right font-bold text-slate-200 tabular-nums">
                {rtBreakdown.reduce((s, r) => s + r.household_count, 0).toLocaleString('id-ID')}
              </td>
              <td className="py-2.5 px-3 text-right">
                {rtBreakdown.reduce((s, r) => s + r.head_vacant_household_count, 0) > 0 ? (
                  <span className="text-amber-400 font-bold tabular-nums">
                    {rtBreakdown.reduce((s, r) => s + r.head_vacant_household_count, 0)}
                  </span>
                ) : (
                  <span className="text-emerald-500 text-[10px] font-medium">✓</span>
                )}
              </td>
              <td className="py-2.5 px-3" />
            </tr>
          </tfoot>
        )}
      </table>
    </div>
  );
};
