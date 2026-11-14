import React from 'react';
import type { ResidentStatusHistory } from '../../../types/resident.ts';
import { RESIDENT_STATUS_MAP } from './ResidentStatusBadge.tsx';

interface ResidentStatusHistoryProps {
  history: ResidentStatusHistory[];
}

function formatDatetime(dt: string): string {
  return new Date(dt).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}

/**
 * Chronological status history timeline.
 * Read-only — no edit/delete affordance.
 * Shows only fields present in the server response.
 */
export const ResidentStatusHistorySection: React.FC<ResidentStatusHistoryProps> = ({ history }) => {
  if (!history || history.length === 0) {
    return (
      <section aria-labelledby="section-status-history">
        <h2 id="section-status-history" className="text-sm font-bold uppercase tracking-widest text-slate-400 pb-2">
          Riwayat Status
        </h2>
        <p className="text-sm text-slate-500 italic py-3">Belum ada riwayat perubahan status.</p>
      </section>
    );
  }

  // Chronological: earliest first
  const sorted = [...history].sort(
    (a, b) => new Date(a.effective_at).getTime() - new Date(b.effective_at).getTime()
  );

  return (
    <section aria-labelledby="section-status-history">
      <h2 id="section-status-history" className="text-sm font-bold uppercase tracking-widest text-slate-400 pb-3">
        Riwayat Status
      </h2>
      <ol className="relative border-l border-slate-800/80 space-y-5 ml-3" aria-label="Kronologi perubahan status">
        {sorted.map((entry) => {
          const prevConfig = RESIDENT_STATUS_MAP[entry.previous_status];
          const newConfig = RESIDENT_STATUS_MAP[entry.new_status];
          return (
            <li key={entry.id} className="ml-5">
              {/* Timeline dot */}
              <span
                className={`absolute -left-1.5 flex items-center justify-center w-3 h-3 rounded-full border-2 border-slate-950 ${newConfig.dot}`}
                aria-hidden="true"
              />
              <div className="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3.5">
                {/* Status change row */}
                <div className="flex flex-wrap items-center gap-2 mb-2">
                  <span className={`text-xs font-medium px-2 py-0.5 rounded-full border ${prevConfig.bg} ${prevConfig.text} ${prevConfig.border}`}>
                    {prevConfig.label}
                  </span>
                  <svg className="w-3.5 h-3.5 text-slate-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                  </svg>
                  <span className={`text-xs font-medium px-2 py-0.5 rounded-full border ${newConfig.bg} ${newConfig.text} ${newConfig.border}`}>
                    {newConfig.label}
                  </span>
                </div>
                {/* Effective date */}
                <p className="text-xs text-slate-500">
                  Berlaku sejak{' '}
                  <time dateTime={entry.effective_at} className="font-medium text-slate-400">
                    {formatDatetime(entry.effective_at)}
                  </time>
                </p>
                {/* Reason — only if returned/authorized */}
                {entry.reason && (
                  <p className="text-xs text-slate-500 mt-1">
                    Alasan: <span className="text-slate-400">{entry.reason}</span>
                  </p>
                )}
                {/* Reference letter — only if returned */}
                {entry.reference_letter_number && (
                  <p className="text-xs text-slate-600 mt-1 font-mono">
                    No. Surat: {entry.reference_letter_number}
                  </p>
                )}
                {/* Officer — only if returned */}
                {entry.recorded_by && (
                  <p className="text-xs text-slate-600 mt-1">
                    Dicatat oleh: <span className="text-slate-500">{entry.recorded_by.name}</span>
                  </p>
                )}
              </div>
            </li>
          );
        })}
      </ol>
    </section>
  );
};
