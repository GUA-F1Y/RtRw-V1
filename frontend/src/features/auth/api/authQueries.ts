import { useQuery, type UseQueryOptions } from '@tanstack/react-query';
import { getAuthToken } from '../../../lib/api/client';
import type { ApiError } from '../../../lib/api/errors';
import type { User } from '../../../types/auth';
import { authApi } from './authApi';
import { authKeys } from './authKeys';

export function useCurrentUser(
  options?: Omit<UseQueryOptions<User, ApiError>, 'queryKey' | 'queryFn'>
) {
  const token = getAuthToken();

  return useQuery<User, ApiError>({
    queryKey: authKeys.currentUser(),
    queryFn: () => authApi.getMe(),
    enabled: Boolean(token) && (options?.enabled ?? true),
    staleTime: 1000 * 60 * 5, // 5 minutes
    retry: false, // Don't retry on auth errors
    ...options,
  });
}
