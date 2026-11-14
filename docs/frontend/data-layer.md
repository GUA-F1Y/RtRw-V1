# Frontend API Foundation & Data Layer Architecture

## 1. Overview & Data Flow

This document specifies the canonical frontend data layer for the RT/RW Community Governance Platform. The architecture enforces strict separation between visual presentation, caching/state management, typed client transport, and the Laravel backend REST API.

Canonical Request/Response Flow:

```text
React Component (UI)
        ↓
Data Hook (e.g. useResidents, useRegisterResident)
        ↓
TanStack Query (Cache, Invalidation, Cancellation)
        ↓
Typed Feature API Client (residentApi, householdApi)
        ↓
Canonical HTTP Client (apiClient / apiRequest)
        ↓
Fetch Transport (Sanctum Bearer Token, Idempotency-Key, AbortSignal)
        ↓
Laravel API /api/v1/...
```

No React UI component may call `fetch(...)` directly, craft ad-hoc endpoint URLs, or manually parse backend error responses.

---

## 2. HTTP Client Abstraction

Located in `frontend/src/lib/api/`:

* `client.ts`: Canonical transport abstraction `apiRequest` and `apiClient` (`get`, `post`, `patch`, `delete`).
* `errors.ts`: Canonical `ApiError` class and `parseApiError` response parser.
* `queryParams.ts`: Canonical query parameter serializer with URL encoding and undefined/null omission.
* `idempotency.ts`: UUID generator and lifecycle documentation for idempotent commands.
* `schemas.ts`: Zod validation schemas for API envelopes, pagination, and domain enums.
* `types.ts`: Core transport types (`ApiSuccessResponse`, `ApiErrorResponse`, `PaginationMeta`, `RequestOptions`).

### 2.1 Base URL & API Versioning

* The API base URL is resolved from Vite environment variable `VITE_API_BASE_URL` with a safe development fallback to `http://localhost:8000/api/v1`.
* All API endpoints are centralized under the `/api/v1/` prefix. Feature clients provide relative resource paths (e.g., `residents`, `households`) and never concatenate `/api/v1` manually.

### 2.2 Canonical Request Defaults

* Headers:
  * `Accept: application/json`
  * `Content-Type: application/json` (automatically set only when a JSON body is present and not `FormData`).
  * `Authorization: Bearer <token>` (injected when authenticated token is stored).
  * `Idempotency-Key: <uuid>` (injected on required mutation commands).
* Signals:
  * Native `AbortSignal` is supported on all client operations for instant cancellation.

---

## 3. Authentication Transport

* **Mechanism:** Laravel Sanctum Bearer Token.
* **Storage:** Dedicated `rtrw_auth_token` key in `localStorage` accessed via `getAuthToken()` and `setAuthToken()`.
* **Token Clearing:** Receiving a `401 Unauthorized` response immediately clears the stored token.
* **Identity vs Resident Profile:** The authenticated user (`User`) represents identity, account status, and role/membership references. A `User` is decoupled from a `Resident` profile; sensitive demographic resident data is never stored in auth session state.

---

## 4. Response Envelope & Error Handling

### 4.1 Success Envelope

All successful responses conform to the backend contract:

```typescript
export interface ApiSuccessResponse<T> {
  data: T;
  meta?: {
    request_id?: string;
    pagination?: PaginationMeta;
    [key: string]: unknown;
  };
}
```

* `204 No Content` mutations resolve to `{ data: null, meta: { request_id } }`.

### 4.2 Error Normalization (`ApiError`)

All HTTP errors normalize into a single canonical `ApiError` class:

```typescript
export class ApiError extends Error {
  public readonly status: number;
  public readonly code: string;
  public readonly requestId?: string;
  public readonly errors?: Record<string, string[]>;
}
```

Helper methods available on `ApiError`:
* `isValidationError()`: Returns true on 422 or `validation_failed`.
* `isUnauthorized()`: Returns true on 401.
* `isForbidden()`: Returns true on 403.
* `isNotFound()`: Returns true on 404.
* `isConflict()`: Returns true on 409.
* `isCsrfMismatch()`: Returns true on 419.
* `isIdempotencyConflict()`: Returns true on `idempotency_request_in_progress` (409) or `idempotency_key_reused` (422).
* `isScopeMismatch()`: Returns true on `resident_scope_mismatch`.
* `getFirstError(field?)`: Safely extracts the first validation error message.

---

## 5. Query Parameter Serialization

Query parameter serialization is centralized in `frontend/src/lib/api/queryParams.ts`:

* **Omission of Undefined/Null/Empty:** Keys with `undefined`, `null`, or `""` are omitted completely. They are never serialized as `"undefined"` or `"null"`.
* **Array Joining:** Array parameters (such as `include`) are serialized as comma-separated values (e.g. `include=household,movements`).
* **Booleans:** Serialized as `1` or `0`.
* **Encoding:** All parameter values are safely URL-encoded.

---

