import React from 'react';
import { Outlet } from 'react-router-dom';
import { useAuth } from '../providers/useAuth';
import { Button } from '../../components/ui/Button';
import { Badge } from '../../components/ui/Badge';

export const AppLayout: React.FC = () => {
  const { user, logout } = useAuth();
  const primaryMembership = user?.memberships?.[0];

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col">
      {/* Top Navigation */}
      <header className="border-b border-slate-800 bg-slate-900/60 backdrop-blur-md sticky top-0 z-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center font-bold text-slate-950 text-sm shadow-md shadow-emerald-900/40">
              RW
            </div>
            <div>
              <div className="font-bold text-sm tracking-tight text-slate-100">
                RT/RW Platform
              </div>
              <div className="text-[10px] text-slate-400 font-mono">
                Community Governance
              </div>
            </div>
          </div>

          <div className="flex items-center gap-4">
            {primaryMembership && (
              <div className="hidden sm:flex items-center gap-2">
                <span className="text-xs text-slate-400">Scoped Authority:</span>
                <Badge variant="emerald" size="sm">
                  {primaryMembership.role.name}
                </Badge>
              </div>
            )}

            <div className="flex items-center gap-3 pl-4 border-l border-slate-800">
              <div className="text-right hidden sm:block">
                <div className="text-xs font-semibold text-slate-200">{user?.name}</div>
                <div className="text-[11px] text-slate-400 font-mono">{user?.email}</div>
              </div>
              <Button
                variant="outline"
                size="sm"
                onClick={() => logout()}
                className="text-xs"
              >
                Sign Out
              </Button>
            </div>
          </div>
        </div>
      </header>

      {/* Main Content Area */}
      <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <Outlet />
      </main>

      {/* Footer */}
      <footer className="border-t border-slate-800/80 bg-slate-950/80 py-4 text-center text-xs text-slate-500">
        <p>RT/RW Community Administration Platform • Multi-Tier Scoped Governance (RW → RT → KK → Warga)</p>
      </footer>
    </div>
  );
};
