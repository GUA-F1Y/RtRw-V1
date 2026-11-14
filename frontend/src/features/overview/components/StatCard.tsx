import React from 'react';

interface StatCardProps {
  label: string;
  value: number | string;
  sub?: string;
  accent?: 'emerald' | 'blue' | 'amber' | 'rose' | 'indigo' | 'slate';
  icon: React.ReactNode;
  trend?: {
    direction: 'up' | 'down' | 'neutral';
    label: string;
  };
  loading?: boolean;
}

const accentMap = {
  emerald: {
    icon: 'bg-emerald-950/60 text-emerald-400 ring-1 ring-emerald-700/30',
    value: 'text-emerald-300',
    glow: 'shadow-emerald-950/30',
    border: 'border-emerald-900/30',
  },
  blue: {
    icon: 'bg-blue-950/60 text-blue-400 ring-1 ring-blue-700/30',
    value: 'text-blue-300',
    glow: 'shadow-blue-950/30',
    border: 'border-blue-900/30',
  },
  amber: {
    icon: 'bg-amber-950/60 text-amber-400 ring-1 ring-amber-700/30',
    value: 'text-amber-300',
    glow: 'shadow-amber-950/30',
    border: 'border-amber-900/30',
  },
  rose: {
    icon: 'bg-rose-950/60 text-rose-400 ring-1 ring-rose-700/30',
    value: 'text-rose-300',
    glow: 'shadow-rose-950/30',
    border: 'border-rose-900/30',
  },
  indigo: {
    icon: 'bg-indigo-950/60 text-indigo-400 ring-1 ring-indigo-700/30',
    value: 'text-indigo-300',
    glow: 'shadow-indigo-950/30',
    border: 'border-indigo-900/30',
  },
  slate: {
    icon: 'bg-slate-800/60 text-slate-400 ring-1 ring-slate-700/30',
    value: 'text-slate-200',
    glow: 'shadow-slate-950/30',
    border: 'border-slate-800/30',
  },
};

const trendColors = {
  up: 'text-emerald-400',
  down: 'text-rose-400',
  neutral: 'text-slate-400',
};

const trendArrow = {
  up: '↑',
  down: '↓',
  neutral: '→',
};

export const StatCard: React.FC<StatCardProps> = ({
  label,
  value,
  sub,
  accent = 'slate',
  icon,
  trend,
  loading = false,
}) => {
  const styles = accentMap[accent];

  return (
    <div
      className={`relative bg-slate-900/70 border ${styles.border} rounded-2xl p-5 shadow-xl ${styles.glow} backdrop-blur-sm overflow-hidden group hover:bg-slate-900/90 transition-all duration-200`}
    >
      {/* Subtle background gradient on hover */}
      <div className="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none bg-gradient-to-br from-white/[0.02] to-transparent" />

      <div className="relative flex items-start justify-between">
        <div className="flex-1 min-w-0">
          <p className="text-xs font-medium text-slate-400 uppercase tracking-wider mb-2">{label}</p>

          {loading ? (
            <div className="space-y-2">
              <div className="h-8 w-24 bg-slate-800 rounded-lg animate-pulse" />
              <div className="h-3 w-16 bg-slate-800/60 rounded animate-pulse" />
            </div>
          ) : (
            <>
              <p className={`text-3xl font-bold tracking-tight ${styles.value} tabular-nums`}>
                {typeof value === 'number' ? value.toLocaleString('id-ID') : value}
              </p>

              <div className="flex items-center gap-2 mt-1.5">
                {sub && <p className="text-xs text-slate-500">{sub}</p>}
                {trend && (
                  <span className={`text-xs font-medium ${trendColors[trend.direction]} flex items-center gap-0.5`}>
                    <span>{trendArrow[trend.direction]}</span>
                    <span>{trend.label}</span>
                  </span>
                )}
              </div>
            </>
          )}
        </div>

        <div className={`w-10 h-10 rounded-xl ${styles.icon} flex items-center justify-center flex-shrink-0 ml-3`}>
          {icon}
        </div>
      </div>
    </div>
  );
};
