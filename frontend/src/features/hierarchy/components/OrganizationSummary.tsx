import React from 'react';
import { Badge } from '../../../components/ui/Badge.tsx';
import type { Organization } from '../../../types/organization.ts';

interface OrganizationSummaryProps {
  organization: Organization;
}

export const OrganizationSummary: React.FC<OrganizationSummaryProps> = ({ organization }) => {
  return (
    <div className="flex items-start gap-4 p-5 rounded-2xl border border-slate-800/60 bg-slate-900/50 backdrop-blur-sm">
      {/* Icon */}
      <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-950/80 to-emerald-900/40 border border-emerald-800/40 flex items-center justify-center flex-shrink-0 shadow-lg shadow-emerald-950/30">
        <svg className="w-6 h-6 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={1.75}
            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
          />
        </svg>
      </div>

      {/* Content */}
      <div className="flex-1 min-w-0">
        <div className="flex flex-wrap items-center gap-2 mb-1">
          <span className="text-[10px] font-bold uppercase tracking-widest text-emerald-500">
            Organisasi
          </span>
          <Badge variant="emerald" size="sm">
            Level Tertinggi
          </Badge>
        </div>

        <h2 className="text-lg font-bold text-slate-100 tracking-tight truncate">
          {organization.name}
        </h2>

        <div className="flex flex-wrap items-center gap-3 mt-1.5">
          <span className="text-xs text-slate-400 font-mono">
            /{organization.slug}
          </span>
          {organization.rws_count !== undefined && (
            <span className="text-xs text-slate-500">
              {organization.rws_count} RW terdaftar
            </span>
          )}
        </div>
      </div>
    </div>
  );
};
