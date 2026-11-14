import { api } from '../../../lib/api/client.ts';
import type { Organization, RtUnit, RwUnit } from '../../../types/organization.ts';

/**
 * Hierarchy API client.
 * Thin wrappers that call the canonical API client.
 * No business logic – serialization only.
 */
export const hierarchyApi = {
  /**
   * Lists all organizations visible to the authenticated user.
   * Backend scopes by active membership.
   */
  getOrganizations: (opts?: { signal?: AbortSignal }): Promise<Organization[]> =>
    api.get<Organization[]>('organizations', { signal: opts?.signal }).then((r) => r.data),

  /**
   * Lists all RWs within an organization visible to the authenticated user.
   * Backend scopes by active membership.
   */
  getRws: (organizationId: string, opts?: { signal?: AbortSignal }): Promise<RwUnit[]> =>
    api
      .get<RwUnit[]>(`organizations/${organizationId}/rws`, { signal: opts?.signal })
      .then((r) => r.data),

  /**
   * Loads a single RW with embedded `rts` array.
   * Backend enforces view authorization.
   */
  getRwWithRts: (rwId: string, opts?: { signal?: AbortSignal }): Promise<RwUnit> =>
    api.get<RwUnit>(`rws/${rwId}`, { signal: opts?.signal }).then((r) => r.data),

  /**
   * Lists RTs within a RW.
   * Used as fallback if the RW detail response does not embed RTs.
   */
  getRts: (rwId: string, opts?: { signal?: AbortSignal }): Promise<RtUnit[]> =>
    api.get<RtUnit[]>(`rws/${rwId}/rts`, { signal: opts?.signal }).then((r) => r.data),
};
