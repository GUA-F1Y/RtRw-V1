import type { ApiErrorDetail, ApiErrorResponse } from './types.ts';

export class ApiError extends Error {
  public readonly status: number;
  public readonly code: string;
  public readonly requestId?: string;
  public readonly errors?: Record<string, string[]>;

  constructor(status: number, detail: ApiErrorDetail) {
    super(detail.message || 'An unexpected API error occurred.');
    this.name = 'ApiError';
    this.status = status;
    this.code = detail.code || 'unknown_error';
    this.requestId = detail.request_id;
    this.errors = detail.errors;

    // Maintain prototype chain
    Object.setPrototypeOf(this, new.target.prototype);
  }

  public isValidationError(): boolean {
    return this.status === 422 || this.code === 'validation_failed';
  }

  public isUnauthorized(): boolean {
    return this.status === 401;
  }

  public isForbidden(): boolean {
    return this.status === 403;
  }

  public isNotFound(): boolean {
    return this.status === 404;
  }

  public isConflict(): boolean {
    return this.status === 409;
  }

  public isCsrfMismatch(): boolean {
    return this.status === 419;
  }

  public isIdempotencyConflict(): boolean {
    return (
      this.code === 'idempotency_request_in_progress' ||
      this.code === 'idempotency_key_reused' ||
      this.code === 'idempotency_key_required'
    );
  }

  public isScopeMismatch(): boolean {
    return this.code === 'resident_scope_mismatch';
  }

  public getFirstError(field?: string): string | null {
    if (!this.errors) return null;
    if (field) {
      const fieldErrors = this.errors[field];
      return fieldErrors && fieldErrors.length > 0 ? fieldErrors[0] : null;
    }
    const firstKey = Object.keys(this.errors)[0];
    if (firstKey && this.errors[firstKey]?.length) {
      return this.errors[firstKey][0];
    }
    return null;
  }
}

// Backwards-compatibility alias
export const ApiClientError = ApiError;

export async function parseApiError(response: Response): Promise<ApiError> {
  const status = response.status;
  let detail: ApiErrorDetail = {
    code: status === 404 ? 'not_found' : status === 403 ? 'forbidden' : status === 401 ? 'unauthenticated' : status === 419 ? 'csrf_token_mismatch' : 'http_error',
    message: response.statusText || 'An unexpected error occurred.',
  };

  const contentType = response.headers.get('content-type') || '';
  if (contentType.includes('application/json')) {
    try {
      const body = (await response.json()) as ApiErrorResponse;
      if (body?.error && typeof body.error === 'object') {
        detail = {
          code: body.error.code || detail.code,
          message: body.error.message || detail.message,
          request_id: body.error.request_id,
          errors: body.error.errors,
        };
      }
    } catch {
      // In case of unparseable JSON, use default detail
    }
  }

  // Extract X-Request-Id header if request_id not inside body
  if (!detail.request_id) {
    const headerReqId = response.headers.get('X-Request-Id') || response.headers.get('x-request-id');
    if (headerReqId) {
      detail.request_id = headerReqId;
    }
  }

  return new ApiError(status, detail);
}
