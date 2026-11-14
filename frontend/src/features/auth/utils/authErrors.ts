import { ApiError } from '../../../lib/api/errors.ts';

export interface FormattedAuthError {
  message: string;
  code?: string;
  status?: number;
  requestId?: string;
  fieldErrors?: Record<string, string[]>;
}

/**
 * Maps ApiError and unknown errors into user-friendly presentation messages.
 * Does not expose raw database errors or stack traces.
 */
export function formatAuthError(error: unknown): FormattedAuthError {
  if (error instanceof ApiError) {
    let message = error.message;

    switch (error.code) {
      case 'invalid_credentials':
        message = 'Invalid email or password. Please verify your credentials.';
        break;
      case 'account_inactive':
        message = 'This account is inactive or suspended. Please contact your RW administrator.';
        break;
      case 'validation_failed':
        message = error.message || 'Please check the required fields below.';
        break;
      case 'unauthenticated':
        message = 'Your session has expired. Please sign in again.';
        break;
      case 'forbidden':
        message = 'You do not have permission to access this resource.';
        break;
      case 'http_error':
        if (error.status === 429) {
          message = 'Too many attempts. Please wait a moment and try again.';
        } else if (error.status >= 500) {
          message = 'A server error occurred. Please try again later.';
        }
        break;
      default:
        // Use normalized message from ApiError
        break;
    }

    return {
      message,
      code: error.code,
      status: error.status,
      requestId: error.requestId,
      fieldErrors: error.errors,
    };
  }

  if (error instanceof Error) {
    if (error.name === 'AbortError') {
      return { message: 'The request was cancelled.' };
    }
    return { message: error.message || 'An unexpected connection error occurred.' };
  }

  return { message: 'An unexpected error occurred. Please try again.' };
}
