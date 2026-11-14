import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { ApiError, parseApiError } from '../src/lib/api/errors.ts';

describe('ApiError and Error Parser', () => {
  it('normalizes backend error envelope into ApiError', () => {
    const error = new ApiError(422, {
      code: 'validation_failed',
      message: 'The given data was invalid.',
      request_id: 'req-12345',
      errors: {
        nik: ['The nik field is required.'],
        name: ['The name field is required.'],
      },
    });

    assert.equal(error.status, 422);
    assert.equal(error.code, 'validation_failed');
    assert.equal(error.message, 'The given data was invalid.');
    assert.equal(error.requestId, 'req-12345');
    assert.deepEqual(error.errors?.nik, ['The nik field is required.']);
    assert.equal(error.isValidationError(), true);
    assert.equal(error.getFirstError('nik'), 'The nik field is required.');
    assert.equal(error.getFirstError(), 'The nik field is required.');
  });

  it('correctly classifies HTTP status and domain error codes', () => {
    const unauthorized = new ApiError(401, { code: 'unauthenticated', message: 'Unauthenticated.' });
    assert.equal(unauthorized.isUnauthorized(), true);
    assert.equal(unauthorized.isForbidden(), false);

    const forbidden = new ApiError(403, { code: 'forbidden', message: 'Forbidden.' });
    assert.equal(forbidden.isForbidden(), true);

    const notFound = new ApiError(404, { code: 'not_found', message: 'Not found.' });
    assert.equal(notFound.isNotFound(), true);

    const conflict = new ApiError(409, { code: 'household_state_conflict', message: 'Conflict.' });
    assert.equal(conflict.isConflict(), true);

    const csrf = new ApiError(419, { code: 'csrf_token_mismatch', message: 'CSRF token mismatch.' });
    assert.equal(csrf.isCsrfMismatch(), true);

    const inProgress = new ApiError(409, {
      code: 'idempotency_request_in_progress',
      message: 'Request already in progress.',
    });
    assert.equal(inProgress.isIdempotencyConflict(), true);

    const reused = new ApiError(422, {
      code: 'idempotency_key_reused',
      message: 'Key reused with different payload.',
    });
    assert.equal(reused.isIdempotencyConflict(), true);

    const scopeMismatch = new ApiError(403, {
      code: 'resident_scope_mismatch',
      message: 'Scope mismatch.',
    });
    assert.equal(scopeMismatch.isScopeMismatch(), true);
  });

  it('parses JSON response into ApiError and extracts request_id header fallback', async () => {
    const mockResponse = new Response(
      JSON.stringify({
        error: {
          code: 'duplicate_nik',
          message: 'Resident with this NIK already exists.',
        },
      }),
      {
        status: 409,
        headers: {
          'Content-Type': 'application/json',
          'X-Request-Id': 'header-req-id-999',
        },
      }
    );

    const parsed = await parseApiError(mockResponse);
    assert.equal(parsed.status, 409);
    assert.equal(parsed.code, 'duplicate_nik');
    assert.equal(parsed.message, 'Resident with this NIK already exists.');
    assert.equal(parsed.requestId, 'header-req-id-999');
  });

  it('handles non-JSON or malformed error responses gracefully', async () => {
    const rawResponse = new Response('<html>502 Bad Gateway</html>', {
      status: 502,
      statusText: 'Bad Gateway',
      headers: {
        'Content-Type': 'text/html',
        'X-Request-Id': 'gateway-err-555',
      },
    });

    const parsed = await parseApiError(rawResponse);
    assert.equal(parsed.status, 502);
    assert.equal(parsed.code, 'http_error');
    assert.equal(parsed.message, 'Bad Gateway');
    assert.equal(parsed.requestId, 'gateway-err-555');
  });
});
