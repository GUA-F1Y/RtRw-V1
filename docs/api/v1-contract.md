# API v1 Contract & Resource Architecture Specification

This document is the authoritative **API v1 Contract** for the RT/RW Community Administration & Governance Platform. It defines the serialization boundaries, privacy tiers, request/response envelopes, filtering/sorting/pagination mechanics, mutation payloads, endpoint catalog, and security boundaries.

Controllers, routes, and FormRequests must adhere strictly to the contracts specified herein.

---

## 1. API Versioning

* All domain and administrative endpoints are explicitly versioned under the `/api/v1/` prefix.
* Unversioned routes (`/api/...`) are strictly prohibited for new domains.
* Backward-incompatible changes require a new API version prefix (`/api/v2/`).

---

## 2. Base Path & Transport

* **Base Path:** `/api/v1`
* **Transport:** HTTPS (TLS 1.3 recommended)
* **Default Content-Type:** `application/json`
* **Accepted Content-Type:** `application/json` (except multipart file uploads at `/api/v1/files`)

---

## 3. Success Envelope

The success envelope follows the platform's canonical `ApiResponse` implementation. Do not construct ad-hoc envelopes.

### 3.1 Single Resource Representation
```json
{
  "data": {
    "id": "01924b12-9c9e-716b-9c3f-8461abde5001",
    "name": "Ahmad Dahlan",
    "status": "ACTIVE"
  },
  "meta": {
    "request_id": "01924b12-9c9e-716b-9c3f-8461abde5002"
  }
}
```

### 3.2 Collection / Paginated Representation
```json
{
  "data": [
    {
      "id": "01924b12-9c9e-716b-9c3f-8461abde5001",
      "name": "Ahmad Dahlan",
      "status": "ACTIVE"
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 42,
      "last_page": 3,
      "has_more": true
    },
    "request_id": "01924b12-9c9e-716b-9c3f-8461abde5002"
  }
}
```

### 3.3 Empty / 204 No Content
Mutation endpoints that perform pure deletions or actions without return representation return `204 No Content` with an empty body.

---

## 4. Error Envelope

The error envelope is centrally rendered by `ApiExceptionRenderer`. Internal exception details, stack traces, and raw SQL queries are never exposed to the client, regardless of `APP_DEBUG`.

```json
{
  "error": {
    "code": "resident_scope_mismatch",
    "message": "RT '01924b12-9c9e-716b-9c3f-8461abde5001' does not exist or has invalid hierarchy.",
    "request_id": "01924b12-9c9e-716b-9c3f-8461abde5002",
    "errors": {
      "rt_id": [
        "The selected rt_id is invalid."
      ]
    }
  }
}
```

* `code`: Machine-readable, lowercase snake_case error string.
* `message`: Human-readable summary suitable for user presentation.
* `request_id`: Correlated request tracking ID.
* `errors`: Field-level validation error dictionary (present on 422 or when validation fails; omitted when empty).

---

## 5. Request Correlation (`X-Request-Id`)

1. If the client sends an `X-Request-Id` header (valid UUIDv4/v7 or alphanumeric string up to 128 chars), the platform reuses it.
2. If omitted, `AssignRequestId` middleware generates a UUIDv7.
3. The ID is injected into `meta.request_id` (success), `error.request_id` (error), the response header `X-Request-Id`, and all database audit logs.

---

## 6. Authentication

* **Mechanism:** Laravel Sanctum Bearer Token.
* **Header:** `Authorization: Bearer <token>`
* Authenticated endpoints enforce `auth:sanctum` and `active_account` middleware.
* Expired or missing token results in `401 unauthenticated`.
* Suspended or deactivated account results in `403 forbidden`.

---

## 7. Authorization & Scope Hierarchy

Authority follows the verified organizational tree:

$$\text{Organization} \longrightarrow \text{RW} \longrightarrow \text{RT} \longrightarrow \text{Household (KK)} \longrightarrow \text{Resident (Warga)}$$

* Authority is evaluated via `AccessResolver` and Laravel Policies (`ResidentPolicy`, `HouseholdPolicy`).
* An actor scoped to RT 01 cannot view or modify RT 02 (`403 Forbidden`).
* An actor scoped to RW 01 can view and manage all child RTs within RW 01.
* Query string parameters like `?rt_id=...` are **query filters**, not authorization mechanisms. The query is always constrained within the actor's authorized scopes.

