import React from 'react';
import { useNavigate } from 'react-router-dom';
import { Button } from '../../components/ui/Button.tsx';
import { Card } from '../../components/ui/Card.tsx';

export const ForbiddenPage: React.FC = () => {
  const navigate = useNavigate();

  return (
    <div className="min-h-screen bg-slate-950 flex flex-col items-center justify-center p-4">
      <div className="w-full max-w-md text-center">
        <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 font-bold text-2xl shadow-xl mb-4">
          403
        </div>
        <h1 className="text-2xl font-bold text-slate-100 tracking-tight">Access Forbidden</h1>
        <p className="text-sm text-slate-400 mt-1 mb-6">
          Your current organizational scope does not authorize access to this resource.
        </p>

        <Card className="border-slate-800 bg-slate-900/80">
          <Button
            variant="primary"
            className="w-full"
            onClick={() => navigate('/app', { replace: true })}
          >
            Return to Overview
          </Button>
        </Card>
      </div>
    </div>
  );
};
