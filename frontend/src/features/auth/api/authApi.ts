import { api, setAuthToken } from '../../../lib/api/client.ts';
import type { AuthSession, User } from '../../../types/auth.ts';

export interface LoginPayload {
  email: string;
  password: string;
  device_name?: string;
}

export const authApi = {
  login: async (payload: LoginPayload): Promise<AuthSession> => {
    const res = await api.post<AuthSession>('auth/login', payload);
    setAuthToken(res.data.token);
    return res.data;
  },

  getMe: async (): Promise<User> => {
    const res = await api.get<User>('auth/me');
    return res.data;
  },

  logout: async (): Promise<void> => {
    try {
      await api.post('auth/logout');
    } finally {
      setAuthToken(null);
    }
  },
};
