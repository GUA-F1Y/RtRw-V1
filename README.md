# RT/RW Community Administration & Governance Platform

A production-grade, multi-tier administration and governance platform built with a **Laravel 13 Backend** (Modular Monolith) and a **React 19 + TypeScript Frontend** (Vite SPA).

---

## 1. Vision & Core Philosophy

This platform is **not** designed as an "RT application with an RW dashboard glued on top". It fundamentally models civic community governance through a multi-tier organizational hierarchy:

$$\mathbf{RW} \longrightarrow \mathbf{RT} \longrightarrow \mathbf{KK} \longrightarrow \mathbf{Warga}$$

* **RW (Rukun Warga):** Coordination, governance, policy approval, program consolidation, shared asset management, and cross-unit monitoring.
* **RT (Rukun Tetangga):** Operational administrative unit responsible for local resident registries, dues collection, community letters, and neighborhood operations.
* **KK (Kartu Keluarga):** Household grouping unit attached to an operational RT.
* **Warga (Resident):** Individual citizen and end-user.

---

## 2. Technology Stack

### Backend
* **Framework:** Laravel 13
* **Language:** PHP 8.3+
* **Database:** PostgreSQL (UUID primary keys, relational integrity, composite foreign keys, append-only triggers, JSONB)
* **Authentication:** Laravel Sanctum (API Bearer Tokens)
* **Audit Logging:** Custom `AuditLog` domain model backed by PostgreSQL append-only trigger
* **Filesystem:** Centralized `FileStorage` abstraction with private disk protection
* **Testing:** PHPUnit / Pest running directly on PostgreSQL test database

### Frontend
* **Framework:** React 19 + TypeScript
* **Build Tool:** Vite
* **Routing:** React Router v7
* **Server State:** TanStack Query v5
* **Validation:** Zod
* **Styling:** Tailwind CSS v4

---

## 3. Repository Structure

```
rt-rw-platform/
├── backend/
│   ├── app/
│   │   ├── Domain/
│   │   │   ├── Identity/         # User model, AccountStatus enum
│   │   │   ├── Organization/     # Organization, Rw, Rt, Membership models
│   │   │   ├── Authorization/    # Role, Permission, ScopeRef, AccessResolver, ScopedQuery
│   │   │   ├── Audit/            # AuditLog (append-only trigger), AuditLogger
│   │   │   ├── File/             # StoredFile, Attachment, FileStorage service
│   │   │   └── Notification/     # DomainNotification queued base class
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/ # Auth, Org, Rw, Rt, Membership, File, Audit
│   │   │   ├── Requests/         # Form validation requests
│   │   │   └── Resources/        # Consistent API JSON resources
│   │   ├── Policies/             # Scoped policies for all domain models
│   │   └── Support/
│   │       ├── Api/              # Standard JSON envelopes & exception rendering
│   │       ├── Http/             # Request correlation & security headers
│   │       └── Idempotency/      # EnsureIdempotency middleware & keys
│   ├── database/
│   │   ├── migrations/           # 11 PostgreSQL schema migrations
│   │   ├── factories/            # Factories for all domain models
│   │   └── seeders/              # RolePermissionSeeder, DatabaseSeeder
│   ├── routes/
│   │   ├── api.php               # Versioned API routes (/api/v1/...)
│   │   └── console.php           # Queue, token, and idempotency cleanup schedules
│   ├── tests/                    # 30 automated feature tests against PostgreSQL
│   ├── .env.example
│   └── composer.json
│
├── frontend/
│   ├── src/
│   │   ├── app/
│   │   │   ├── config/           # Environment validation
│   │   │   ├── providers/        # AuthProvider, QueryProvider
│   │   │   └── router/           # AppLayout, ProtectedRoute, routes
│   │   ├── features/
│   │   │   ├── auth/             # LoginForm, LoginPage, authApi
│   │   │   └── organization/     # HierarchyTree, HierarchyPage, orgApi
│   │   ├── components/
│   │   │   ├── ui/               # Button, Card, Badge
│   │   │   └── feedback/         # Alert, LoadingSpinner
│   │   ├── lib/
│   │   │   └── api/              # Centralized fetch client & error normalization
│   │   └── types/                # Strict TypeScript contracts matching backend
│   ├── .env.example
│   ├── package.json
│   └── vite.config.ts
│
├── docs/
│   ├── architecture.md           # System topology, modular monolith, concurrency
│   ├── authorization.md          # Multi-tier scope model & access resolution
│   ├── api-conventions.md        # JSON envelopes, error codes, request correlation
│   └── testing.md                # Automated test suites & verification instructions
│
└── README.md
```

