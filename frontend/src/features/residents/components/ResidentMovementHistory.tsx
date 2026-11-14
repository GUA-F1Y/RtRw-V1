import React from 'react';
import type { ResidencyMovement, ScopeRefSummary } from '../../../types/resident.ts';

interface ResidentMovementHistoryProps {
  movements: ResidencyMovement[];
}

function formatDate(dt: string): string {
  return new Date(dt).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}

function scopeLabel(scope: ScopeRefSummary): string {
  if (scope.rt_number != null && scope.rw_number != null) {
    return `RT ${scope.rt_number} / RW ${scope.rw_number}`;
  }
  if (scope.rt_number != null) {
    return `RT ${scope.rt_number}`;
  }
  if (scope.rw_number != null) {
    return `RW ${scope.rw_number}`;
  }
  return 'Lokasi tidak diketahui';
}

function movementTypeLabel(type: ResidencyMovement['movement_type']): string {
  switch (type) {
    case 'TRANSFER_INTERNAL':
      return 'Pindah Internal';
    case 'MOVE_OUT_EXTERNAL':
      return 'Pindah Keluar';
    default:
      return type;
  }
}

function movementTypeBadge(type: ResidencyMovement['movement_type']): string {
  switch (type) {
    case 'TRANSFER_INTERNAL':
      return 'bg-blue-500/10 text-blue-400 border-blue-500/20';
    case 'MOVE_OUT_EXTERNAL':
      return 'bg-amber-500/10 text-amber-400 border-amber-500/20';
    default:
      return 'bg-slate-500/10 text-slate-400 border-slate-500/20';
  }
}

/**
 * Chronological movement history timeline.
 * Read-only — no edit/delete affordance.
 * Shows only fields present in the server response (respects PrivacyTier).
 */
export const ResidentMovementHistory: React.FC<ResidentMovementHistoryProps> = ({ movements }) => {
  if (!movements || movements.length === 0) {
    return (
      <section aria-labelledby="section-movement-history">
        <h2 id="section-movement-history" className="text-sm font-bold uppercase tracking-widest text-slate-400 pb-2">
          Riwayat Perpindahan
        </h2>
        <p className="text-sm text-slate-500 italic py-3">Belum ada riwayat perpindahan.</p>
      </section>
    );
  }

  // Chronological: earliest first
  const sorted = [...movements].sort(
    (a, b) => new Date(a.effective_date).getTime() - new Date(b.effective_date).getTime()
  );

  return (
    <section aria-labelledby="section-movement-history">
      <h2 id="section-movement-history" className="text-sm font-bold uppercase tracking-widest text-slate-400 pb-3">
        Riwayat Perpindahan
      </h2>
      <ol className="relative border-l border-slate-800/80 space-y-5 ml-3" aria-label="Kronologi perpindahan">
        {sorted.map((movement) => {
          const badgeClass = movementTypeBadge(movement.movement_type);
          return (
            <li key={movement.id} className="ml-5">
              {/* Timeline dot */}
              <span
                className="absolute -left-1.5 flex items-center justify-center w-3 h-3 rounded-full border-2 border-slate-950 bg-indigo-500"
                aria-hidden="true"
              />
              <div className="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3.5">
                {/* Movement type badge */}
                <div className="flex flex-wrap items-center gap-2 mb-2">
                  <span className={`text-xs font-medium px-2 py-0.5 rounded-full border ${badgeClass}`}>
                    {movementTypeLabel(movement.movement_type)}
                  </span>
                </div>

                {/* From → To */}
                <div className="flex flex-wrap items-center gap-2 text-sm mb-2">
                  <span className="text-slate-400 font-medium">{scopeLabel(movement.from_scope)}</span>
                  <svg className="w-3.5 h-3.5 text-slate-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                  </svg>
                  <span className="text-slate-300 font-medium">{scopeLabel(movement.to_scope)}</span>
                </div>

                {/* Effective date */}
                <p className="text-xs text-slate-500">
                  Berlaku sejak{' '}
                  <time dateTime={movement.effective_date} className="font-medium text-slate-400">
                    {formatDate(movement.effective_date)}
                  </time>
                </p>

                {/* Notes — only if returned/authorized */}
                {movement.notes && (
                  <p className="text-xs text-slate-500 mt-1">
                    Catatan: <span className="text-slate-400">{movement.notes}</span>
                  </p>
                )}

                {/* Reference letter — only if returned */}
                {movement.reference_letter_number && (
                  <p className="text-xs text-slate-600 mt-1 font-mono">
                    No. Surat: {movement.reference_letter_number}
                  </p>
                )}

                {/* Officer — only if returned */}
                {movement.recorded_by && (
                  <p className="text-xs text-slate-600 mt-1">
                    Dicatat oleh: <span className="text-slate-500">{movement.recorded_by.name}</span>
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
