import { authKeys } from '../../features/auth/api/authKeys.ts';
import { hierarchyKeys } from '../../features/hierarchy/api/hierarchyKeys.ts';
import { householdKeys } from '../../features/households/api/householdKeys.ts';
import { overviewKeys } from '../../features/overview/api/overviewKeys.ts';
import { residentKeys } from '../../features/residents/api/residentKeys.ts';

/**
 * Centralized Query Keys Registry
 */
export const queryKeys = {
  auth: authKeys,
  hierarchy: hierarchyKeys,
  residents: residentKeys,
  households: householdKeys,
  overview: overviewKeys,
};
