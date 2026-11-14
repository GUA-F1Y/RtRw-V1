import React from 'react';
import { Link } from 'react-router-dom';
import type { Resident } from '../../../types/resident.ts';
import { ResidentStatusBadge } from './ResidentStatusBadge.tsx';

const GENDER_LABEL: Record<string, string> = { MALE: 'Laki-laki', FEMALE: 'Perempuan' };
const RESIDENCY_LABEL: Record<string, string> = {
  PERMANENT: 'Tetap',
  TEMPORARY: 'Sementara',
  SEASONAL: 'Musiman',
};

interface ResidentCardProps {
  resident: Resident;
}

/**
 * Mobile-optimized compact resident card.
 * Shows name, status, age/gender, RT, residency type.
 * No NIK, phone, or sensitive fields.
 */
export const ResidentCard: React.FC<ResidentCardProps> = ({ resident }) => (
  <Link
    to={`/app/residents/${resident.id}`}
    className="block rounded-xl border border-slate-800/60 bg-slate-900/40 p-4 hover:bg-slate-800/50 hover:border-slate-700/60 transition-all focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-500"
    aria-label={`Warga ${resident.name}, status ${resident.status}`}
  >
    <div className="flex items-start justify-between gap-3">
      {/* Avatar initial */}
      <div
        className="w-10 h-10 rounded-full bg-gradient-to-br from-slate-700 to-slate-800 border border-slate-700/60 flex items-center justify-center text-slate-300 font-bold text-sm flex-shrink-0"
        aria-hidden="true"
      >
        {resident.name.charAt(0).toUpperCase()}
      </div>

      <div className="flex-1 min-w-0">
        <div className="flex items-start justify-between gap-2 flex-wrap">
          <p className="font-semibold text-slate-100 truncate leading-tight">{resident.name}</p>
          <ResidentStatusBadge status={resident.status} size="sm" />
        </div>

        <div className="flex flex-wrap gap-x-3 gap-y-0.5 mt-1.5 text-xs text-slate-500">
          <span>{GENDER_LABEL[resident.gender] ?? resident.gender}</span>
          <span>•</span>
          <span>{resident.age} th</span>
          <span>•</span>
          <span>{RESIDENCY_LABEL[resident.residency_type] ?? resident.residency_type}</span>
        </div>
      </div>
    </div>
  </Link>
);

// ── Card skeleton ─────────────────────────────────────────────────────────────

export function ResidentCardSkeleton({ count = 5 }: { count?: number }) {
  return (
    <div className="space-y-3 animate-pulse">
      {Array.from({ length: count }).map((_, i) => (
        <div
          key={i}
          className="rounded-xl border border-slate-800/40 bg-slate-900/40 p-4"
        >
          <div className="flex items-start gap-3">
            <div className="w-10 h-10 rounded-full bg-slate-800 flex-shrink-0" />
            <div className="flex-1 space-y-2">
              <div className="flex justify-between gap-2">
                <div className="h-4 bg-slate-800 rounded w-32" />
                <div className="h-5 bg-slate-800 rounded-full w-20" />
              </div>
              <div className="h-3 bg-slate-800 rounded w-24" />
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}
