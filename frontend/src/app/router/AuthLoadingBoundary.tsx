import React from 'react';
import { LoadingSpinner } from '../../components/feedback/LoadingSpinner.tsx';

export const AuthLoadingBoundary: React.FC<{ message?: string }> = ({
  message = 'Authenticating session...',
}) => {
  return (
    <div className="min-h-screen bg-slate-950 flex flex-col items-center justify-center p-4">
      <div className="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center font-black text-slate-950 text-xl shadow-lg shadow-emerald-500/20 mb-4 animate-pulse">
        RW
      </div>
      <LoadingSpinner message={message} />
      <p className="text-[11px] text-slate-500 font-mono mt-3">
        RT/RW Community Administration Platform
      </p>
    </div>
  );
};
