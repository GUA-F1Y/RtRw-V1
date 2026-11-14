import type { Household } from '../../../types/household.ts';

/**
 * Determines which operational actions are available for a given household state.
 * UI affordance only — backend policies remain authoritative.
 */
export function getHouseholdAvailableActions(household: Household): {
  canEdit: boolean;
  canAddMember: boolean;
  canChangeHead: boolean;
  canMove: boolean;
  canDissolve: boolean;
} {
  const { status } = household;
  const isOperable = status === 'ACTIVE' || status === 'HEAD_VACANT';

  return {
    canEdit: isOperable,
    canAddMember: isOperable,
    canChangeHead: isOperable,
    canMove: isOperable,
    canDissolve: isOperable,
  };
}
