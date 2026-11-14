import React from 'react';
import { Badge } from '../../../components/ui/Badge.tsx';
import type { RtUnit } from '../../../types/organization.ts';
import { UnitStatusBadge } from './UnitStatusBadge.tsx';

interface RtUnitCardProps {
  rt: RtUnit;
  position: 'first' | 'middle' | 'last' | 'only';
}

export const RtUnitCard: React.FC<RtUnitCardProps> = ({ rt, position }) => {
  const isActive = rt.status === 'active';
  const statusDescription = isActive ? 'Unit beroperasi secara normal' : 'Unit sementara tidak beroperasi';

  return (
    <div className="flex gap-3">
      {/* Tree connector */}
      <div className="flex flex-col items-center w-5 flex-shrink-0" aria-hidden="true">
        {/* Vertical line above (hidden for first/only) */}
        <div
          className={`w-px flex-shrink-0 ${
            position === 'only' || position === 'first' ? 'bg-transparent' : 'bg-slate-700'
          }`}
          style={{ height: '12px' }}
        />
        {/* Horizontal elbow */}
        <div className="flex items-center gap-0 w-full">
          <div className="w-px bg-slate-700 h-4 flex-shrink-0" />
          <div className="w-3 h-px bg-slate-700 flex-shrink-0" />
        </div>
        {/* Vertical line below (hidden for last/only) */}
        <div
          className={`w-px flex-1 ${
            position === 'only' || position === 'last' ? 'bg-transparent' : 'bg-slate-700'
          }`}
        />
      </div>

      {/* Card Content */}
      <div
        className={`flex-1 mb-2 rounded-xl border transition-all duration-200 overflow-hidden ${
          isActive
            ? 'bg-slate-900/60 border-slate-800/80 hover:border-slate-700 hover:bg-slate-900/80'
            : 'bg-slate-950/40 border-slate-800/40 opacity-75'
        }`}
        role="article"
        aria-label={`RT ${String(rt.number).padStart(2, '0')}${rt.name ? ` — ${rt.name}` : ''}`}
      >
        <div className="p-4">
          {/* Header row */}
          <div className="flex items-start justify-between gap-2">
            <div className="flex items-center gap-3">
              {/* RT Number chip */}
              <div
                className={`w-9 h-9 rounded-lg flex items-center justify-center font-bold text-sm flex-shrink-0 ${
                  isActive
                    ? 'bg-indigo-950/70 text-indigo-300 border border-indigo-800/50'
                    : 'bg-slate-800/60 text-slate-500 border border-slate-700/40'
                }`}
                aria-hidden="true"
              >
                {String(rt.number).padStart(2, '0')}
              </div>

              <div>
                <div className="flex items-center gap-2">
                  <span className="text-sm font-semibold text-slate-100">
                    RT {String(rt.number).padStart(2, '0')}
                  </span>
                  <Badge variant="indigo" size="sm">Operasional</Badge>
                </div>
                {rt.name && (
                  <p className="text-xs text-slate-400 mt-0.5 leading-relaxed">{rt.name}</p>
                )}
              </div>
            </div>

            {/* Status */}
            <div className="flex-shrink-0">
              <UnitStatusBadge status={rt.status} />
            </div>
          </div>

          {/* Footer info */}
          <div className="mt-3 pt-3 border-t border-slate-800/60 flex flex-wrap items-center gap-3">
            <span className="text-[10px] text-slate-500 font-mono">
              ID: {rt.id.slice(0, 8)}…
            </span>
            {statusDescription && (
              <span className="text-[10px] text-slate-500">{statusDescription}</span>
            )}

            {/* Future navigation affordance — no broken links */}
            <div className="ml-auto flex items-center gap-1.5 text-[10px] text-slate-600 italic">
              <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
              Warga & KK — fase berikutnya
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
