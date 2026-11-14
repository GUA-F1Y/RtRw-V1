import React, { useEffect, useMemo, useState } from 'react';
import { authApi, type LoginPayload } from '../../features/auth/api/authApi.ts';
import { authKeys } from '../../features/auth/api/authKeys.ts';
import { getAuthToken, setAuthToken } from '../../lib/api/client.ts';
import { ApiError } from '../../lib/api/errors.ts';
import type { AuthStatus, User } from '../../types/auth.ts';
import { deriveActiveScope } from '../navigation/navCapabilities.ts';
import { queryClient } from '../query/queryClient.ts';
import { AuthContext } from './AuthContext.ts';

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [status, setStatus] = useState<AuthStatus>('loading');
  const [error, setError] = useState<ApiError | null>(null);

  const activeScope = useMemo(() => deriveActiveScope(user), [user]);

  // Session hydration on application mount
  useEffect(() => {
    let isCancelled = false;

    const hydrateSession = async () => {
      const token = getAuthToken();
      if (!token) {
        if (!isCancelled) {
          setStatus('unauthenticated');
          setUser(null);
        }
        return;
      }

      setStatus('loading');
      setError(null);

      try {
        const currentUser = await authApi.getMe();
        if (!isCancelled) {
          setUser(currentUser);
          queryClient.setQueryData(authKeys.currentUser(), currentUser);
          setStatus('authenticated');
        }
      } catch (err: unknown) {
        if (!isCancelled) {
          if (err instanceof ApiError) {
            setError(err);
            if (err.status === 401) {
              setAuthToken(null);
              setUser(null);
              setStatus('unauthenticated');
            } else if (err.status === 403 && err.code === 'account_inactive') {
              setUser(null);
              setStatus('account_inactive');
            } else {
              // Transient or unexpected error - do not clear token immediately
              setStatus('error');
            }
          } else {
            setStatus('error');
          }
        }
      }
    };

    hydrateSession();

    return () => {
      isCancelled = true;
    };
  }, []);

  const login = async (payload: LoginPayload): Promise<void> => {
    setStatus('loading');
    setError(null);

    try {
      const session = await authApi.login(payload);
      setUser(session.user);
      queryClient.setQueryData(authKeys.currentUser(), session.user);
      setStatus('authenticated');
    } catch (err: unknown) {
      if (err instanceof ApiError) {
        setError(err);
        if (err.status === 403 && err.code === 'account_inactive') {
          setStatus('account_inactive');
        } else {
          setStatus('unauthenticated');
        }
      } else {
        setStatus('unauthenticated');
      }
      throw err;
    }
  };

  const logout = async (): Promise<void> => {
    setStatus('loading');
    try {
      await authApi.logout();
    } catch {
      // Best-effort logout: ignore server failure so client always resets cleanly
    } finally {
      setAuthToken(null);
      setUser(null);
      setError(null);
      // Guarantee zero multi-account cache leakage by clearing the entire TanStack Query cache
      queryClient.clear();
      setStatus('unauthenticated');
    }
  };

  const refreshUser = async (): Promise<void> => {
    const token = getAuthToken();
    if (!token) {
      setUser(null);
      setStatus('unauthenticated');
      return;
    }

    try {
      const currentUser = await authApi.getMe();
      setUser(currentUser);
      queryClient.setQueryData(authKeys.currentUser(), currentUser);
      setStatus('authenticated');
    } catch (err: unknown) {
      if (err instanceof ApiError) {
        if (err.status === 401) {
          setAuthToken(null);
          setUser(null);
          queryClient.clear();
          setStatus('unauthenticated');
        } else if (err.status === 403 && err.code === 'account_inactive') {
          setUser(null);
          setStatus('account_inactive');
        }
      }
    }
  };

  const isLoading = status === 'loading';
  const isAuthenticated = status === 'authenticated' && user !== null;
  const isAccountInactive = status === 'account_inactive';

  return (
    <AuthContext.Provider
      value={{
        user,
        status,
        isLoading,
        isAuthenticated,
        isAccountInactive,
        activeScope,
        error,
        login,
        logout,
        refreshUser,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
};
