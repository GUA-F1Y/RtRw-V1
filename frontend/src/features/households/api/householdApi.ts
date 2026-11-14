import { apiClient } from '../../../lib/api/client.ts';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type { ApiSuccessResponse } from '../../../lib/api/types.ts';
import type {
  AddHouseholdMemberPayload,
  ChangeHouseholdHeadPayload,
  CreateHouseholdPayload,
  DissolveHouseholdPayload,
  Household,
  HouseholdIncludeToken,
  HouseholdMember,
  HouseholdQueryParams,
  MoveHouseholdPayload,
  RemoveHouseholdMemberPayload,
  UpdateHouseholdPayload,
} from '../../../types/household.ts';

export interface HouseholdRequestOptions {
  signal?: AbortSignal;
  idempotencyKey?: string;
}

export const householdApi = {
  /**
   * List households with scope-constrained filters, pagination, search, and sorting
   * GET /api/v1/households
   */
  list: async (
    params?: HouseholdQueryParams,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<Household[]>> => {
    return apiClient.get<Household[]>('households', {
      params: params as Record<string, unknown>,
      signal: options?.signal,
    });
  },

  /**
   * Retrieve household detail by ID
   * GET /api/v1/households/{id}
   */
  getById: async (
    id: string,
    params?: { include?: HouseholdIncludeToken[] | string },
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<Household>> => {
    return apiClient.get<Household>(`households/${encodeURIComponent(id)}`, {
      params: params as Record<string, unknown>,
      signal: options?.signal,
    });
  },

  /**
   * Create a new household
   * POST /api/v1/households
   * Requires Idempotency-Key
   */
  create: async (
    payload: CreateHouseholdPayload,
    options?: HouseholdRequestOptions
  ): Promise<ApiSuccessResponse<Household>> => {
    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.post<Household>('households', payload, {
      idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Update dwelling / address details
   * PATCH /api/v1/households/{id}
   */
  update: async (
    id: string,
    payload: UpdateHouseholdPayload,
    options?: HouseholdRequestOptions
  ): Promise<ApiSuccessResponse<Household>> => {
    return apiClient.patch<Household>(`households/${encodeURIComponent(id)}`, payload, {
      idempotencyKey: options?.idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Add member to household
   * POST /api/v1/households/{id}/members
   * Requires Idempotency-Key
   */
  addMember: async (
    id: string,
    payload: AddHouseholdMemberPayload,
    options?: HouseholdRequestOptions
  ): Promise<ApiSuccessResponse<HouseholdMember>> => {
    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.post<HouseholdMember>(`households/${encodeURIComponent(id)}/members`, payload, {
      idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Remove member from household
   * DELETE /api/v1/households/{id}/members/{residentId}
   */
  removeMember: async (
    id: string,
    residentId: string,
    payload?: RemoveHouseholdMemberPayload,
    options?: HouseholdRequestOptions
  ): Promise<ApiSuccessResponse<null>> => {
    const endpoint = `households/${encodeURIComponent(id)}/members/${encodeURIComponent(residentId)}`;
    return apiClient.delete<null>(endpoint, {
      body: payload && Object.keys(payload).length > 0 ? payload : undefined,
      idempotencyKey: options?.idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Change head of household
   * POST /api/v1/households/{id}/change-head
   */
  changeHead: async (
    id: string,
    payload: ChangeHouseholdHeadPayload,
    options?: HouseholdRequestOptions
  ): Promise<ApiSuccessResponse<Household>> => {
    return apiClient.post<Household>(`households/${encodeURIComponent(id)}/change-head`, payload, {
      idempotencyKey: options?.idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Relocate entire household (INTER_RT or INTER_RW)
   * POST /api/v1/households/{id}/move
   * Requires Idempotency-Key
   */
  move: async (
    id: string,
    payload: MoveHouseholdPayload,
    options?: HouseholdRequestOptions
  ): Promise<ApiSuccessResponse<Household>> => {
    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.post<Household>(`households/${encodeURIComponent(id)}/move`, payload, {
      idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Dissolve household
   * POST /api/v1/households/{id}/dissolve
   */
  dissolve: async (
    id: string,
    payload: DissolveHouseholdPayload = {},
    options?: HouseholdRequestOptions
  ): Promise<ApiSuccessResponse<Household>> => {
    return apiClient.post<Household>(`households/${encodeURIComponent(id)}/dissolve`, payload, {
      idempotencyKey: options?.idempotencyKey,
      signal: options?.signal,
    });
  },
};
