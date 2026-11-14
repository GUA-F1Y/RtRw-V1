import { QueryClient } from '@tanstack/react-query';
import { ApiError } from '../../lib/api/errors.ts';

/**
 * Canonical QueryClient Configuration
 *
 * Retry Policy:
 * - 4xx errors (400, 401, 403, 404, 409, 419, 422) are client/auth/validation/conflict errors and NEVER retried.
 * - 5xx server errors or transient network failures may retry up to 2 times.
 * - Mutations never retry automatically to prevent duplicate side effects.
 *
 * Cache Policy:
 * - Queries have 5-minute staleTime by default.
 * - Inactive queries are garbage-collected after 15 minutes.
 * - refetchOnWindowFocus is disabled by default for controlled data fetching.
 */
export function createQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 1000 * 60 * 5, // 5 minutes
        gcTime: 1000 * 60 * 15, // 15 minutes
        refetchOnWindowFocus: false,
        retry: (failureCount, error) => {
          // If error is an ApiError with 4xx status, do NOT retry
          if (error instanceof ApiError) {
            if (error.status >= 400 && error.status < 500) {
              return false;
            }
          }

          // Check generic status property if present
          if (typeof error === 'object' && error !== null && 'status' in error) {
            const status = Number((error as { status: unknown }).status);
            if (status >= 400 && status < 500) {
              return false;
            }
          }

          // Allow at most 2 retries for transient/5xx errors
          return failureCount < 2;
        },
      },
      mutations: {
        // Never automatically retry mutations to protect business invariants
        retry: false,
      },
    },
  });
}

export const queryClient = createQueryClient();
