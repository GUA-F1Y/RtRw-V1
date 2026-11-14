import React from 'react';
import { Badge } from '../../components/ui/Badge.tsx';
import { Button } from '../../components/ui/Button.tsx';
import { useAuth } from '../providers/useAuth.ts';

interface AppHeaderProps {
  onToggleMobileNav: () => void;
}

export const AppHeader: React.FC<AppHeaderProps> = ({ onToggleMobileNav }) => {
  const { user, activeScope, logout, isLoading } = useAuth();

  return (
    <header className="sticky top-0 z-30 h-16 border-b border-slate-800 bg-slate-900/80 backdrop-blur-md px-4 sm:px-6 lg:px-8 flex items-center justify-between">
      {/* Left: Mobile Toggle & Brand Context */}
      <div className="flex items-center gap-3">
        <button
          type="button"
          onClick={onToggleMobileNav}
          className="lg:hidden p-2 rounded-lg text-slate-400 hover:text-slate-100 hover:bg-slate-800 transition-colors"
          aria-label="Toggle navigation menu"
        >
          <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>

        <div className="flex items-center gap-2.5">
          <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center font-bold text-slate-950 text-xs shadow-md shadow-emerald-900/30">
            RW
          </div>
          <div>
            <div className="font-bold text-xs sm:text-sm text-slate-100 tracking-tight leading-tight">
              {activeScope?.organization?.name || 'RT/RW Platform'}
            </div>
            <div className="text-[10px] text-slate-400 font-mono">
              {activeScope?.rw?.number ? `RW ${String(activeScope.rw.number).padStart(2, '0')}` : 'RW Coordination'}
              {activeScope?.rt?.number ? ` • RT ${String(activeScope.rt.number).padStart(2, '0')}` : ''}
            </div>
          </div>
        </div>
      </div>

      {/* Right: User Identity & Actions */}
      <div className="flex items-center gap-3">
        {activeScope?.role && (
          <div className="hidden sm:flex items-center gap-2">
            <Badge variant="emerald" size="sm">
              {activeScope.role.name}
            </Badge>
          </div>
        )}

        <div className="flex items-center gap-3 pl-3 border-l border-slate-800">
          <div className="text-right hidden sm:block">
            <div className="text-xs font-semibold text-slate-200">{user?.name}</div>
            <div className="text-[11px] text-slate-400 font-mono">{user?.email}</div>
          </div>
          <Button
            variant="outline"
            size="sm"
            onClick={() => logout()}
            isLoading={isLoading}
            className="text-xs border-slate-700 hover:bg-slate-800"
          >
            Sign Out
          </Button>
        </div>
      </div>
    </header>
  );
};