---

## 8. Resource Authorization & Serialization Boundary

> [!IMPORTANT]
> **API Resources MUST NOT perform authorization.**  
> API Resources are strictly presentation and serialization boundaries. They do NOT invoke `Gate::allows()`, do NOT issue database queries, and do NOT read sensitivity toggles from HTTP requests.

The canonical flow is:
```text
Controller / Handler
       ↓
Policy check (e.g. ResidentPolicy::representationTier())
       ↓
Selects PrivacyTier (Directory | Normal | Sensitive)
       ↓
Resource serialization (ResidentResource::at($model, $tier))
```

---

## 9. Privacy Tiers

### 9.1 Resident Tiers

| Tier | Policy Gate Required | Serialized Fields | NIK / Phone Visibility |
|---|---|---|---|
| **Directory** | `resident.directory_view` | `id`, `representation`, `name`, `gender`, `age`, `residency_type`, `status`, `organization_id`, `rw_id`, `rt_id` | **NOT EXPOSED** |
| **Normal** | `resident.view` | Directory fields + `birth_place`, `birth_date`, `marital_status`, `occupation`, `education`, `current_household_id`, timestamps, eager relations | **MASKED** (`320101******0005`, `+628*******890`) |
| **Sensitive** | `resident.view` + `resident.view_sensitive` | Normal fields + `religion`, `blood_type`, `user_id` | **FULL RAW VALUE** |

* Default tier when constructed without parameter: **Directory** (secure default).
* Sensitive override via query parameter (`?sensitive=true`) is **strictly forbidden and ignored**.

### 9.2 Household Tiers

| Tier | Policy Gate Required | Serialized Fields | KK Number Visibility |
|---|---|---|---|
| **Directory (Safe)** | Internal / Child Reference | `id`, `representation`, `status`, unit IDs, `active_members_count` | **NOT EXPOSED** |
| **Normal** | `household.view` | Safe fields + `address`, `notes`, timestamps, eager relations (`active_head`, `members`) | **MASKED** (`320101******0001`) |
| **Sensitive** | `household.view` + `household.view_sensitive` | Normal fields with unmasked KK number | **FULL RAW VALUE** |

* Bendahara roles hold `household.view` but **NEVER** `household.view_sensitive`.
* Default tier when constructed without parameter: **Directory (Safe)**.

---

## 10. Resource Schemas

### 10.1 ResidentResource Schema
```json
{
  "id": "01924b12-9c9e-716b-9c3f-8461abde5001",
  "representation": "normal",
  "name": "Ahmad Dahlan",
  "gender": "MALE",
  "age": 36,
  "residency_type": "PERMANENT",
  "status": "ACTIVE",
  "organization_id": "01924b12-9c9e-716b-9c3f-8461abde5000",
  "rw_id": "01924b12-9c9e-716b-9c3f-8461abde5002",
  "rt_id": "01924b12-9c9e-716b-9c3f-8461abde5003",
  "birth_place": "Jakarta",
  "birth_date": "1990-05-12",
  "marital_status": "MARRIED",
  "occupation": "Karyawan Swasta",
  "education": "SARJANA",
  "current_household_id": "01924b12-9c9e-716b-9c3f-8461abde5010",
  "nik": "320101******0005",
  "nik_masked": true,
  "phone": "+628*******890",
  "phone_masked": true,
  "created_at": "2026-01-15T08:00:00.000000Z",
  "updated_at": "2026-01-15T08:00:00.000000Z"
}
```

*Note: In `sensitive` tier, `nik` and `phone` are raw, `nik_masked` and `phone_masked` are `false`, and `religion`, `blood_type`, and `user_id` are included.*

### 10.2 HouseholdResource Schema
```json
{
  "id": "01924b12-9c9e-716b-9c3f-8461abde5010",
  "representation": "normal",
  "status": "ACTIVE",
  "organization_id": "01924b12-9c9e-716b-9c3f-8461abde5000",
  "rw_id": "01924b12-9c9e-716b-9c3f-8461abde5002",
  "rt_id": "01924b12-9c9e-716b-9c3f-8461abde5003",
  "active_members_count": 4,
  "kk_number": "320101******0001",
  "kk_number_masked": true,
  "address": "Jl. Kenanga No. 10",
  "notes": "Rumah tinggal milik sendiri",
  "created_at": "2026-01-15T08:00:00.000000Z",
  "updated_at": "2026-01-15T08:00:00.000000Z"
}
```

