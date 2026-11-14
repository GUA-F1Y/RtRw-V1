import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { formatAuthError } from '../src/features/auth/utils/authErrors.ts';
import { ApiError } from '../src/lib/api/errors.ts';

describe('Auth Error Presentation Formatter', () => {
  it('maps invalid_credentials into clear user guidance', () => {
    const error = new ApiError(401, {
      code: 'invalid_credentials',
      message: 'Invalid credentials provided.',
      request_id: 'req-auth-1',
    });

    const formatted = formatAuthError(error);
    assert.equal(formatted.message, 'Invalid email or password. Please verify your credentials.');
    assert.equal(formatted.code, 'invalid_credentials');
    assert.equal(formatted.status, 401);
    assert.equal(formatted.requestId, 'req-auth-1');
  });

  it('maps account_inactive into administrator contact guidance', () => {
    const error = new ApiError(403, {
      code: 'account_inactive',
      message: 'This account is not active.',
      request_id: 'req-auth-2',
    });

    const formatted = formatAuthError(error);
    assert.equal(
      formatted.message,
      'This account is inactive or suspended. Please contact your RW administrator.'
    );
    assert.equal(formatted.code, 'account_inactive');
    assert.equal(formatted.status, 403);
  });

  it('preserves validation field errors without exposing SQL traces', () => {
    const error = new ApiError(422, {
      code: 'validation_failed',
      message: 'Validation failed.',
      errors: {
        email: ['The email must be a valid email address.'],
        password: ['The password field is required.'],
      },
    });

    const formatted = formatAuthError(error);
    assert.equal(formatted.fieldErrors?.email?.[0], 'The email must be a valid email address.');
    assert.equal(formatted.fieldErrors?.password?.[0], 'The password field is required.');
  });

  it('handles unexpected 500 server errors cleanly', () => {
    const error = new ApiError(500, {
      code: 'http_error',
      message: 'Internal Server Error',
    });

    const formatted = formatAuthError(error);
    assert.equal(formatted.message, 'A server error occurred. Please try again later.');
  });

  it('handles non-ApiError generic errors', () => {
    const generic = new Error('Network timeout');
    const formatted = formatAuthError(generic);
    assert.equal(formatted.message, 'Network timeout');

    const unknown = formatAuthError('Something broke');
    assert.equal(formatted.message, 'Network timeout');
    assert.equal(unknown.message, 'An unexpected error occurred. Please try again.');
  });
});
