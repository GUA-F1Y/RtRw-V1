import { useQuery, type UseQueryOptions } from '@tanstack/react-query';
import type { ApiError } from '../../../lib/api/errors.ts';
import type { Organization, RtUnit, RwUnit } from '../../../types/organization.ts';
import { hierarchyApi } from './hierarchyApi.ts';
import { hierarchyKeys } from './hierarchyKeys.ts';

/**
 * Fetches all organizations authorized for the current user.
 * Result is used to derive the org ID for downstream hierarchy queries.
 */
export function useOrganizations(
  options?: Omit<UseQueryOptions<Organization[], ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<Organization[], ApiError>({
    queryKey: hierarchyKeys.organizations(),
    queryFn: ({ signal }) => hierarchyApi.getOrganizations({ signal }),
    staleTime: 5 * 60_000,
    ...options,
  });
}

/**
 * Fetches RW units within an organization.
 * Disabled until organizationId is known.
 */
export function useRws(
  organizationId: string | null | undefined,
  options?: Omit<UseQueryOptions<RwUnit[], ApiError>, 'queryKey' | 'queryFn' | 'enabled'>
) {
  return useQuery<RwUnit[], ApiError>({
    queryKey: hierarchyKeys.rws(organizationId ?? ''),
    queryFn: ({ signal }) => hierarchyApi.getRws(organizationId!, { signal }),
    enabled: !!organizationId,
    staleTime: 5 * 60_000,
    ...options,
  });
}

/**
 * Fetches a single RW with embedded `rts` array.
 * This is the preferred query for hierarchy display:
 * - O(1) backend query
 * - RTs embedded in the same response (no separate fetch needed)
 */
export function useRwWithRts(
  rwId: string | null | undefined,
  options?: Omit<UseQueryOptions<RwUnit, ApiError>, 'queryKey' | 'queryFn' | 'enabled'>
) {
  return useQuery<RwUnit, ApiError>({
    queryKey: hierarchyKeys.rw(rwId ?? ''),
    queryFn: ({ signal }) => hierarchyApi.getRwWithRts(rwId!, { signal }),
    enabled: !!rwId,
    staleTime: 5 * 60_000,
    ...options,
  });
}

/**
 * Fetches RTs within an RW.
 * Used as fallback when RT data is not embedded in the RW response.
 */
export function useRts(
  rwId: string | null | undefined,
  options?: Omit<UseQueryOptions<RtUnit[], ApiError>, 'queryKey' | 'queryFn' | 'enabled'>
) {
  return useQuery<RtUnit[], ApiError>({
    queryKey: hierarchyKeys.rts(rwId ?? ''),
    queryFn: ({ signal }) => hierarchyApi.getRts(rwId!, { signal }),
    enabled: !!rwId,
    staleTime: 5 * 60_000,
    ...options,
  });
}
