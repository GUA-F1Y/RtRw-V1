import React from 'react';
import { Link } from 'react-router-dom';
import type { Resident } from '../../../types/resident.ts';

interface ResidentHouseholdSectionProps {
  resident: Resident;
}

/**
 * Displays the resident's current household context.
 * Uses only data present in the Resident response — no separate household fetch.
 * Links to Household Detail page using backend-provided current_household_id.
 */
export const ResidentHouseholdSection: React.FC<ResidentHouseholdSectionProps> = ({ resident }) => {
  // current_household_id may be absent for homeless/moved/deceased residents
  if (!resident.current_household_id) {
    return (
      <section aria-labelledby="section-household" className="space-y-1">
        <h2 id="section-household" className="text-sm font-bold uppercase tracking-widest text-slate-400 pb-2">
          Kartu Keluarga
        </h2>
        <p className="text-sm text-slate-500 italic py-3">
          Warga ini tidak terdaftar dalam kartu keluarga manapun.
        </p>
      </section>
    );
  }

  return (
    <section aria-labelledby="section-household" className="space-y-1">
      <h2 id="section-household" className="text-sm font-bold uppercase tracking-widest text-slate-400 pb-2">
        Kartu Keluarga
      </h2>
      <div className="rounded-xl border border-slate-800/60 bg-slate-900/40 p-4">
        <div className="flex items-start gap-3">
          {/* Household icon */}
          <div className="w-9 h-9 rounded-lg bg-slate-800/80 border border-slate-700/50 flex items-center justify-center flex-shrink-0" aria-hidden="true">
            <svg className="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.75} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-xs text-slate-500 font-semibold uppercase tracking-wider mb-1">
              ID Kartu Keluarga
            </p>
            <p className="text-sm font-mono text-slate-300 break-all">
              {resident.current_household_id}
            </p>
            {/* Link to Household Detail — uses backend-provided ID */}
            <Link
              to={`/app/households/${resident.current_household_id}`}
              className="inline-flex items-center gap-1 mt-2 text-xs text-emerald-400 hover:text-emerald-300 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-500 rounded"
            >
              Lihat detail KK →
            </Link>
          </div>
        </div>
      </div>
    </section>
  );
};
