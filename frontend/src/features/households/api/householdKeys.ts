import type { HouseholdIncludeToken, HouseholdQueryParams } from '../../../types/household';

export const householdKeys = {
  all: ['households'] as const,
  lists: () => [...householdKeys.all, 'list'] as const,
  list: (params?: HouseholdQueryParams) =>
    [...householdKeys.lists(), params ? { ...params } : {}] as const,
  details: () => [...householdKeys.all, 'detail'] as const,
  detail: (id: string, includes?: HouseholdIncludeToken[] | string) =>
    [...householdKeys.details(), id, includes ? { includes } : {}] as const,
};
