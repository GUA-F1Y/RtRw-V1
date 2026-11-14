import { useMutation, useQuery, useQueryClient, type UseQueryOptions } from '@tanstack/react-query';
import type { ApiError } from '../../../lib/api/errors.ts';
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
import { overviewKeys } from '../../overview/api/overviewKeys.ts';
import { householdApi } from './householdApi.ts';
import { householdKeys } from './householdKeys.ts';

// -------------------------------------------------------------
// Read Queries
// -------------------------------------------------------------

export function useHouseholds(
  params?: HouseholdQueryParams,
  options?: Omit<UseQueryOptions<ApiSuccessResponse<Household[]>, ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<ApiSuccessResponse<Household[]>, ApiError>({
    queryKey: householdKeys.list(params),
    queryFn: ({ signal }) => householdApi.list(params, { signal }),
    ...options,
  });
}

export function useHousehold(
  id: string,
  params?: { include?: HouseholdIncludeToken[] | string },
  options?: Omit<UseQueryOptions<ApiSuccessResponse<Household>, ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<ApiSuccessResponse<Household>, ApiError>({
    queryKey: householdKeys.detail(id, params?.include),
    queryFn: ({ signal }) => householdApi.getById(id, params, { signal }),
    enabled: Boolean(id) && (options?.enabled ?? true),
    ...options,
  });
}

// -------------------------------------------------------------
// Mutation Hooks (Data-Layer Only)
// -------------------------------------------------------------

export function useCreateHousehold() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<Household>,
    ApiError,
    { payload: CreateHouseholdPayload; idempotencyKey?: string }
  >({
    mutationFn: ({ payload, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return householdApi.create(payload, { idempotencyKey: key });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: householdKeys.lists() });
      queryClient.invalidateQueries({ queryKey: ['residents'] });
      queryClient.invalidateQueries({ queryKey: overviewKeys.all });
    },
  });
}

export function useUpdateHousehold() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<Household>,
    ApiError,
    { id: string; payload: UpdateHouseholdPayload }
  >({
    mutationFn: ({ id, payload }) => householdApi.update(id, payload),
    onSuccess: (_response, { id }) => {
      queryClient.invalidateQueries({ queryKey: householdKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: householdKeys.lists() });
    },
  });
}

export function useAddHouseholdMember() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<HouseholdMember>,
    ApiError,
    { id: string; payload: AddHouseholdMemberPayload; idempotencyKey?: string }
  >({
    mutationFn: ({ id, payload, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return householdApi.addMember(id, payload, { idempotencyKey: key });
    },
    onSuccess: (_response, { id, payload }) => {
      queryClient.invalidateQueries({ queryKey: householdKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: householdKeys.lists() });
      // Invalidate the specific resident and resident lists
      queryClient.invalidateQueries({ queryKey: ['residents', 'detail', payload.resident_id] });
      queryClient.invalidateQueries({ queryKey: ['residents', 'list'] });
      queryClient.invalidateQueries({ queryKey: overviewKeys.all });
    },
  });
}

export function useRemoveHouseholdMember() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<null>,
    ApiError,
    { id: string; residentId: string; payload?: RemoveHouseholdMemberPayload; idempotencyKey?: string }
  >({
    mutationFn: ({ id, residentId, payload, idempotencyKey }) =>
      householdApi.removeMember(id, residentId, payload, { idempotencyKey }),
    onSuccess: (_response, { id, residentId }) => {
      queryClient.invalidateQueries({ queryKey: householdKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: householdKeys.lists() });
      queryClient.invalidateQueries({ queryKey: ['residents', 'detail', residentId] });
      queryClient.invalidateQueries({ queryKey: ['residents', 'list'] });
      queryClient.invalidateQueries({ queryKey: overviewKeys.all });
    },
  });
}

export function useChangeHouseholdHead() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<Household>,
    ApiError,
    { id: string; payload: ChangeHouseholdHeadPayload; idempotencyKey?: string }
  >({
    mutationFn: ({ id, payload, idempotencyKey }) =>
      householdApi.changeHead(id, payload, { idempotencyKey }),
    onSuccess: (_response, { id }) => {
      queryClient.invalidateQueries({ queryKey: householdKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: householdKeys.lists() });
      queryClient.invalidateQueries({ queryKey: ['residents'] });
      queryClient.invalidateQueries({ queryKey: overviewKeys.all });
    },
  });
}

export function useMoveHousehold() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<Household>,
    ApiError,
    { id: string; payload: MoveHouseholdPayload; idempotencyKey?: string }
  >({
    mutationFn: ({ id, payload, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return householdApi.move(id, payload, { idempotencyKey: key });
    },
    onSuccess: (_response, { id }) => {
      queryClient.invalidateQueries({ queryKey: householdKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: householdKeys.lists() });
      // Invalidate residents since their RT/RW location moved with the household
      queryClient.invalidateQueries({ queryKey: ['residents'] });
      queryClient.invalidateQueries({ queryKey: overviewKeys.all });
    },
  });
}

export function useDissolveHousehold() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<Household>,
    ApiError,
    { id: string; payload?: DissolveHouseholdPayload }
  >({
    mutationFn: ({ id, payload }) => householdApi.dissolve(id, payload),
    onSuccess: (_response, { id }) => {
      queryClient.invalidateQueries({ queryKey: householdKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: householdKeys.lists() });
      queryClient.invalidateQueries({ queryKey: ['residents'] });
      queryClient.invalidateQueries({ queryKey: overviewKeys.all });
    },
  });
}
