# Testing Strategy & Verification Guide

## 1. Testing Philosophy

The test suite validates actual domain invariants directly against **PostgreSQL**:
- Database foreign keys, cascading rules, and composite unique constraints.
- Real PostgreSQL triggers (such as the append-only `activity_log_prevent_mutation` trigger).
- Sanctum token authentication and revocation.
- Organizational scope isolation (preventing cross-RW and cross-RT data leaks).
- Idempotency middleware execution and replay behavior.
- Private file upload validation, MIME detection, and streaming downloads.

## 2. Test Suites Overview

| Test Suite | Location | Assertions Verified |
| :--- | :--- | :--- |
| `AuthenticationTest` | `tests/Feature/AuthenticationTest.php` | 401 unauthenticated, valid token generation, invalid password rejection, suspended account blocking, `/api/v1/auth/me` profile retrieval, and token revocation on logout. |
| `AuthorizationScopeTest` | `tests/Feature/AuthorizationScopeTest.php` | RW scoping, nested RT visibility, cross-RW access rejection (403), cross-RT access rejection within same RW, client spoofing prevention, and scope-level mismatch rejection. |
| `ApiEnvelopeAndErrorTest` | `tests/Feature/ApiEnvelopeAndErrorTest.php` | JSON `{ data, meta }` envelope consistency, validation error structures (422), 404 envelopes, and `X-Request-Id` correlation propagation. |
| `DatabaseIntegrityTest` | `tests/Feature/DatabaseIntegrityTest.php` | PostgreSQL foreign key restrictions, duplicate RW numbers within an organization, duplicate RT numbers within an RW, and PostgreSQL append-only audit trigger preventing updates/deletes. |
| `IdempotencyTest` | `tests/Feature/IdempotencyTest.php` | Initial creation caching, exact replay with `Idempotent-Replayed: true`, and payload modification rejection (422 `idempotency_key_reused`). |
| `FileStorageTest` | `tests/Feature/FileStorageTest.php` | Scoped upload into private storage, rejection of uploads outside user's scope, and forbidden download access across scopes. |

## 3. Running Backend Tests

Run all tests:
```bash
cd backend
php artisan test
```

Run a specific suite:
```bash
php artisan test --filter=AuthorizationScopeTest
```

## 4. Running Frontend Quality Gates

Compile TypeScript and build production bundle:
```bash
cd frontend
npm run build
```

Run linter:
```bash
npm run lint
```
