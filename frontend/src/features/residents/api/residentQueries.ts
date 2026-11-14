import { useMutation, useQuery, useQueryClient, type UseQueryOptions } from '@tanstack/react-query';
import type { ApiError } from '../../../lib/api/errors';
import { generateIdempotencyKey } from '../../../lib/api/idempotency';
import type { ApiSuccessResponse } from '../../../lib/api/types';
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
} from '../../../types/resident';
import { overviewKeys } from '../../overview/api/overviewKeys.ts';
import { residentApi } from './residentApi';
import { residentKeys } from './residentKeys';

// -------------------------------------------------------------
// Read Queries
// -------------------------------------------------------------

export function useResidents(
  params?: ResidentQueryParams,
  options?: Omit<UseQueryOptions<ApiSuccessResponse<Resident[]>, ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<ApiSuccessResponse<Resident[]>, ApiError>({
    queryKey: residentKeys.list(params),
    queryFn: ({ signal }) => residentApi.list(params, { signal }),
    ...options,
  });
}

export function useResident(
  id: string,
  params?: { include?: ResidentIncludeToken[] | string },
  options?: Omit<UseQueryOptions<ApiSuccessResponse<Resident>, ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<ApiSuccessResponse<Resident>, ApiError>({
    queryKey: residentKeys.detail(id, params?.include),
    queryFn: ({ signal }) => residentApi.getById(id, params, { signal }),
    enabled: Boolean(id) && (options?.enabled ?? true),
    ...options,
  });
}

// -------------------------------------------------------------
// Mutation Hooks (Data-Layer Only)
// -------------------------------------------------------------

export function useRegisterResident() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<Resident>,
    ApiError,
    { payload: RegisterResidentPayload; idempotencyKey?: string }
  >({
    mutationFn: ({ payload, idempotencyKey }) => {
      // Stable idempotency key per command execution attempt
      const key = idempotencyKey || generateIdempotencyKey();
      return residentApi.register(payload, { idempotencyKey: key });
    },
    onSuccess: () => {
      // Invalidate resident list caches and overview metrics
      queryClient.invalidateQueries({ queryKey: residentKeys.lists() });
      queryClient.invalidateQueries({ queryKey: overviewKeys.all });
    },
  });
}

export function useUpdateResidentProfile() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<Resident>,
    ApiError,
    { id: string; payload: UpdateResidentProfilePayload }
  >({
    mutationFn: ({ id, payload }) => residentApi.updateProfile(id, payload),
    onSuccess: (_response, { id }) => {
      queryClient.invalidateQueries({ queryKey: residentKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: residentKeys.lists() });
    },
  });
}

export function useVerifyResident() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<Resident>,
    ApiError,
    { id: string; payload?: VerifyResidentPayload }
  >({
    mutationFn: ({ id, payload }) => residentApi.verify(id, payload),
    onSuccess: (_response, { id }) => {
      queryClient.invalidateQueries({ queryKey: residentKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: residentKeys.lists() });
      queryClient.invalidateQueries({ queryKey: overviewKeys.all });
    },
  });
}

export function useRelocateResident() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<Resident>,
    ApiError,
    { id: string; payload: RelocateResidentPayload; idempotencyKey?: string }
  >({
    mutationFn: ({ id, payload, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return residentApi.relocate(id, payload, { idempotencyKey: key });
    },
    onSuccess: (_response, { id, payload }) => {
      queryClient.invalidateQueries({ queryKey: residentKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: residentKeys.lists() });
      queryClient.invalidateQueries({ queryKey: overviewKeys.all });
      // Invalidate target & source household queries if applicable
      if (payload.target_household_id) {
        queryClient.invalidateQueries({ queryKey: ['households'] });
      }
    },
  });
}

export function useChangeResidentStatus() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<Resident>,
    ApiError,
    { id: string; payload: ChangeResidentStatusPayload }
  >({
    mutationFn: ({ id, payload }) => residentApi.changeStatus(id, payload),
    onSuccess: (_response, { id }) => {
      queryClient.invalidateQueries({ queryKey: residentKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: residentKeys.lists() });
      // Invalidate households as member status could change household state
      queryClient.invalidateQueries({ queryKey: ['households'] });
      queryClient.invalidateQueries({ queryKey: overviewKeys.all });
    },
  });
}

export function useRemoveResidentFromHousehold() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<Resident>,
    ApiError,
    { id: string; payload?: RemoveResidentFromHouseholdPayload }
  >({
    mutationFn: ({ id, payload }) => residentApi.removeFromHousehold(id, payload),
    onSuccess: (_response, { id }) => {
      queryClient.invalidateQueries({ queryKey: residentKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: residentKeys.lists() });
      queryClient.invalidateQueries({ queryKey: ['households'] });
      queryClient.invalidateQueries({ queryKey: overviewKeys.all });
    },
  });
}
