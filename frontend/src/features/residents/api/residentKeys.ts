import type { ResidentIncludeToken, ResidentQueryParams } from '../../../types/resident';

export const residentKeys = {
  all: ['residents'] as const,
  lists: () => [...residentKeys.all, 'list'] as const,
  list: (params?: ResidentQueryParams) =>
    [...residentKeys.lists(), params ? { ...params } : {}] as const,
  details: () => [...residentKeys.all, 'detail'] as const,
  detail: (id: string, includes?: ResidentIncludeToken[] | string) =>
    [...residentKeys.details(), id, includes ? { includes } : {}] as const,
};
