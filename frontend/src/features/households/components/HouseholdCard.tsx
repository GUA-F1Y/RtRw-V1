import React from 'react';
import { Link } from 'react-router-dom';
import type { Household } from '../../../types/household.ts';
import { HouseholdStatusBadge } from './HouseholdStatusBadge.tsx';

interface HouseholdCardProps {
  household: Household;
}

/**
 * Mobile-optimized compact household card.
 * KK rendered as-is from server (may be masked/absent — never derived).
 * No NIK, phone, or sensitive resident fields.
 */
export const HouseholdCard: React.FC<HouseholdCardProps> = ({ household }) => {
  const headName = household.active_head?.resident?.name ?? household.head?.resident?.name;
  const isHeadVacant = household.status === 'HEAD_VACANT';

  return (
    <Link
      to={`/app/households/${household.id}`}
      className="block rounded-xl border border-slate-800/60 bg-slate-900/40 p-4 hover:bg-slate-800/50 hover:border-slate-700/60 transition-all focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-500"
      aria-label={`Kartu Keluarga ${household.kk_number ?? 'tanpa nomor'}, status ${household.status}`}
    >
      <div className="flex items-start justify-between gap-3 mb-3">
        {/* KK Icon */}
        <div
          className="w-10 h-10 rounded-xl bg-gradient-to-br from-slate-700 to-slate-800 border border-slate-700/60 flex items-center justify-center flex-shrink-0"
          aria-hidden="true"
        >
          <svg className="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
        </div>

        <div className="flex-1 min-w-0">
          {/* KK Number */}
          <p className="font-mono text-sm text-slate-300 truncate leading-tight">
            {household.kk_number ?? <span className="text-slate-600 italic not-italic">No. KK tidak tersedia</span>}
          </p>
          {/* Head name */}
          <p className="text-xs text-slate-500 mt-0.5 truncate">
            {isHeadVacant ? (
              <span className="text-amber-400 italic">Belum memiliki Kepala Keluarga</span>
            ) : headName ? (
              headName
            ) : (
              <span className="italic">—</span>
            )}
          </p>
        </div>

        <HouseholdStatusBadge status={household.status} size="sm" />
      </div>

      {/* Footer meta */}
      <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
        <span>{household.active_members_count} anggota aktif</span>
        <span>•</span>
        <span>RT {String(household.rt_id).slice(-3)}</span>
      </div>
    </Link>
  );
};

// ── Card Skeleton ─────────────────────────────────────────────────────────────

export function HouseholdCardSkeleton() {
  return (
    <div className="rounded-xl border border-slate-800/40 bg-slate-900/40 p-4 animate-pulse">
      <div className="flex items-start gap-3 mb-3">
        <div className="w-10 h-10 rounded-xl bg-slate-800 flex-shrink-0" />
        <div className="flex-1 space-y-2">
          <div className="h-4 bg-slate-800 rounded w-36 font-mono" />
          <div className="h-3 bg-slate-800 rounded w-24" />
        </div>
        <div className="h-5 bg-slate-800 rounded-full w-16" />
      </div>
      <div className="flex gap-3">
        <div className="h-3 bg-slate-800 rounded w-20" />
        <div className="h-3 bg-slate-800 rounded w-12" />
      </div>
    </div>
  );
}