---

## 11. Household Member Summary Contract

`HouseholdMemberResource` embeds **only a minimal, privacy-safe resident summary**. It does NOT recursively embed `ResidentResource` or leak demographic/sensitive identifiers.

```json
{
  "id": "01924b12-9c9e-716b-9c3f-8461abde5020",
  "household_id": "01924b12-9c9e-716b-9c3f-8461abde5010",
  "resident_id": "01924b12-9c9e-716b-9c3f-8461abde5001",
  "relationship": "KEPALA_KELUARGA",
  "is_active": true,
  "joined_at": "2026-01-01",
  "left_at": null,
  "left_reason": null,
  "resident": {
    "id": "01924b12-9c9e-716b-9c3f-8461abde5001",
    "name": "Ahmad Dahlan"
  }
}
```

---

## 12. Residency Movement Ledger Contract (Read-Only)

Residency movement is an immutable ledger. Mutations are not accepted on historical records.

```json
{
  "id": "01924b12-9c9e-716b-9c3f-8461abde5030",
  "representation": "normal",
  "resident_id": "01924b12-9c9e-716b-9c3f-8461abde5001",
  "movement_type": "TRANSFER_INTERNAL",
  "effective_date": "2026-02-01",
  "from_scope": {
    "organization_id": "01924b12-9c9e-716b-9c3f-8461abde5000",
    "rw_id": "01924b12-9c9e-716b-9c3f-8461abde5002",
    "rt_id": "01924b12-9c9e-716b-9c3f-8461abde5003",
    "household_id": "01924b12-9c9e-716b-9c3f-8461abde5010",
    "rw_number": 1,
    "rt_number": 1
  },
  "to_scope": {
    "organization_id": "01924b12-9c9e-716b-9c3f-8461abde5000",
    "rw_id": "01924b12-9c9e-716b-9c3f-8461abde5002",
    "rt_id": "01924b12-9c9e-716b-9c3f-8461abde5004",
    "household_id": "01924b12-9c9e-716b-9c3f-8461abde5015",
    "rw_number": 1,
    "rt_number": 2
  },
  "created_at": "2026-02-01T10:00:00.000000Z"
}
```

*In `sensitive` tier (officer with `resident.view_sensitive`), the following fields are additionally included:*
* `reference_letter_number`: e.g. `"SK/2026/01"`
* `notes`: e.g. `"Pindah rumah antar RT"`
* `attachment_file_id`: UUID
* `recorded_by`: `{ "id": "...", "name": "Ketua RT 01" }`

---

## 13. Resident Status History Ledger Contract (Read-Only)

```json
{
  "id": "01924b12-9c9e-716b-9c3f-8461abde5040",
  "representation": "normal",
  "resident_id": "01924b12-9c9e-716b-9c3f-8461abde5001",
  "previous_status": "PROSPECTIVE",
  "new_status": "ACTIVE",
  "effective_at": "2026-01-15T10:00:00.000000Z",
  "created_at": "2026-01-15T10:00:00.000000Z"
}
```

*In `sensitive` tier, restricted metadata is included:*
* `reason`: e.g. `"Verifikasi berkas kependudukan disetujui"`
* `reference_letter_number`: `"VERIF/001"`
* `attachment_file_id`: UUID
* `recorded_by`: `{ "id": "...", "name": "Ketua RT 01" }`

---

## 14. Pagination Contract

* **Parameter:** `?page=1&per_page=15`
* **Default `per_page`:** 15
* **Minimum `per_page`:** 1
* **Maximum `per_page`:** 100 (Values exceeding 100 are rejected or clamped to 100)
* Arbitrary values like `per_page=999999` are strictly blocked.

---

## 15. Filtering Contract

Filters act as query constraints **within** the authenticated user's authorized scope.

