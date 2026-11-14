import React from 'react';
import { useAuth } from '../../../app/providers/useAuth';
import { Badge } from '../../../components/ui/Badge';
import { Card } from '../../../components/ui/Card';

export const HierarchyTree: React.FC = () => {
  const { user } = useAuth();
  const memberships = user?.memberships || [];

  return (
    <Card
      title="Organizational Scope & Hierarchy"
      subtitle="Fundamental structural distinction: RW is coordinating tier, RT is operational child"
    >
      <div className="grid grid-cols-1 md:grid-cols-4 gap-3 my-4">
        {/* RW Tier */}
        <div className="bg-slate-950/60 border border-emerald-900/40 rounded-lg p-3.5 relative overflow-hidden">
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs font-bold uppercase tracking-wider text-emerald-400">Tier 1: RW</span>
            <Badge variant="emerald">Coordination</Badge>
          </div>
          <p className="text-xs text-slate-300 font-medium">Rukun Warga</p>
          <p className="text-[11px] text-slate-400 mt-1">
            Governance, consolidation, cross-RT approvals, shared assets & monitoring.
          </p>
        </div>

        {/* RT Tier */}
        <div className="bg-slate-950/60 border border-blue-900/40 rounded-lg p-3.5 relative overflow-hidden">
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs font-bold uppercase tracking-wider text-blue-400">Tier 2: RT</span>
            <Badge variant="blue">Operational</Badge>
          </div>
          <p className="text-xs text-slate-300 font-medium">Rukun Tetangga</p>
          <p className="text-[11px] text-slate-400 mt-1">
            Operational unit responsible for residents, dues, and local service delivery.
          </p>
        </div>

        {/* KK Tier */}
        <div className="bg-slate-950/60 border border-indigo-900/30 rounded-lg p-3.5 opacity-75">
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs font-bold uppercase tracking-wider text-indigo-400">Tier 3: KK</span>
            <Badge variant="slate">Foundation Ready</Badge>
          </div>
          <p className="text-xs text-slate-300 font-medium">Kartu Keluarga</p>
          <p className="text-[11px] text-slate-400 mt-1">
            Household grouping unit under respective operational RT.
          </p>
        </div>

        {/* Warga Tier */}
        <div className="bg-slate-950/60 border border-purple-900/30 rounded-lg p-3.5 opacity-75">
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs font-bold uppercase tracking-wider text-purple-400">Tier 4: Warga</span>
            <Badge variant="slate">Foundation Ready</Badge>
          </div>
          <p className="text-xs text-slate-300 font-medium">Individual Resident</p>
          <p className="text-[11px] text-slate-400 mt-1">
            End-user with verified resident identity and service access.
          </p>
        </div>
      </div>

      {/* User's Current Scope */}
      <div className="mt-4 pt-4 border-t border-slate-800">
        <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">
          Your Active Roles & Scopes
        </h4>
        {memberships.length === 0 ? (
          <p className="text-xs text-slate-500">No active organizational memberships found.</p>
        ) : (
          <div className="flex flex-wrap gap-2">
            {memberships.map((m) => (
              <div
                key={m.id}
                className="inline-flex items-center gap-2 bg-slate-950/80 border border-slate-700/80 rounded-lg px-3 py-1.5 text-xs"
              >
                <span className="font-semibold text-emerald-400">{m.role.name}</span>
                <span className="text-slate-500">@</span>
                <Badge variant={m.scope_level === 'organization' ? 'emerald' : m.scope_level === 'rw' ? 'blue' : 'indigo'}>
                  {m.scope_level.toUpperCase()}
                  {m.rw?.number !== undefined ? ` ${String(m.rw.number).padStart(2, '0')}` : ''}
                  {m.rt?.number !== undefined ? ` / RT ${String(m.rt.number).padStart(2, '0')}` : ''}
                </Badge>
              </div>
            ))}
          </div>
        )}
      </div>
    </Card>
  );
};
