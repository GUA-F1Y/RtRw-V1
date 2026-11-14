import React from 'react';
import { Link } from 'react-router-dom';
import type { Household } from '../../../types/household.ts';
import { HouseholdStatusBadge } from './HouseholdStatusBadge.tsx';

// ── Table Row ─────────────────────────────────────────────────────────────────

interface HouseholdTableRowProps {
  household: Household;
}

export const HouseholdTableRow: React.FC<HouseholdTableRowProps> = ({ household }) => {
  const headName = household.active_head?.resident?.name ?? household.head?.resident?.name;
  const isHeadVacant = household.status === 'HEAD_VACANT';

  return (
    <tr className="border-b border-slate-800/60 hover:bg-slate-800/30 transition-colors group">
      {/* KK Number */}
      <td className="px-4 py-3">
        {household.kk_number ? (
          <span className="font-mono text-sm text-slate-300">{household.kk_number}</span>
        ) : (
          <span className="text-xs text-slate-600 italic">—</span>
        )}
      </td>

      {/* Kepala Keluarga */}
      <td className="px-4 py-3">
        {isHeadVacant ? (
          <span className="text-xs text-amber-400 italic">Belum memiliki Kepala Keluarga</span>
        ) : headName ? (
          <span className="text-sm font-medium text-slate-200">{headName}</span>
        ) : (
          <span className="text-xs text-slate-600 italic">—</span>
        )}
      </td>

      {/* Member count */}
      <td className="px-4 py-3 text-sm text-slate-300 tabular-nums text-center">
        {household.active_members_count}
      </td>

      {/* RT */}
      <td className="px-4 py-3 text-sm text-slate-400 tabular-nums">
        RT {String(household.rt_id).slice(-3)}
      </td>

      {/* Status */}
      <td className="px-4 py-3">
        <HouseholdStatusBadge status={household.status} />
      </td>

      {/* Detail link */}
      <td className="px-4 py-3 text-right">
        <Link
          to={`/app/households/${household.id}`}
          className="text-xs font-medium text-emerald-400 hover:text-emerald-300 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-500 rounded"
          aria-label={`Lihat detail KK ${household.kk_number ?? household.id}`}
        >
          Detail →
        </Link>
      </td>
    </tr>
  );
};

// ── Table ─────────────────────────────────────────────────────────────────────

interface HouseholdTableProps {
  households: Household[];
}

export const HouseholdTable: React.FC<HouseholdTableProps> = ({ households }) => (
  <div className="overflow-x-auto rounded-xl border border-slate-800/60 bg-slate-900/40">
    <table className="w-full text-left" aria-label="Daftar Kartu Keluarga">
      <thead>
        <tr className="border-b border-slate-800/80 bg-slate-900/60">
          <th scope="col" className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
            No. KK
          </th>
          <th scope="col" className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
            Kepala Keluarga
          </th>
          <th scope="col" className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400 text-center">
            Anggota
          </th>
          <th scope="col" className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
            RT
          </th>
          <th scope="col" className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
            Status
          </th>
          <th scope="col" className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400 text-right">
            Aksi
          </th>
        </tr>
      </thead>
      <tbody>
        {households.map((hh) => (
          <HouseholdTableRow key={hh.id} household={hh} />
        ))}
      </tbody>
    </table>
  </div>
);

// ── Skeleton ──────────────────────────────────────────────────────────────────

export function HouseholdTableSkeleton({ rows = 8 }: { rows?: number }) {
  return (
    <div className="overflow-x-auto rounded-xl border border-slate-800/60 bg-slate-900/40 animate-pulse">
      <table className="w-full">
        <thead>
          <tr className="border-b border-slate-800/80 bg-slate-900/60">
            {['No. KK', 'Kepala Keluarga', 'Anggota', 'RT', 'Status', ''].map((_h, i) => (
              <th key={i} className="px-4 py-3">
                <div className="h-3 bg-slate-800 rounded w-14" />
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {Array.from({ length: rows }).map((_, i) => (
            <tr key={i} className="border-b border-slate-800/40">
              <td className="px-4 py-3"><div className="h-4 bg-slate-800 rounded w-32 font-mono" /></td>
              <td className="px-4 py-3"><div className="h-4 bg-slate-800 rounded w-36" /></td>
              <td className="px-4 py-3"><div className="h-4 bg-slate-800 rounded w-8 mx-auto" /></td>
              <td className="px-4 py-3"><div className="h-4 bg-slate-800 rounded w-10" /></td>
              <td className="px-4 py-3"><div className="h-5 bg-slate-800 rounded-full w-24" /></td>
              <td className="px-4 py-3"><div className="h-4 bg-slate-800 rounded w-12 ml-auto" /></td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
