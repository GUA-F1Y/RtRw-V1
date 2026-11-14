/**
 * Idempotency Key Management for Mutation Commands
 *
 * Requirements & Lifecycle Rules:
 * 1. The Idempotency-Key represents ONE distinct user command attempt.
 * 2. It must be generated ONCE at the start of a command execution (e.g. form submit).
 * 3. It must NOT be regenerated if a network error occurs or if TanStack Query retries internally.
 * 4. Required endpoints:
 *    - POST /api/v1/residents (Registration)
 *    - POST /api/v1/residents/{id}/relocate (Relocation)
 *    - POST /api/v1/households (Household Creation)
 *    - POST /api/v1/households/{id}/members (Member Addition)
 *    - POST /api/v1/households/{id}/move (Administrative Move)
 * 5. Replay behavior:
 *    - 200/201 with 'Idempotent-Replayed: true' header indicates a safe replayed response.
 *    - 409 'idempotency_request_in_progress' indicates a previous concurrent execution is in progress.
 *    - 422 'idempotency_key_reused' indicates the key was previously used with a different payload.
 *    Both 409 and 422 are normalized via ApiError and must NEVER be silently retried with a new key.
 */

export function generateIdempotencyKey(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  // Fallback UUID v4 generator if crypto.randomUUID is not available
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}
