# Authentication Session, Route Guards & Application Shell

## 1. Overview & Architectural Boundaries

This document specifies the authentication session management, route protection mechanics, and responsive application shell for the RT/RW Community Governance Platform.

The architecture enforces strict separation between:
1. **Authentication Session:** Identity, account status, token lifecycle (`AuthContext` / `AuthProvider`).
2. **Authorization & Security:** Enforced authoritatively by Laravel backend Policies (`ResidentPolicy`, `HouseholdPolicy`, `OrganizationPolicy`).
3. **Navigation & UI Hints:** Navigation items and scope representations (`navCapabilities.ts`) serve purely as UX hints. Hiding an item or disabling a button is NOT a security mechanism.
4. **Data Isolation:** Complete TanStack Query cache purges on logout to prevent multi-account data leakage.

```text
HTTP Request / Stored Token
           ↓
AuthProvider (Session State Machine: unauthenticated | loading | authenticated | account_inactive | error)
           ↓
Route Guards (ProtectedRoute | PublicRoute | AuthLoadingBoundary)
           ↓
Application Shell (AppShell: AppSidebar [RW Context] + AppHeader [Scope Indicator] + Main Outlet)
           ↓
Feature Pages (HierarchyPage, AppOverviewPlaceholder)
```

---

## 2. Authentication State Machine

Located in `frontend/src/app/providers/AuthContext.ts` and `frontend/src/app/providers/AuthProvider.tsx`:

```typescript
export type AuthStatus =
  | 'idle'
  | 'loading'
  | 'authenticated'
  | 'unauthenticated'
  | 'account_inactive'
  | 'error';
```

### State Transitions

```text
[App Launch]
      ↓
Has token in localStorage?
  ├── NO  ──→ unauthenticated
  └── YES ──→ loading
                ↓
            GET /api/v1/auth/me
                ├── 200 OK ─────────────────→ authenticated (User + activeScope hydrated)
                ├── 401 Unauthorized ───────→ unauthenticated (Token cleared)
                ├── 403 account_inactive ──→ account_inactive (Redirected to /inactive)
                └── 5xx / Network Error ────→ error (Token preserved, no infinite loop)

[User Action: Login]
      ↓
POST /api/v1/auth/login
  ├── 200 OK ─────────────────→ authenticated (Token stored, current user cached)
  ├── 401 Invalid Credentials → unauthenticated (Form error displayed via ApiError)
  └── 403 account_inactive ──→ account_inactive (Redirected to /inactive)

[User Action: Logout]
      ↓
POST /api/v1/auth/logout (Best-effort token revocation on backend)
      ↓
setAuthToken(null)
      ↓
queryClient.clear() (Wipes all resident/household/user cached data)
      ↓
unauthenticated (Redirected to /login)
```

---

## 3. Route Guards & Fallback Boundaries

Located in `frontend/src/app/router/`:

| Guard / Boundary | Path | Behavior |
|---|---|---|
| **`ProtectedRoute`** | `/app/*` | If `isLoading`, renders `<AuthLoadingBoundary />` (preventing flash-of-unauthorized-content). If `isAccountInactive`, navigates to `/inactive`. If `!isAuthenticated`, navigates to `/login` preserving `location.state.from`. If authenticated, renders child `<Outlet />`. |
| **`PublicRoute`** | `/login` | If `isLoading`, renders `<AuthLoadingBoundary />`. If `isAuthenticated`, navigates to `/app`. If unauthenticated, renders login page. |
| **`AuthLoadingBoundary`** | Transient | Full-screen sleek loading indicator preventing layout shifts or flashing protected content before authentication is verified. |
| **`AccountInactivePage`** | `/inactive` | Dedicated screen when account status is suspended/inactive. Details why access is restricted and provides a direct "Sign Out" button to return to `/login`. |
| **`ForbiddenPage`** | `/unauthorized` | 403 Forbidden screen when an authenticated user attempts to reach a resource outside their organizational scope. |
| **`NotFoundPage`** | `*` | 404 Route boundary with return link to `/app`. |

---

## 4. Application Shell & Responsive Layout

Located in `frontend/src/app/layouts/`:

