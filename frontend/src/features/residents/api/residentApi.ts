import { apiClient } from '../../../lib/api/client.ts';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type { ApiSuccessResponse } from '../../../lib/api/types.ts';
import type {
  ChangeResidentStatusPayload,
  RegisterResidentPayload,
  RelocateResidentPayload,
  RemoveResidentFromHouseholdPayload,
  Resident,
  ResidentIncludeToken,
  ResidentQueryParams,
  UpdateResidentProfilePayload,
  VerifyResidentPayload,
} from '../../../types/resident.ts';

export interface ResidentRequestOptions {
  signal?: AbortSignal;
  idempotencyKey?: string;
}

export const residentApi = {
  /**
   * List residents with scope-constrained filters, pagination, search, and sorting
   * GET /api/v1/residents
   */
  list: async (
    params?: ResidentQueryParams,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<Resident[]>> => {
    return apiClient.get<Resident[]>('residents', {
      params: params as Record<string, unknown>,
      signal: options?.signal,
    });
  },

  /**
   * Retrieve resident detail by ID
   * GET /api/v1/residents/{id}
   */
  getById: async (
    id: string,
    params?: { include?: ResidentIncludeToken[] | string },
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<Resident>> => {
    return apiClient.get<Resident>(`residents/${encodeURIComponent(id)}`, {
      params: params as Record<string, unknown>,
      signal: options?.signal,
    });
  },

  /**
   * Register a new resident (always initial PROSPECTIVE status)
   * POST /api/v1/residents
   * Requires Idempotency-Key
   */
  register: async (
    payload: RegisterResidentPayload,
    options?: ResidentRequestOptions
  ): Promise<ApiSuccessResponse<Resident>> => {
    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.post<Resident>('residents', payload, {
      idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Update mutable demographic profile fields
   * PATCH /api/v1/residents/{id}
   */
  updateProfile: async (
    id: string,
    payload: UpdateResidentProfilePayload,
    options?: ResidentRequestOptions
  ): Promise<ApiSuccessResponse<Resident>> => {
    return apiClient.patch<Resident>(`residents/${encodeURIComponent(id)}`, payload, {
      idempotencyKey: options?.idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Verify prospective resident (PROSPECTIVE -> ACTIVE)
   * POST /api/v1/residents/{id}/verify
   */
  verify: async (
    id: string,
    payload: VerifyResidentPayload = {},
    options?: ResidentRequestOptions
  ): Promise<ApiSuccessResponse<Resident>> => {
    return apiClient.post<Resident>(`residents/${encodeURIComponent(id)}/verify`, payload, {
      idempotencyKey: options?.idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Relocate resident (Internal transfer within RW/RT or external move out)
   * POST /api/v1/residents/{id}/relocate
   * Requires Idempotency-Key
   */
  relocate: async (
    id: string,
    payload: RelocateResidentPayload,
    options?: ResidentRequestOptions
  ): Promise<ApiSuccessResponse<Resident>> => {
    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.post<Resident>(`residents/${encodeURIComponent(id)}/relocate`, payload, {
      idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Change resident civil status (DEACTIVATE, REACTIVATE, MARK_DECEASED)
   * POST /api/v1/residents/{id}/status
   */
  changeStatus: async (
    id: string,
    payload: ChangeResidentStatusPayload,
    options?: ResidentRequestOptions
  ): Promise<ApiSuccessResponse<Resident>> => {
    return apiClient.post<Resident>(`residents/${encodeURIComponent(id)}/status`, payload, {
      idempotencyKey: options?.idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Remove resident from current household
   * POST /api/v1/residents/{id}/remove-from-household
   */
  removeFromHousehold: async (
    id: string,
    payload: RemoveResidentFromHouseholdPayload = {},
    options?: ResidentRequestOptions
  ): Promise<ApiSuccessResponse<Resident>> => {
    return apiClient.post<Resident>(
      `residents/${encodeURIComponent(id)}/remove-from-household`,
      payload,
      {
        idempotencyKey: options?.idempotencyKey,
        signal: options?.signal,
      }
    );
  },
};