## 6. Privacy Tiers & Masked Values

The backend enforces three policy-driven representation tiers:
1. **Directory:** Public community safe fields (`id`, `name`, `gender`, `age`, `residency_type`, `status`, unit IDs).
2. **Normal:** Adds demographic fields; `nik`, `phone`, and `kk_number` are **masked** (e.g. `320101******0005`, `+628*******890`, `320101******0001`).
3. **Sensitive:** Unmasked full values (requires `resident.view_sensitive` / `household.view_sensitive`).

### Frontend Handling Rules:
* Sensitive fields in TypeScript interfaces are optional (`nik?: string`, `phone?: string`, `kk_number?: string`, `religion?: string`, `blood_type?: string`).
* Masked values are accepted as valid API strings. The frontend does not validate masked values with strict 16-digit regex.
* The frontend never attempts to unmask or reconstruct raw values from masked strings.
* Masked values must never be sent back to mutation endpoints as updated fields.

---

## 7. TanStack Query Configuration

Located in `frontend/src/app/query/queryClient.ts`:

### 7.1 Retry Policy
* **4xx Errors (400, 401, 403, 404, 409, 419, 422):** NEVER retried. These represent client errors, permission denials, state conflicts, or validation issues.
* **5xx Server Errors & Network Failures:** Retried up to a maximum of 2 times with backoff.
* **Mutations:** NEVER automatically retried (`retry: false`) to prevent accidental duplicate side effects.

### 7.2 Caching & Staleness
* Default query `staleTime`: 5 minutes.
* Inactive query garbage collection (`gcTime`): 15 minutes.
* `refetchOnWindowFocus`: disabled by default to maintain deterministic rendering.

---

## 8. Query Key Factory & Targeted Invalidation

Located in `frontend/src/features/residents/api/residentKeys.ts` and `frontend/src/features/households/api/householdKeys.ts`:

### 8.1 Key Factory Structure
* `residentKeys.all`: `['residents']`
* `residentKeys.lists()`: `['residents', 'list']`
* `residentKeys.list(params)`: `['residents', 'list', params]`
* `residentKeys.details()`: `['residents', 'detail']`
* `residentKeys.detail(id, includes)`: `['residents', 'detail', id, { includes }]`
* Equivalent structure exists for `householdKeys` and `authKeys`.

Query keys are parameter-aware so different filters/pages never collide in cache.

### 8.2 Targeted Cache Invalidation
Instead of wiping the entire query cache, mutations perform targeted invalidations:
* **Resident Registration:** Invalidates `residentKeys.lists()`.
* **Resident Profile Update / Verification / Status Change:** Invalidates `residentKeys.detail(id)` and `residentKeys.lists()`.
* **Resident Relocation:** Invalidates `residentKeys.detail(id)`, `residentKeys.lists()`, and `householdKeys.all`.
* **Household Creation:** Invalidates `householdKeys.lists()` and `residentKeys.all`.
* **Household Member Addition / Removal:** Invalidates `householdKeys.detail(id)`, `householdKeys.lists()`, and `residentKeys.detail(residentId)`.
* **Household Relocation / Dissolution:** Invalidates `householdKeys.detail(id)`, `householdKeys.lists()`, and `residentKeys.all`.

---

## 9. Idempotency Key Handling

Commands requiring an `Idempotency-Key` header:
1. `POST /api/v1/residents` (Registration)
2. `POST /api/v1/residents/{id}/relocate` (Relocation)
3. `POST /api/v1/households` (Creation)
4. `POST /api/v1/households/{id}/members` (Member Addition)
5. `POST /api/v1/households/{id}/move` (Administrative Move)

### Lifecycle Rules:
* An idempotency key represents **one user command attempt** (e.g. one button click or submission).
* The key must NOT be regenerated if a network glitch occurs or if TanStack Query retries internally.
* Replays (`200`/`201` with `Idempotent-Replayed: true`) are handled transparently.
* `409 idempotency_request_in_progress` and `422 idempotency_key_reused` are normalized as `ApiError` and must be presented directly to the user/UI without silent retry.

---

## 10. Mutation Contracts & Forbidden Fields

Payload interfaces strictly mirror allowed backend parameters. Forbidden fields are omitted from TypeScript interfaces so developers cannot accidentally send them:

* **Resident Payloads:**
  * Forbidden: `organization_id`, `rw_id`, `status`, `user_id`, `current_household_id`, `from_*`, `recorded_by`.
* **Household Payloads:**
  * Forbidden: `organization_id`, `rw_id`, `status`, `from_*`, `is_external_move`.

---

## 11. Deferred Operations

The following backend capabilities are explicitly deferred from API v1 and are NOT present in the frontend client:
1. `MOVE_IN_EXTERNAL` (Resident): Inbound external migration.
2. `MOVE_OUT_EXTERNAL` (Household): Relocating an entire household outside the organization.
3. User Account Self-Linking: Linking a resident profile to an arbitrary user ID during registration.
