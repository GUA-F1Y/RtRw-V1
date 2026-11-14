/**
 * Canonical Query Parameter Serializer for API v1
 * - Omits undefined, null, and empty string values
 * - Serializes arrays as comma-separated values (e.g., include=household,movements)
 * - Properly handles booleans, numbers, and strings
 * - Encodes parameters cleanly using URLSearchParams
 */
export function buildQueryString(params?: Record<string, unknown>): string {
  if (!params || typeof params !== 'object') {
    return '';
  }

  const searchParams = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '') {
      continue;
    }

    if (Array.isArray(value)) {
      const validItems = value.filter(
        (item) => item !== undefined && item !== null && item !== ''
      );
      if (validItems.length > 0) {
        searchParams.set(key, validItems.map(String).join(','));
      }
      continue;
    }

    if (typeof value === 'boolean') {
      searchParams.set(key, value ? '1' : '0');
      continue;
    }

    searchParams.set(key, String(value));
  }

  const query = searchParams.toString();
  return query ? `?${query}` : '';
}
