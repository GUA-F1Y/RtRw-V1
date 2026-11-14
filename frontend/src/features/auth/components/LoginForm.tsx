import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { z } from 'zod';
import { useAuth } from '../../../app/providers/useAuth.ts';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { ApiError } from '../../../lib/api/errors.ts';
import { formatAuthError, type FormattedAuthError } from '../utils/authErrors.ts';

const loginSchema = z.object({
  email: z.string().email('Please enter a valid email address'),
  password: z.string().min(1, 'Password is required'),
});

export const LoginForm: React.FC = () => {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [errors, setErrors] = useState<{ email?: string; password?: string }>({});
  const [formattedError, setFormattedError] = useState<FormattedAuthError | null>(null);
  const [isLoading, setIsLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrors({});
    setFormattedError(null);

    const validation = loginSchema.safeParse({ email, password });
    if (!validation.success) {
      const fieldErrors: { email?: string; password?: string } = {};
      for (const issue of validation.error.issues) {
        if (issue.path[0] === 'email') fieldErrors.email = issue.message;
        if (issue.path[0] === 'password') fieldErrors.password = issue.message;
      }
      setErrors(fieldErrors);
      return;
    }

    setIsLoading(true);
    try {
      await login({ email, password, device_name: 'web-browser' });
      navigate('/app', { replace: true });
    } catch (err: unknown) {
      if (err instanceof ApiError && err.code === 'account_inactive') {
        navigate('/inactive', { replace: true });
        return;
      }

      const formatted = formatAuthError(err);
      setFormattedError(formatted);

      if (formatted.fieldErrors) {
        const backendFieldErrors: { email?: string; password?: string } = {};
        if (formatted.fieldErrors.email?.[0]) backendFieldErrors.email = formatted.fieldErrors.email[0];
        if (formatted.fieldErrors.password?.[0]) backendFieldErrors.password = formatted.fieldErrors.password[0];
        setErrors((prev) => ({ ...prev, ...backendFieldErrors }));
      }
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {formattedError && (
        <Alert
          type="error"
          message={formattedError.message}
          requestId={formattedError.requestId}
        />
      )}

      <div>
        <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
          Email Address
        </label>
        <input
          type="email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          placeholder="warga@rtrw.local"
          autoComplete="email"
          className="w-full bg-slate-950/80 border border-slate-700 rounded-lg px-3.5 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all"
        />
        {errors.email && <p className="text-xs text-rose-400 mt-1">{errors.email}</p>}
      </div>

      <div>
        <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
          Password
        </label>
        <input
          type="password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          placeholder="••••••••"
          autoComplete="current-password"
          className="w-full bg-slate-950/80 border border-slate-700 rounded-lg px-3.5 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all"
        />
        {errors.password && <p className="text-xs text-rose-400 mt-1">{errors.password}</p>}
      </div>

      <Button
        type="submit"
        variant="primary"
        className="w-full mt-2"
        isLoading={isLoading}
      >
        Sign In to Platform
      </Button>
    </form>
  );
};