### 4.1 Structural Components
* **`AppShell`**: Top-level container. Coordinates the fixed desktop sidebar, mobile drawer overlay, top header, and scrollable content area.
* **`AppHeader`**:
  * Displays active organizational context (e.g., "RW 01 • RT 03").
  * Displays authenticated actor's name, email, and primary role badge.
  * Provides a responsive hamburger menu button on mobile/tablet screens.
  * Houses the primary "Sign Out" button with loading indicator.
* **`AppSidebar`**:
  * RW-centric branding: "RT/RW Platform • Multi-Tier Governance".
  * Primary navigation list with active route indicators and status badges.
  * Bottom container showing the actor's current authority scope level.
  * Fully responsive: acts as a slide-out drawer on `< lg` screen sizes with dark backdrop dismiss.

### 4.2 RW-Centric Navigation Model
Platform hierarchy is explicitly structured around:
$$\text{RW} \longrightarrow \text{RT} \longrightarrow \text{KK / Household} \longrightarrow \text{Warga / Resident}$$

The shell establishes RW-level governance as a first-class citizen rather than treating the app as a disconnected RT tool.

Structural Navigation Catalog (`APP_NAVIGATION_ITEMS` in `navCapabilities.ts`):
1. **RW Overview (`/app`)**: Active default authenticated landing.
2. **Hierarchy & Units (`/app/hierarchy`)**: Active organizational tree viewer.
3. **Resident Directory (`/app/residents`)**: Badge `Phase 14`, `disabled: true` (prevents broken routes).
4. **Household Registry (`/app/households`)**: Badge `Phase 14`, `disabled: true`.
5. **Iuran & Finance (`/app/finance`)**: Badge `Planned`, `disabled: true`.
6. **RW Governance (`/app/governance`)**: Badge `Planned`, `disabled: true`, requires RW scope.

---

## 5. Scope Context & User Identity

* **`User != Resident`**: The authenticated `User` object holds authentication credentials, account status, and an array of `Membership` items linking the user to organizations, RWs, or RTs.
* Full resident and household records are **never** bundled into the auth session.
* **Active Scope Derivation (`deriveActiveScope`)**: Automatically determines the primary scope context based on active membership priority (`organization` > `rw` > `rt`).
* Scope changes cannot be claimed arbitrarily by the client. Any future scope switcher must only activate authorized memberships returned by the server.

---

## 6. Multi-Account Cache Security

### Risk Model
When User A (e.g. Ketua RW with sensitive access) uses a shared computer and logs out, subsequent user B (e.g. standard resident or bendahara) logging in on the same browser must NOT inherit or view cached sensitive data (such as unmasked NIK or KK numbers) from TanStack Query's memory cache.

### Enforcement
In `AuthProvider.logout`:
```typescript
const logout = async () => {
  try {
    await authApi.logout();
  } finally {
    setAuthToken(null);
    setUser(null);
    queryClient.clear(); // Complete purge of all cached query and mutation entries
    setStatus('unauthenticated');
  }
};
```
Verified in [frontend/tests/cacheSecurity.test.ts](file:///c:/Users/USER/Desktop/RTRW/frontend/tests/cacheSecurity.test.ts):
* User A data cached -> `queryClient.clear()` -> User A cache confirmed empty -> User B logs in -> User B cannot access any cached query data from User A.

---

## 7. Token Storage & Security Observations

### Current Mechanism
* Stored in browser `localStorage` under key `rtrw_auth_token`.
* Transported via HTTP header: `Authorization: Bearer <token>`.

### Production Hardening Recommendations
1. **XSS Exposure:** `localStorage` is accessible to JavaScript. In production environments where high-privilege administrative functions operate, transitioning to HttpOnly SameSite Secure cookies backed by Sanctum CSRF protection (`/sanctum/csrf-cookie`) is recommended.
2. **Token Leakage Defenses:**
   * Tokens are never logged to console or included in error objects.
   * Tokens are never appended to URLs or stored in query keys.
   * Tokens are not passed through component prop drilling.

---

## 8. Verification Baseline

* **Frontend Test Suite:** 44 passed across 10 test suites (0 failures).
* **Frontend Linter (`oxlint`):** 0 warnings, 0 errors across 69 files.
* **Frontend Production Build (`tsc -b && vite build`):** Built cleanly in 419ms.
* **Backend Regression Suite (`php artisan test`):** 223 tests passed, 1,231 assertions passed, 0 failures, 0 errors.
