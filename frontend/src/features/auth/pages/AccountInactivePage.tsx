import React from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../../app/providers/useAuth.ts';
import { Button } from '../../../components/ui/Button.tsx';
import { Card } from '../../../components/ui/Card.tsx';

export const AccountInactivePage: React.FC = () => {
  const { logout, error } = useAuth();
  const navigate = useNavigate();

  const handleSignOut = async () => {
    await logout();
    navigate('/login', { replace: true });
  };

  return (
    <div className="min-h-screen bg-slate-950 flex flex-col items-center justify-center p-4 relative overflow-hidden">
      <div className="w-full max-w-md relative z-10">
        <div className="text-center mb-6">
          <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-amber-500/20 border border-amber-500/40 text-amber-400 font-bold text-2xl shadow-xl shadow-amber-500/10 mb-4">
            !
          </div>
          <h1 className="text-2xl font-bold text-slate-100 tracking-tight">
            Account Inactive
          </h1>
          <p className="text-sm text-slate-400 mt-1">
            Access to this RT/RW workspace is restricted
          </p>
        </div>

        <Card className="border-amber-900/40 bg-slate-900/80 backdrop-blur-xl">
          <div className="space-y-4">
            <p className="text-sm text-slate-300 leading-relaxed">
              Your account is currently disabled or suspended by governance policy.
              Please reach out to your RW Chairman or Organization Administrator to reactivate your access.
            </p>

            {error?.requestId && (
              <div className="p-3 bg-slate-950/80 rounded-lg border border-slate-800 text-[11px] font-mono text-slate-400">
                <span className="text-slate-500">Request ID:</span> {error.requestId}
              </div>
            )}

            <div className="pt-2">
              <Button
                variant="outline"
                className="w-full text-slate-300 border-slate-700 hover:bg-slate-800"
                onClick={handleSignOut}
              >
                Sign Out & Return to Login
              </Button>
            </div>
          </div>
        </Card>
      </div>
    </div>
  );
};
