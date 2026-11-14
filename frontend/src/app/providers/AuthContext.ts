import { createContext } from 'react';
import type { LoginPayload } from '../../features/auth/api/authApi.ts';
import type { ApiError } from '../../lib/api/errors.ts';
import type { AuthStatus, ScopeContext, User } from '../../types/auth.ts';

export interface AuthContextType {
  user: User | null;
  status: AuthStatus;
  isLoading: boolean;
  isAuthenticated: boolean;
  isAccountInactive: boolean;
  activeScope: ScopeContext | null;
  error: ApiError | null;
  login: (payload: LoginPayload) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

export const AuthContext = createContext<AuthContextType | undefined>(undefined);
