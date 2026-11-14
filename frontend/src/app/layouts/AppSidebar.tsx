import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { Badge } from '../../components/ui/Badge.tsx';
import { APP_NAVIGATION_ITEMS, getVisibleNavItems } from '../navigation/navCapabilities.ts';
import { useAuth } from '../providers/useAuth.ts';

interface AppSidebarProps {
  isMobileOpen: boolean;
  onCloseMobile: () => void;
}

export const AppSidebar: React.FC<AppSidebarProps> = ({ isMobileOpen, onCloseMobile }) => {
  const { user, activeScope } = useAuth();
  const location = useLocation();

  const navItems = getVisibleNavItems(APP_NAVIGATION_ITEMS, user);

  return (
    <>
      {/* Mobile Backdrop */}
      {isMobileOpen && (
        <div
          className="fixed inset-0 z-40 bg-slate-950/80 backdrop-blur-sm lg:hidden"
          onClick={onCloseMobile}
          aria-hidden="true"
        />
      )}

      {/* Sidebar Container */}
      <aside
        className={`fixed top-0 bottom-0 left-0 z-40 w-64 border-r border-slate-800 bg-slate-900 flex flex-col transition-transform duration-200 ease-in-out lg:translate-x-0 ${
          isMobileOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        {/* Sidebar Header Brand */}
        <div className="h-16 border-b border-slate-800 px-5 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center font-bold text-slate-950 text-xs shadow-md shadow-emerald-900/30">
              RW
            </div>
            <div>
              <div className="font-bold text-sm tracking-tight text-slate-100">
                RT/RW Platform
              </div>
              <div className="text-[10px] text-slate-400 font-mono">
                Multi-Tier Governance
              </div>
            </div>
          </div>

          <button
            type="button"
            onClick={onCloseMobile}
            className="lg:hidden p-1.5 rounded-lg text-slate-400 hover:text-slate-200 hover:bg-slate-800"
            aria-label="Close navigation menu"
          >
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        {/* Navigation Items */}
        <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
          <div className="px-3 pb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500 font-mono">
            Navigation Menu
          </div>

          {navItems.map((item) => {
            const isActive =
              item.path === '/app'
                ? location.pathname === '/app'
                : location.pathname.startsWith(item.path);

            if (item.disabled) {
              return (
                <div
                  key={item.id}
                  className="flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-medium text-slate-500 cursor-not-allowed opacity-75"
                  title="Scheduled for next phase"
                >
                  <span className="flex items-center gap-2.5">
                    <span className="w-2 h-2 rounded-full bg-slate-700" />
                    {item.label}
                  </span>
                  {item.badge && (
                    <Badge variant="slate" size="sm">
                      {item.badge}
                    </Badge>
                  )}
                </div>
              );
            }

            return (
              <Link
                key={item.id}
                to={item.path}
                onClick={onCloseMobile}
                className={`flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-medium transition-all ${
                  isActive
                    ? 'bg-emerald-950/50 text-emerald-300 border border-emerald-800/40 shadow-sm'
                    : 'text-slate-300 hover:bg-slate-800/60 hover:text-slate-100'
                }`}
              >
                <span className="flex items-center gap-2.5">
                  <span
                    className={`w-2 h-2 rounded-full ${
                      isActive ? 'bg-emerald-400 ring-2 ring-emerald-400/20' : 'bg-slate-600'
                    }`}
                  />
                  {item.label}
                </span>
                {item.badge && (
                  <Badge variant="blue" size="sm">
                    {item.badge}
                  </Badge>
                )}
              </Link>
            );
          })}
        </nav>

        {/* Sidebar Footer Context */}
        <div className="border-t border-slate-800 p-4 bg-slate-950/40">
          <div className="text-[11px] font-medium text-slate-400 mb-1">Active Authority</div>
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-slate-200">
              {activeScope?.role?.name || 'Authorized Member'}
            </span>
            <Badge variant="emerald" size="sm">
              {activeScope?.scopeLevel || 'rw'}
            </Badge>
          </div>
          <div className="text-[10px] text-slate-500 font-mono mt-1">
            Hierarchy: RW → RT → KK → Warga
          </div>
        </div>
      </aside>
    </>
  );
};
