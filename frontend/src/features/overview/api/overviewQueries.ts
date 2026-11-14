import { useQuery, type UseQueryOptions } from '@tanstack/react-query';
import type { ApiError } from '../../../lib/api/errors.ts';
import type { ApiSuccessResponse } from '../../../lib/api/types.ts';
import type { Overview } from '../../../types/overview.ts';
import { overviewApi } from './overviewApi.ts';
import { overviewKeys } from './overviewKeys.ts';

export function useOverview(
  options?: Omit<UseQueryOptions<ApiSuccessResponse<Overview>, ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<ApiSuccessResponse<Overview>, ApiError>({
    queryKey: overviewKeys.current(),
    queryFn: ({ signal }) => overviewApi.getOverview({ signal }),
    ...options,
  });
}
