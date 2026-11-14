export const overviewKeys = {
  all: ['overview'] as const,
  current: () => [...overviewKeys.all, 'current'] as const,
};
