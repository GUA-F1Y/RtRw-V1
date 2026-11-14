import React from 'react';

interface CardProps {
  title?: string;
  subtitle?: string;
  badge?: React.ReactNode;
  children: React.ReactNode;
  className?: string;
}

export const Card: React.FC<CardProps> = ({
  title,
  subtitle,
  badge,
  children,
  className = '',
}) => {
  return (
    <div className={`bg-slate-900/70 border border-slate-800 rounded-xl p-5 shadow-xl backdrop-blur-sm ${className}`}>
      {(title || subtitle || badge) && (
        <div className="flex items-start justify-between border-b border-slate-800/80 pb-3 mb-4">
          <div>
            {title && <h3 className="text-base font-semibold text-slate-100">{title}</h3>}
            {subtitle && <p className="text-xs text-slate-400 mt-0.5">{subtitle}</p>}
          </div>
          {badge && <div>{badge}</div>}
        </div>
      )}
      {children}
    </div>
  );
};
