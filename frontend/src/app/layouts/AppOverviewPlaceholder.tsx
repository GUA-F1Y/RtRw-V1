import React from 'react';
import { Link } from 'react-router-dom';
import { Badge } from '../../components/ui/Badge.tsx';
import { Card } from '../../components/ui/Card.tsx';
import { useAuth } from '../providers/useAuth.ts';

export const AppOverviewPlaceholder: React.FC = () => {
  const { user, activeScope } = useAuth();

  return (
    <div className="space-y-6">
      {/* Welcome Banner */}
      <div className="rounded-2xl border border-emerald-900/40 bg-gradient-to-r from-emerald-950/40 via-slate-900/60 to-slate-950 p-6 shadow-lg shadow-emerald-950/20">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2">
              <span className="text-xs uppercase tracking-wider font-semibold text-emerald-400">
                Active Governance Workspace
              </span>
              <Badge variant="emerald" size="sm">
                {activeScope?.scopeLevel ? activeScope.scopeLevel.toUpperCase() : 'PLATFORM'}
              </Badge>
            </div>
            <h1 className="text-xl sm:text-2xl font-bold text-slate-100 tracking-tight mt-1">
              Welcome back, {user?.name || 'Administrator'}
            </h1>
            <p className="text-xs sm:text-sm text-slate-400 mt-1">
              {activeScope?.organization?.name || 'RT/RW Community Administration'}
              {activeScope?.rw?.number ? ` • RW ${String(activeScope.rw.number).padStart(2, '0')}` : ''}
              {activeScope?.rt?.number ? ` • RT ${String(activeScope.rt.number).padStart(2, '0')}` : ''}
            </p>
          </div>

          <div className="flex items-center gap-2">
            <Link
              to="/app/hierarchy"
              className="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-slate-950 font-semibold text-xs transition-colors shadow-md shadow-emerald-900/30"
            >
              Explore Hierarchy & Units →
            </Link>
          </div>
        </div>
      </div>

      {/* Structural Overview Grid */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card
          title="Data Layer Foundation"
          subtitle="Phase 12 Completed"
          badge={<Badge variant="emerald" size="sm">Ready</Badge>}
        >
          <p className="text-xs text-slate-300 leading-relaxed mt-2">
            Canonical typed API clients, TanStack Query hooks, error normalizer, and idempotency engine are fully integrated with backend API v1.
          </p>
        </Card>

        <Card
          title="Authentication & Session"
          subtitle="Phase 13 In Progress"
          badge={<Badge variant="blue" size="sm">Active</Badge>}
        >
          <p className="text-xs text-slate-300 leading-relaxed mt-2">
            Protected route guards, account inactive boundaries, multi-account cache isolation, and RW-centric responsive shell are established.
          </p>
        </Card>

        <Card
          title="Domain Management UI"
          subtitle="Phase 14 Scheduled"
          badge={<Badge variant="slate" size="sm">Phase 14</Badge>}
        >
          <p className="text-xs text-slate-400 leading-relaxed mt-2">
            Resident directory, Household management, registration forms, and ledger movement visual views will be implemented in the next phase.
          </p>
        </Card>
      </div>
    </div>
  );
};
