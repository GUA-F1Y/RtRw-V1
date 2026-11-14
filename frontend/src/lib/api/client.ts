import { ApiError, ApiClientError, parseApiError } from './errors.ts';
import { buildQueryString } from './queryParams.ts';
import type { ApiSuccessResponse, RequestOptions } from './types.ts';

export { ApiClientError, ApiError };

const RAW_BASE_URL =
  typeof import.meta !== 'undefined' && import.meta.env?.VITE_API_BASE_URL
    ? (import.meta.env.VITE_API_BASE_URL as string)
    : 'http://localhost:8000/api/v1';

// Normalize base URL so it always ends with /api/v1 without trailing slash
function resolveBaseUrl(raw: string): string {
  const trimmed = raw.replace(/\/+$/, '');
  if (trimmed.endsWith('/api/v1')) {
    return trimmed;
  }
  return `${trimmed}/api/v1`;
}

export const API_BASE_URL = resolveBaseUrl(RAW_BASE_URL);

export const AUTH_TOKEN_KEY = 'rtrw_auth_token';

export function getAuthToken(): string | null {
  try {
    return localStorage.getItem(AUTH_TOKEN_KEY);
  } catch {
    return null;
  }
}

export function setAuthToken(token: string | null): void {
  try {
    if (token) {
      localStorage.setItem(AUTH_TOKEN_KEY, token);
    } else {
      localStorage.removeItem(AUTH_TOKEN_KEY);
    }
  } catch {
    // LocalStorage may fail in restricted/private contexts
  }
}

/**
 * Single canonical HTTP transport abstraction for all API requests.
 */
export async function apiRequest<T>(
  endpoint: string,
  options: RequestOptions = {}
): Promise<ApiSuccessResponse<T>> {
  const { body, params, idempotencyKey, signal, headers: customHeaders, ...restOptions } = options;

  const queryString = buildQueryString(params);
  const cleanEndpoint = endpoint.replace(/^\/+/, '');
  const url = `${API_BASE_URL}/${cleanEndpoint}${queryString}`;

  const headers = new Headers(customHeaders || {});
  headers.set('Accept', 'application/json');

  const token = getAuthToken();
  if (token && !headers.has('Authorization')) {
    headers.set('Authorization', `Bearer ${token}`);
  }

  if (idempotencyKey) {
    headers.set('Idempotency-Key', idempotencyKey);
  }

  let requestBody: BodyInit | null | undefined = undefined;

  if (body !== undefined && body !== null) {
    if (body instanceof FormData) {
      requestBody = body;
    } else {
      headers.set('Content-Type', 'application/json');
      requestBody = JSON.stringify(body);
    }
  }

  const response = await fetch(url, {
    ...restOptions,
    headers,
    body: requestBody,
    signal,
  });

  if (!response.ok) {
    if (response.status === 401) {
      setAuthToken(null);
    }
    const apiError = await parseApiError(response);
    throw apiError;
  }

  const requestIdHeader = response.headers.get('X-Request-Id') || response.headers.get('x-request-id') || undefined;

  if (response.status === 204) {
    return {
      data: null as unknown as T,
      meta: requestIdHeader ? { request_id: requestIdHeader } : undefined,
    };
  }

  const contentType = response.headers.get('content-type') || '';
  if (contentType.includes('application/json')) {
    const json = (await response.json()) as ApiSuccessResponse<T>;
    if (requestIdHeader && json.meta && !json.meta.request_id) {
      json.meta.request_id = requestIdHeader;
    }
    return json;
  }

  return {
    data: null as unknown as T,
    meta: requestIdHeader ? { request_id: requestIdHeader } : undefined,
  };
}

export const apiClient = {
  get: <T>(endpoint: string, options?: Omit<RequestOptions, 'body'>) =>
    apiRequest<T>(endpoint, { ...options, method: 'GET' }),

  post: <T>(endpoint: string, body?: unknown, options?: Omit<RequestOptions, 'body'>) =>
    apiRequest<T>(endpoint, { ...options, method: 'POST', body }),

  put: <T>(endpoint: string, body?: unknown, options?: Omit<RequestOptions, 'body'>) =>
    apiRequest<T>(endpoint, { ...options, method: 'PUT', body }),

  patch: <T>(endpoint: string, body?: unknown, options?: Omit<RequestOptions, 'body'>) =>
    apiRequest<T>(endpoint, { ...options, method: 'PATCH', body }),

  delete: <T>(endpoint: string, options?: RequestOptions) =>
    apiRequest<T>(endpoint, { ...options, method: 'DELETE' }),
};

// Aliased for compatibility with existing features
export const api = apiClient;
