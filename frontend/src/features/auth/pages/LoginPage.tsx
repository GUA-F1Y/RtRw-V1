import React, { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../../app/providers/useAuth';
import { Card } from '../../../components/ui/Card';
import { LoginForm } from '../components/LoginForm';

export const LoginPage: React.FC = () => {
  const { isAuthenticated, isLoading } = useAuth();
  const navigate = useNavigate();

  useEffect(() => {
    if (!isLoading && isAuthenticated) {
      navigate('/', { replace: true });
    }
  }, [isAuthenticated, isLoading, navigate]);

  return (
    <div className="min-h-screen bg-slate-950 flex flex-col items-center justify-center p-4 relative overflow-hidden">
      {/* Background glow effects */}
      <div className="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none" />
      <div className="absolute bottom-1/4 right-1/4 w-80 h-80 bg-blue-500/10 rounded-full blur-3xl pointer-events-none" />

      <div className="w-full max-w-md relative z-10">
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-600 text-slate-950 font-black text-2xl shadow-xl shadow-emerald-500/20 mb-4">
            RW
          </div>
          <h1 className="text-2xl font-bold text-slate-100 tracking-tight">
            RT/RW Governance Platform
          </h1>
          <p className="text-sm text-slate-400 mt-1">
            Community Administration & Multi-Tier Hierarchy
          </p>
        </div>

        <Card className="border-slate-800/80 bg-slate-900/80 backdrop-blur-xl">
          <div className="mb-4">
            <h2 className="text-lg font-semibold text-slate-100">Sign In</h2>
            <p className="text-xs text-slate-400">
              Access your scoped governance workspace
            </p>
          </div>
          <LoginForm />
        </Card>

        <div className="mt-8 text-center text-xs text-slate-500 space-y-1">
          <p>Hierarchy: RW → RT → KK → Warga</p>
          <p>Laravel 13 REST API + React TypeScript</p>
        </div>
      </div>
    </div>
  );
};
