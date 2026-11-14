/**
 * Hierarchy feature – query keys factory.
 * All keys are user-scoped via TanStack Query cache isolation.
 */
export const hierarchyKeys = {
  all: ['hierarchy'] as const,

  // Authenticated user's organizations list
  organizations: () => [...hierarchyKeys.all, 'organizations'] as const,

  // Single organization detail
  organization: (id: string) => [...hierarchyKeys.organizations(), id] as const,

  // RW list for an organization
  rws: (organizationId: string) => [...hierarchyKeys.all, 'rws', organizationId] as const,

  // Single RW detail (includes rts)
  rw: (rwId: string) => [...hierarchyKeys.all, 'rw', rwId] as const,

  // RT list for a given RW
  rts: (rwId: string) => [...hierarchyKeys.all, 'rts', rwId] as const,
};
