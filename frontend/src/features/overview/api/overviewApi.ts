import { apiClient } from '../../../lib/api/client.ts';
import type { ApiSuccessResponse } from '../../../lib/api/types.ts';
import type { Overview } from '../../../types/overview.ts';

export interface OverviewRequestOptions {
  signal?: AbortSignal;
}

export const overviewApi = {
  /**
   * Retrieve aggregate overview metrics for current actor's authorized scope.
   * GET /api/v1/overview
   */
  getOverview: async (
    options?: OverviewRequestOptions
  ): Promise<ApiSuccessResponse<Overview>> => {
    return apiClient.get<Overview>('overview', {
      signal: options?.signal,
    });
  },
};
