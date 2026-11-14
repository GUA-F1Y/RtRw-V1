import React from 'react';

interface AlertProps {
  type?: 'error' | 'success' | 'warning' | 'info';
  title?: string;
  message: string;
  requestId?: string;
  className?: string;
}

export const Alert: React.FC<AlertProps> = ({
  type = 'error',
  title,
  message,
  requestId,
  className = '',
}) => {
  const typeStyles = {
    error: 'bg-rose-950/40 border-rose-800/60 text-rose-300',
    success: 'bg-emerald-950/40 border-emerald-800/60 text-emerald-300',
    warning: 'bg-amber-950/40 border-amber-800/60 text-amber-300',
    info: 'bg-blue-950/40 border-blue-800/60 text-blue-300',
  };

  return (
    <div className={`p-4 rounded-xl border text-sm ${typeStyles[type]} ${className}`} role="alert">
      {title && <div className="font-semibold mb-1">{title}</div>}
      <div>{message}</div>
      {requestId && (
        <div className="mt-2 text-xs font-mono opacity-70">
          Request ID: {requestId}
        </div>
      )}
    </div>
  );
};