---

## 4. Local Development Setup

### Prerequisites
* PHP 8.3+ with `pdo_pgsql`, `mbstring`, `openssl`, `curl` extensions enabled
* PostgreSQL 16+ running on `127.0.0.1:5432`
* Composer 2.7+
* Node.js 20+ & npm

### Backend Setup
1. Enter backend directory:
   ```bash
   cd backend
   ```
2. Copy environment file:
   ```bash
   cp .env.example .env
   ```
3. Generate application encryption key:
   ```bash
   php artisan key:generate
   ```
4. Create PostgreSQL databases:
   ```sql
   CREATE DATABASE rtrw;
   CREATE DATABASE rtrw_test;
   ```
5. Run migrations & reference data seeder:
   ```bash
   php artisan migrate
   php artisan db:seed --class=RolePermissionSeeder
   ```
6. Start backend development server:
   ```bash
   php artisan serve --port=8000
   ```

### Frontend Setup
1. Enter frontend directory:
   ```bash
   cd frontend
   ```
2. Copy environment configuration:
   ```bash
   cp .env.example .env
   ```
3. Install dependencies:
   ```bash
   npm install
   ```
4. Start development server:
   ```bash
   npm run dev
   ```
   The frontend runs at `http://localhost:5173`.

---

## 5. Authentication & Authorization Approach

### Authentication Flow
1. Client submits credentials to `POST /api/v1/auth/login`.
2. Backend verifies hash, asserts account is active (`AccountStatus::Active`), generates a Sanctum Bearer token, logs an audit entry, and returns the token with user profile and active memberships.
3. Protected endpoints enforce `auth:sanctum` and `active_account` middleware.

### Scoped Authorization Flow
Authority is never evaluated using arbitrary client-provided parameters. It traverses the verified scope path:

$$\text{User} \longrightarrow \text{Membership} \longrightarrow \text{ScopeRef} \longrightarrow \text{Role} \longrightarrow \text{Permission}$$

* An RW user can view and manage their RW and all child RTs within it.
* A user in RW 05 cannot access RW 06 data (`403 Forbidden`).
* An RT user in RT 01 cannot access RT 02 data even inside the same RW (`403 Forbidden`).
* Client-supplied `scope_id` or `scope_type` values are resolved against database records via `ScopeResolver` before checking permissions with `AccessResolver`.

---

## 6. Testing & Quality Verification

### Run Backend Tests (Against PostgreSQL)
```bash
cd backend
php artisan test
```
*Current test suite status:* **30 passed tests (124 assertions)** covering Authentication, AuthorizationScope, DatabaseIntegrity, Idempotency, FileStorage, and ApiEnvelope.

### Run Frontend Build & Type Check
```bash
cd frontend
npm run build
```
*Current build status:* **0 type errors**, production assets bundled cleanly in 1.37s.

---

## 7. Current Project Scope & Roadmap

### Completed in this Foundation Phase (Phases A through N):
- [x] Environment inspection and tool discovery
- [x] Laravel 13 Modular Monolith backend initialized
- [x] React 19 + TypeScript + Vite frontend initialized
- [x] PostgreSQL database schema with 11 migrations (UUIDs, FKs, append-only trigger)
- [x] Laravel Sanctum Bearer token authentication
- [x] Scoped authorization architecture (`ScopeRef`, `AccessResolver`, `ScopedQuery`)
- [x] Append-only audit logging system (`AuditLog`, `AuditLogger`)
- [x] Centralized file storage abstraction (`FileStorage`, `StoredFile`)
- [x] Queue & scheduler maintenance foundation
- [x] Standardized API response envelopes & centralized exception handling
- [x] 30 automated feature tests passing with 124 assertions
- [x] Full architecture and API documentation

### Future Business Module Roadmap (Deferred to Subsequent Phases):
1. **Resident Registry:** Warga profile management, NIK/KK verification, identity document attachments.
2. **Household Management:** KK grouping, family members, residence status (permanent vs tenant).
3. **Community Finance:** Dues ledger, citizen billing, automated invoice generation, payment proof upload.
4. **Administration & Letters:** Digital administrative cover letters (Surat Pengantar RT/RW), approval chains.
5. **Community Governance:** Resident complaints (Pengaduan), announcements, voting/polling, community events.
