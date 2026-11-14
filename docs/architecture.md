# RT/RW Platform Architecture & Design System

## 1. Vision & Core Philosophy

This platform is **not** an "RT admin dashboard with an RW view glued on top". It is an **organization and governance platform** designed around multi-tier scoped authority:

$$\text{RW (Coordination/Governance)} \longrightarrow \text{RT (Operational Administration)} \longrightarrow \text{KK (Household)} \longrightarrow \text{Warga (Resident)}$$

* **RW (Rukun Warga):** Coordination, governance oversight, inter-RT approvals, monitoring, programs, shared assets, and consolidated reporting.
* **RT (Rukun Tetangga):** Operational unit responsible for resident registry, local dues collection, letters, and community operations.
* **KK (Kartu Keluarga):** Family grouping unit within an RT.
* **Warga (Resident):** Individual citizen/resident end-user identity.

## 2. System Topology

The platform strictly separates the **Laravel 13 Backend API** and the **React TypeScript SPA Frontend**:

```
+--------------------------------------------------------+
|               React 19 + TypeScript SPA                |
|  - Vite, React Router, TanStack Query, Zod, Tailwind   |
+--------------------------------------------------------+
                           |
                     REST API (v1)
              JSON Envelopes / Bearer Tokens
                           |
+--------------------------------------------------------+
|               Laravel 13 Modular Backend               |
|  - Sanctum API Tokens, Scoped Gates/Policies           |
|  - PostgreSQL Database (UUIDs, Foreign Keys, Triggers) |
|  - FileStorage Abstraction (Local/S3 interchangeable)   |
|  - Centralized Exception Handling & Audit Trails       |
+--------------------------------------------------------+
```

## 3. Backend Modular Monolith Structure

```
backend/app/
├── Domain/
│   ├── Identity/         # User model, AccountStatus enum, credentials
│   ├── Organization/     # Organization, Rw, Rt, Membership models
│   ├── Authorization/    # Role, Permission, ScopeRef, AccessResolver, ScopedQuery
│   ├── Audit/            # AuditLog (append-only PostgreSQL trigger), AuditLogger
│   ├── File/             # StoredFile, Attachment, FileStorage service
│   └── Notification/     # DomainNotification queued base class
├── Http/
│   ├── Controllers/Api/V1/ # Auth, Org, Rw, Rt, Membership, File, Audit
│   ├── Requests/         # Strongly-typed FormRequests (Login, CreateRt, etc.)
│   └── Resources/        # UserResource, RwResource, RtResource, etc.
├── Policies/             # OrganizationPolicy, RwPolicy, RtPolicy, MembershipPolicy, FilePolicy
└── Support/
    ├── Api/              # ApiResponse helper, ApiExceptionRenderer, Exceptions
    ├── Http/             # RequestContext (X-Request-Id), SecurityHeaders, ActiveAccount
    └── Idempotency/      # EnsureIdempotency middleware, IdempotencyKey model
```

## 4. Concurrency & Idempotency Safeguards

1. **Idempotency Keys:** Mutating POST endpoints (`/api/v1/rws/{rw}/rts`, `/api/v1/memberships`) support the `Idempotency-Key` header. Duplicate identical requests replay the saved JSON payload without re-executing writes (`Idempotent-Replayed: true`). Mismatched payloads with an identical key are rejected with `422 idempotency_key_reused`.
2. **Database-Enforced Invariants:**
   - Composite unique constraints: `(organization_id, number)` for RWs, `(rw_id, number)` for RTs.
   - Composite foreign keys: RTs must point to the valid parent RW within the same organization.
   - Append-only PostgreSQL triggers: `activity_log` rejects updates and deletes at the database engine level.
