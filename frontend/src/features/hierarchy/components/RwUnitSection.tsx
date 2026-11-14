import React from 'react';
import { Badge } from '../../../components/ui/Badge.tsx';
import type { RtUnit, RwUnit } from '../../../types/organization.ts';
import { RtUnitCard } from './RtUnitCard.tsx';
import { UnitStatusBadge } from './UnitStatusBadge.tsx';

interface RwUnitSectionProps {
  rw: RwUnit;
  rts: RtUnit[];
  isLoadingRts?: boolean;
}

function RtListSkeleton() {
  return (
    <div className="space-y-2 mt-4 pl-5">
      {[...Array(3)].map((_, i) => (
        <div key={i} className="h-16 rounded-xl bg-slate-800/30 animate-pulse" />
      ))}
    </div>
  );
}

export const RwUnitSection: React.FC<RwUnitSectionProps> = ({ rw, rts, isLoadingRts = false }) => {
  const activeRtCount = rts.filter((rt) => rt.status === 'active').length;

  const getPosition = (index: number, total: number) => {
    if (total === 1) return 'only';
    if (index === 0) return 'first';
    if (index === total - 1) return 'last';
    return 'middle';
  };

  return (
    <section
      aria-label={`RW ${String(rw.number).padStart(2, '0')}`}
      className="rounded-2xl border border-slate-800/60 bg-slate-900/40 overflow-hidden"
    >
      {/* RW Header */}
      <div className="px-5 py-4 border-b border-slate-800/60 bg-slate-900/60 flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          {/* RW Icon */}
          <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-950/80 to-slate-900 border border-emerald-800/40 flex items-center justify-center flex-shrink-0">
            <span className="text-emerald-400 font-bold text-xs">RW</span>
          </div>

          <div>
            <div className="flex items-center gap-2 flex-wrap">
              <h3 className="text-base font-bold text-slate-100">
                RW {String(rw.number).padStart(2, '0')}
              </h3>
              <Badge variant="emerald" size="sm">Koordinasi</Badge>
              <UnitStatusBadge status={rw.status} />
            </div>
            {rw.name && (
              <p className="text-xs text-slate-400 mt-0.5">{rw.name}</p>
            )}
          </div>
        </div>

        {/* RT count summary */}
        <div className="flex items-center gap-2 text-xs text-slate-400">
          <Badge variant="indigo" size="sm">
            {rts.length} RT
          </Badge>
          {activeRtCount < rts.length && (
            <Badge variant="amber" size="sm">
              {rts.length - activeRtCount} tidak aktif
            </Badge>
          )}
        </div>
      </div>

      {/* RT List */}
      <div className="p-5">
        {isLoadingRts ? (
          <RtListSkeleton />
        ) : rts.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-8 text-slate-500">
            <svg className="w-8 h-8 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <p className="text-sm font-medium text-slate-500">Belum ada unit RT</p>
            <p className="text-xs text-slate-600 mt-1">RW ini belum memiliki RT yang terdaftar.</p>
          </div>
        ) : (
          <div>
            {/* Tree root label */}
            <div className="flex items-center gap-2 mb-3 ml-5 text-[10px] font-semibold uppercase tracking-widest text-slate-600">
              <div className="w-px h-3 bg-slate-700" aria-hidden="true" />
              <span>Unit RT dalam RW {String(rw.number).padStart(2, '0')}</span>
            </div>

            {/* RT cards with tree connectors */}
            <div role="list" aria-label={`Daftar RT dalam RW ${String(rw.number).padStart(2, '0')}`}>
              {rts.map((rt, index) => (
                <div key={rt.id} role="listitem">
                  <RtUnitCard
                    rt={rt}
                    position={getPosition(index, rts.length)}
                  />
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </section>
  );
};