### 15.1 Resident Filters
* `status`: `PROSPECTIVE`, `ACTIVE`, `INACTIVE`, `MOVED`, `DECEASED`
* `rt_id`: UUID (must be within actor's scope coverage)
* `rw_id`: UUID (must be within actor's scope coverage)
* `household_id`: UUID
* `gender`: `MALE`, `FEMALE`
* `residency_type`: `PERMANENT`, `TEMPORARY`, `SEASONAL`
* `marital_status`: `SINGLE`, `MARRIED`, `DIVORCED`, `WIDOWED`
* `q`: Text search term

*Raw NIK and phone number are **NOT** filterable via query parameters.*

### 15.2 Household Filters
* `status`: `ACTIVE`, `HEAD_VACANT`, `MOVED`, `DISSOLVED`, `ARCHIVED`
* `rt_id`: UUID (must be within actor's scope coverage)
* `rw_id`: UUID (must be within actor's scope coverage)
* `head_availability`: `present`, `vacant`
* `q`: Text search term (address)

---

## 16. Search Contract (`?q=...`)

* **Query Parameter:** `?q=<term>`
* **Minimum Length:** 3 characters
* **Maximum Length:** 100 characters
* **Matching Strategy:** Case-insensitive partial substring match (`ILIKE %term%`).
* **Resident Search Fields:** `name` only (NIK and phone are NOT searchable via general search).
* **Household Search Fields:** `address` only (KK number is NOT searchable via general search).
* Wildcard characters (`%`, `_`) are automatically escaped.

---

## 17. Sorting Contract (`?sort=...`)

* **Syntax:** `?sort=field` (ascending) or `?sort=-field` (descending).
* Arbitrary column names or SQL expressions are strictly rejected with `422 validation_failed`.

### 17.1 Resident Sort Allowlist
* `name` (default: `name` ascending)
* `created_at`
* `status`

### 17.2 Household Sort Allowlist
* `-created_at` (default: `created_at` descending)
* `address`
* `status`

*Sensitive or masked fields (`nik`, `phone`, `kk_number`, `birth_date`) are **NOT** sortable to prevent data inference via ordering.*

---

## 18. Relationship Inclusion Contract (`?include=...`)

Clients may request related resources using comma-separated tokens. Arbitrary Eloquent relationship names are prohibited.

* **Maximum include depth:** 2 levels
* **Maximum include tokens per request:** 5

### 18.1 Supported Resident Includes
| Token | Eloquent Relation | Minimum Required Caller Tier |
|---|---|---|
| `organization` | `organization` | `Directory` |
| `rw` | `rw` | `Directory` |
| `rt` | `rt` | `Directory` |
| `household` | `currentHousehold` | `Normal` |
| `memberships` | `householdMemberships` | `Normal` |
| `movements` | `residencyMovements` | `Normal` |
| `status_history` | `statusHistory` | `Normal` |

### 18.2 Supported Household Includes
| Token | Eloquent Relation | Minimum Required Caller Tier |
|---|---|---|
| `organization` | `organization` | `Normal` |
| `rw` | `rw` | `Normal` |
| `rt` | `rt` | `Normal` |
| `head` | `activeHead` | `Normal` |
| `head.resident` | `activeHead.resident` | `Normal` |
| `members` | `activeMembers` | `Normal` |
| `members.resident` | `activeMembers.resident` | `Normal` |

---

## 19. Mutation Payload Contracts

### 19.1 Resident Registration (`POST /api/v1/residents`)
Registers a new resident into a target RT. Initial status is **always `PROSPECTIVE`**.

**Accepted Payload:**
```json
{
  "nik": "3201011205900005",
  "name": "Ahmad Dahlan",
  "gender": "MALE",
  "birth_place": "Jakarta",
  "birth_date": "1990-05-12",
  "religion": "ISLAM",
  "marital_status": "MARRIED",
  "rt_id": "01924b12-9c9e-716b-9c3f-8461abde5003",
  "blood_type": "O",
  "occupation": "Wiraswasta",
  "education": "SARJANA",
  "phone": "+6281234567890",
  "residency_type": "PERMANENT"
}
```

**Explicitly Rejected Fields:**
* `organization_id`, `rw_id`: Authoritatively derived from RT record in database.
* `status`: Registration must always produce `PROSPECTIVE`. Verification is required to activate.
* `user_id`: User account linking is decoupled to prevent account linkage escalation.
* `current_household_id`: Household membership is handled via household commands.

### 19.2 Resident Profile Update (`PATCH /api/v1/residents/{id}`)
Updates mutable demographic fields only.

**Accepted Payload:**
```json
{
  "occupation": "PNS",
  "education": "MAGISTER",
  "phone": "+6281299998888",
  "marital_status": "MARRIED"
}
```

**Explicitly Rejected Fields:**
* `nik`, `name`, `gender`, `birth_place`, `birth_date`: Immutable identity fields.
* `status`: Lifecycle transitions require explicit domain commands.
* `rt_id`, `rw_id`, `organization_id`: Relocation operations require `POST /relocate`.
* `current_household_id`: Requires household member actions.

### 19.3 Resident Verification (`POST /api/v1/residents/{id}/verify`)
Transitions resident status `PROSPECTIVE -> ACTIVE`.

**Accepted Payload:**
```json
{
  "notes": "Berkas KTP dan KK fisik telah diperiksa",
  "reference_letter_number": "VERIF/2026/001",
  "attachment_file_id": "01924b12-9c9e-716b-9c3f-8461abde5099"
}
```
*Rejected:* `status`, `new_status` (Target status is always `ACTIVE`).

### 19.4 Resident Status Change (`POST /api/v1/residents/{id}/status`)
Records civil status transition.

**Accepted Payload:**
```json
{
  "intent": "MARK_DECEASED",
  "reason": "Meninggal dunia karena sakit di RS",
  "reference_letter_number": "SKM/2026/012",
  "attachment_file_id": "01924b12-9c9e-716b-9c3f-8461abde5098",
  "effective_at": "2026-03-01T04:30:00Z"
}
```
*Allowed Intents:* `DEACTIVATE`, `REACTIVATE`, `MARK_DECEASED`.  
*Rejected:* `status`, `new_status`, `previous_status`.

### 19.5 Resident Relocation (`POST /api/v1/residents/{id}/relocate`)
Executes internal transfer or external move.

**Accepted Payload (Internal Transfer):**
```json
{
  "movement_type": "TRANSFER_INTERNAL",
  "target_rt_id": "01924b12-9c9e-716b-9c3f-8461abde5004",
  "target_household_id": "01924b12-9c9e-716b-9c3f-8461abde5015",
  "effective_date": "2026-04-01",
  "reference_letter_number": "SKP/2026/04",
  "attachment_file_id": "01924b12-9c9e-716b-9c3f-8461abde5097",
  "notes": "Pindah domisili ke RT tetangga"
}
```
*Rejected:* `from_organization_id`, `from_rw_id`, `from_rt_id`, `from_household_id` (always read from database).

### 19.6 Household Creation (`POST /api/v1/households`)
**Accepted Payload:**
```json
{
  "kk_number": "3201011205900001",
  "address": "Jl. Mawar No. 5",
  "rt_id": "01924b12-9c9e-716b-9c3f-8461abde5003",
  "notes": "Keluarga baru",
  "initial_head_resident_id": "01924b12-9c9e-716b-9c3f-8461abde5001"
}
```
*Rejected:* `organization_id`, `rw_id`, `status`.

### 19.7 Household Member Addition (`POST /api/v1/households/{id}/members`)
**Accepted Payload:**
```json
{
  "resident_id": "01924b12-9c9e-716b-9c3f-8461abde5002",
  "relationship": "ISTRI",
  "joined_at": "2026-01-01"
}
```
*Rejected:* `is_active`, `is_head`, `left_at`, `left_reason`.

### 19.8 Change Household Head (`POST /api/v1/households/{id}/change-head`)
**Accepted Payload:**
```json
{
  "new_head_resident_id": "01924b12-9c9e-716b-9c3f-8461abde5002",
  "old_head_new_relationship": "FAMILI_LAIN"
}
```

### 19.9 Household Administrative Move (`POST /api/v1/households/{id}/move`)
**Accepted Payload:**
```json
{
  "move_type": "INTER_RT",
  "target_rt_id": "01924b12-9c9e-716b-9c3f-8461abde5004",
  "new_address": "Jl. Melati No. 8",
  "notes": "Relokasi keluarga ke RT 02"
}
```

---

## 20. Idempotency Contract

Unsafe mutation endpoints support idempotency through the platform's `EnsureIdempotency` middleware.

* **Header:** `Idempotency-Key` (Alphanumeric + `-_:.`, 8–128 characters)
* **Required on:** `POST /residents`, `POST /residents/{id}/relocate`, `POST /households`, `POST /households/{id}/members`, `POST /households/{id}/move`.
* **Optional on:** Other POST/PATCH mutations.
* **Scope:** Scoped per authenticated user.
* **Replay Behavior:** Identical key + identical payload replayed within TTL returns stored status and body with header `Idempotent-Replayed: true`.
* **Conflict Behavior:**
  * Same key + different payload: `422 idempotency_key_reused`
  * Same key + previous request in progress: `409 idempotency_request_in_progress` (Header `Retry-After: 1`)

---

## 21. HTTP Status Semantics

| Status | Code | Platform Semantic |
|---|---|---|
| `200` | OK | Successful GET, PATCH, or non-creating command with response body |
| `201` | Created | Successful resource creation via POST |
| `204` | No Content | Successful operation with empty body |
| `400` | Bad Request | Missing required header (e.g. Idempotency-Key) or malformed syntax |
| `401` | Unauthenticated | Missing, expired, or invalid Sanctum token |
| `403` | Forbidden | Authenticated user lacks permission or scope authority |
| `404` | Not Found | Resource does not exist or falls outside user's readable scope |
| `409` | Conflict | State conflict, duplicate unique key, or in-progress idempotency |
| `419` | CSRF Mismatch | Session / CSRF token mismatch |
| `422` | Unprocessable Content | Form validation error or domain business invariant violation |
| `429` | Too Many Requests | Rate limit exceeded |
| `500` | Server Error | Unhandled server exception (opaque message to client) |

---

## 22. Endpoint Catalog & Implementation Status

> [!NOTE]
> **Phase 10 & 11 Status:**
> - Read-Only endpoints for Residents and Households (`GET /api/v1/residents`, `GET /api/v1/residents/{id}`, `GET /api/v1/households`, `GET /api/v1/households/{id}`) are **FULLY IMPLEMENTED** (Phase 10) with authoritative ScopedQuery, policy-driven privacy tiers, and strict query whitelists.
> - Mutation endpoints for Residents and Households (13 operations: Registration, Profile Update, Verification, Relocation, Status Transition, Household Removal, Household Creation, Dwelling Update, Add Member, Remove Member, Change Head, Administrative Move, Dissolution) are **FULLY IMPLEMENTED** (Phase 11) wired directly to Domain Actions, with explicit authenticated actor propagation, FormRequest-level forbidden field rejection via `MutationContract`, dual-scope authorization policies, and idempotency guarantees.
> - Deferred operations (`MOVE_IN_EXTERNAL`, external household moves, and registration user linking) remain strictly deferred.

### 22.1 Residents

| Method | Endpoint | Required Permission | Resource Scope | Sensitive Permission | Status |
|---|---|---|---|---|---|
| `GET` | `/api/v1/residents` | `resident.directory_view` | RT / RW query | `resident.view`, `resident.view_sensitive` | **Implemented (Phase 10)** |
| `POST` | `/api/v1/residents` | `resident.register` | Target RT | — | **Implemented (Phase 11)** |
| `GET` | `/api/v1/residents/{id}` | `resident.view` | Resident RT | `resident.view_sensitive` | **Implemented (Phase 10)** |
| `PATCH` | `/api/v1/residents/{id}` | `resident.update` | Resident RT | — | **Implemented (Phase 11)** |
| `POST` | `/api/v1/residents/{id}/verify` | `resident.verify` | Resident RT | — | **Implemented (Phase 11)** |
| `POST` | `/api/v1/residents/{id}/relocate` | `resident.relocate` | Source RT + Target RT | `resident.view_sensitive` | **Implemented (Phase 11)** |
| `POST` | `/api/v1/residents/{id}/status` | `resident.change_status` | Resident RT | — | **Implemented (Phase 11)** |
| `POST` | `/api/v1/residents/{id}/remove-from-household` | `resident.remove_from_household` | Resident RT | — | **Implemented (Phase 11)** |

### 22.2 Households

| Method | Endpoint | Required Permission | Resource Scope | Sensitive Permission | Status |
|---|---|---|---|---|---|
| `GET` | `/api/v1/households` | `household.view` | RT / RW query | `household.view_sensitive` | **Implemented (Phase 10)** |
| `POST` | `/api/v1/households` | `household.create` | Target RT | — | **Implemented (Phase 11)** |
| `GET` | `/api/v1/households/{id}` | `household.view` | Household RT | `household.view_sensitive` | **Implemented (Phase 10)** |
| `PATCH` | `/api/v1/households/{id}` | `household.update` | Household RT | — | **Implemented (Phase 11)** |
| `POST` | `/api/v1/households/{id}/members` | `household.add_member` | Household RT + Resident RT | — | **Implemented (Phase 11)** |
| `DELETE`| `/api/v1/households/{id}/members/{residentId}` | `household.remove_member` | Household RT + Resident RT | — | **Implemented (Phase 11)** |
| `POST` | `/api/v1/households/{id}/change-head` | `household.change_head` | Household RT | — | **Implemented (Phase 11)** |
| `POST` | `/api/v1/households/{id}/move` | `household.move` | Source RT + Target RT | — | **Implemented (Phase 11)** |
| `POST` | `/api/v1/households/{id}/dissolve` | `household.dissolve` | Household RT | — | **Implemented (Phase 11)** |

### 22.3 Overview Read Model

| Method | Endpoint | Required Permission | Resource Scope | Sensitive Permission | Status |
|---|---|---|---|---|---|
| `GET` | `/api/v1/overview` | `resident.directory_view` & `household.view` | Server-Derived Primary Scope (Org/RW/RT) | N/A (Aggregates Only) | **Implemented (Phase 14)** |

---

## 23. Deferred Operations

The following capabilities exist or are anticipated in the domain, but are **explicitly deferred** from API v1 until cross-tenant governance is established:

1. **`MOVE_IN_EXTERNAL` (Resident):** Inbound migration of an external resident into an organization requires verified civil release letters from external subdistricts and multi-tenant authorization. Deferred from public API v1.
2. **`MOVE_OUT_EXTERNAL` (Household Relocation):** Moving an entire household outside the organization requires external destination address validation schemas not yet specified.
3. **User Account Self-Linking:** Direct linking of a resident ID to an arbitrary `user_id` during registration is deferred to prevent privilege escalation and sensitive identity exposure.

---

## 24. Security Audit & Protection Matrix

| Vulnerability Vector | Defense Mechanism |
|---|---|
| **IDOR (Insecure Direct Object Reference)** | Every route parameter model is verified against the actor's authorized scope via `ScopeRef` and Policies. |
| **Mass-Assignment / Over-Posting** | Strict FormRequests will reject unexpected keys (`MutationContract::unexpectedFields()`). Scope and status columns are never mass-assignable. |
| **Sensitive Field Leakage** | NIK and KK numbers are masked in `Normal` tier and omitted in `Directory` tier. Serializers do not depend on frontend masking. |
| **Arbitrary Sort / Filter Injection** | Strict whitelists in `QueryContract`. Unwhitelisted fields produce `422 validation_failed`. |
| **Arbitrary Relationship Traversal** | `?include=` accepts only predefined tokens with bounded depth ($\le 2$). |
| **Cross-RT / Cross-RW Tampering** | Relocation and household moving enforce dual-scope policy authorization (`source` and `target` scopes must both be authorized). |
| **User/Resident Account-Link Abuse** | `user_id` is excluded from registration contracts. |
| **Overview Query Manipulation** | `GET /api/v1/overview` prohibits client-supplied `organization_id`, `rw_id`, or `rt_id` query parameters (rejected with 422). |

---

## 25. Overview Read Model Specification (`GET /api/v1/overview`)

### 25.1 Purpose & Role
`GET /api/v1/overview` is the authoritative aggregate read endpoint designed to power the community dashboard (RW / RT / Organization Overview). It provides pre-aggregated demographic and household statistics directly from PostgreSQL, strictly eliminating the need for client-side calculation or collection hydration.

### 25.2 Scope Derivation & Semantics
- **No Client-Controlled Scope:** The endpoint does not accept `organization_id`, `rw_id`, or `rt_id` query parameters. Any attempt to supply them is rejected with `422 validation_failed`.
- **Server Authority:** The server derives the target scope strictly from the authenticated user's active membership hierarchy:
  1. `ScopeLevel::Organization` (highest authority - aggregates entire organization)
  2. `ScopeLevel::Rw` (RW authority - aggregates authorized RW and its child RTs)
  3. `ScopeLevel::Rt` (RT authority - aggregates only the authorized RT)
- **RT Breakdown Boundary:**
  - For RW- and Organization-scoped actors, `rt_breakdown` lists child active RT units within the authorized scope.
  - For RT-scoped actors, `rt_breakdown` contains strictly the authorized RT. Neighboring RTs are never leaked or fabricated.

### 25.3 Permission & Authorization
- Enforced via `OverviewPolicy::view`.
- Requires an active account (`AccountStatus::Active`).
- Requires that the actor holds `resident.directory_view` (or `resident.view`) and `household.view` covering the resolved target scope.
- Actors without active memberships are denied with `403 Forbidden`.

### 25.4 Response Structure & Representation
```json
{
  "data": {
    "scope": {
      "level": "RW",
      "id": "01924b12-9c9e-716b-9c3f-8461abde5001",
      "name": "RW 01"
    },
    "population": {
      "total": 1000,
      "active": 950,
      "prospective": 20,
      "inactive": 15,
      "moved": 10,
      "deceased": 5
    },
    "households": {
      "active": 320,
      "head_assigned": 315,
      "head_vacant": 5,
      "moved": 10,
      "dissolved": 8
    },
    "rt_breakdown": [
      {
        "rt_id": "01924b12-9c9e-716b-9c3f-8461abde5010",
        "rt_code": "01",
        "resident_count": 135,
        "household_count": 42,
        "head_vacant_household_count": 1
      }
    ]
  },
  "meta": {
    "request_id": "01924b12-9c9e-716b-9c3f-8461abde5002"
  }
}
```

### 25.5 Metric Definitions
- **`population.total`:** All resident records registered in the authorized scope across all 5 lifecycle statuses (`ACTIVE`, `PROSPECTIVE`, `INACTIVE`, `MOVED`, `DECEASED`). It is not active-only.
- **`population.active`:** Residents with `status = 'ACTIVE'`.
- **`population.prospective`:** Residents with `status = 'PROSPECTIVE'`.
- **`population.inactive`:** Residents with `status = 'INACTIVE'`.
- **`population.moved`:** Residents with `status = 'MOVED'`.
- **`population.deceased`:** Residents with `status = 'DECEASED'`. Deceased individuals are explicitly excluded from `active`.
- **`households.active`:** Households with `status = 'ACTIVE'`.
- **`households.head_assigned`:** Households with `status = 'ACTIVE'` that have an active head membership (`relationship = 'KEPALA_KELUARGA' AND is_active = true`).
- **`households.head_vacant`:** Households with `status = 'HEAD_VACANT'`.
- **`households.moved`:** Households with `status = 'MOVED'`.
- **`households.dissolved`:** Households with `status = 'DISSOLVED'`. Historical head memberships of dissolved households never count toward `head_assigned`.
- **`rt_breakdown[]`:**
  - `rt_id`: Canonical RT UUID.
  - `rt_code`: Zero-padded string code (e.g. `'01'`).
  - `resident_count`: Total resident records in the RT.
  - `household_count`: Active households in the RT.
  - `head_vacant_household_count`: Head-vacant households in the RT.

### 25.6 Privacy & Data Minimization
- The endpoint contains only aggregated counts and identifiers.
- It strictly forbids exposure of NIK, KK numbers, resident names, phone numbers, physical addresses, or collections of individual resident/household objects.

### 25.7 Performance Guarantees
- Aggregation is performed directly inside PostgreSQL using `COUNT(*) FILTER (...)` and `GROUP BY rt_id`.
- Fixed query count: executes exactly 4 SQL queries regardless of the number of child RTs (O(1) query complexity, zero N+1 queries).
- Zero hydration of `Resident` or `Household` Eloquent models.

