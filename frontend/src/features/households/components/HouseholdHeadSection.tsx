import React from 'react';
import { Link } from 'react-router-dom';
import type { Household } from '../../../types/household.ts';
import { HouseholdStatusBadge } from './HouseholdStatusBadge.tsx';

interface HouseholdHeadSectionProps {
  household: Household;
}

/**
 * Displays the current head of household.
 * Current head = active_head (relationship KEPALA_KELUARGA, is_active: true).
 * Falls back to head if active_head not included.
 * Shows head-vacant condition clearly when status === HEAD_VACANT.
 * Links to Resident Detail page using backend-provided resident ID only.
 * Never displays NIK, phone, or sensitive fields.
 */
export const HouseholdHeadSection: React.FC<HouseholdHeadSectionProps> = ({ household }) => {
  const currentHead = household.active_head ?? household.head;
  const isHeadVacant = household.status === 'HEAD_VACANT';

  return (
    <section aria-labelledby="section-head">
      <h2 id="section-head" className="text-sm font-bold uppercase tracking-widest text-slate-400 pb-3">
        Kepala Keluarga
      </h2>

      {/* Head-vacant condition */}
      {isHeadVacant && (
        <div className="rounded-xl border border-amber-700/40 bg-amber-950/20 px-4 py-3 mb-3 flex items-center gap-3">
          <svg className="w-5 h-5 text-amber-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.75} d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
          </svg>
          <p className="text-sm text-amber-300 font-medium">Belum memiliki Kepala Keluarga</p>
        </div>
      )}

      {/* Head present */}
      {currentHead && currentHead.resident ? (
        <div className="rounded-xl border border-slate-800/60 bg-slate-900/40 p-4 flex items-start gap-4">
          {/* Avatar */}
          <div
            className="w-12 h-12 rounded-full bg-gradient-to-br from-emerald-900/60 to-slate-800 border border-emerald-700/30 flex items-center justify-center text-lg font-bold text-emerald-400 flex-shrink-0"
            aria-hidden="true"
          >
            {currentHead.resident.name.charAt(0).toUpperCase()}
          </div>
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-2 flex-wrap">
              <p className="font-semibold text-slate-100">{currentHead.resident.name}</p>
              <HouseholdStatusBadge status="ACTIVE" size="sm" />
            </div>
            <p className="text-xs text-slate-500 mt-0.5">Kepala Keluarga</p>
            {currentHead.joined_at && (
              <p className="text-xs text-slate-600 mt-1">
                Bergabung sejak{' '}
                <time dateTime={currentHead.joined_at}>
                  {new Date(currentHead.joined_at).toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                  })}
                </time>
              </p>
            )}
            {/* Link to Resident Detail — uses backend-provided ID only */}
            <Link
              to={`/app/residents/${currentHead.resident_id}`}
              className="inline-flex items-center gap-1 mt-2 text-xs text-emerald-400 hover:text-emerald-300 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-500 rounded"
            >
              Lihat profil warga →
            </Link>
          </div>
        </div>
      ) : !isHeadVacant ? (
        <p className="text-sm text-slate-500 italic py-2">Data kepala keluarga tidak tersedia.</p>
      ) : null}
    </section>
  );
};
