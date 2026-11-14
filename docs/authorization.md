# Multi-Tier Scoped Authorization Model

## 1. Overview & Chain of Authority

Authority in this platform **never** relies on a global, unstructured role string (e.g. `role: "admin"`). Authority is strictly derived through active organizational memberships:

$$\text{User} \longrightarrow \text{Membership} \longrightarrow \text{Scope (Organization / RW / RT)} \longrightarrow \text{Role} \longrightarrow \text{Permissions}$$

### Example Scenarios:

* **User A (Ketua RW 05):** Holds `ketua_rw` role scoped to `Organization X` + `RW 05`.
  * Can view RW 05 and all RTs inside RW 05 (`RT 01`, `RT 02`, etc.).
  * Can create and manage RTs inside RW 05.
  * **Cannot** view, access, or modify `RW 06` or `RW 06`'s RTs.
* **User B (Ketua RT 03):** Holds `ketua_rt` role scoped to `Organization X` + `RW 05` + `RT 03`.
  * Can view and operate within `RT 03`.
  * **Cannot** access `RT 01` or `RT 02` even though they reside in the same RW.
  * **Cannot** create new RTs in RW 05.

## 2. Core Abstractions

### `ScopeRef`
A value object representing a fully-qualified point in the organizational tree:
```php
ScopeRef::organization($orgId);
ScopeRef::rw($orgId, $rwId);
ScopeRef::rt($orgId, $rwId, $rtId);
```
`ScopeRef::covers($target)` implements hierarchical propagation:
- An **Organization** grant covers everything inside that organization.
- An **RW** grant covers that RW and all RT children inside it (never another RW).
- An **RT** grant covers only that specific RT.

### `AccessResolver`
The single authoritative service answering `allows(User $user, PermissionKey $permission, ScopeRef $target): bool`:
- Verifies that the user account is active.
- Evaluates active (unrevoked) memberships and their attached permissions.
- Employs per-request caching with eager-loaded role permissions to prevent N+1 queries.

### `ScopedQuery`
Applies authorization grants directly to Eloquent Query Builders:
```php
$query = $scopedQuery->constrain(
    $query,
    $request->user(),
    PermissionKey::RwView,
    [
        'organization' => 'organization_id',
        'rw' => 'id',
        'rt' => fn ($q, $rtId) => $q->whereHas('rts', fn ($sub) => $sub->where('id', $rtId)),
    ]
);
```
This guarantees list endpoints never leak rows that detail endpoints would deny.

### Server-Side Authority Protection
Frontend-supplied IDs (`user_id`, `rw_id`, `rt_id`, `scope_type`) are **never** trusted as proof of authorization:
1. `ScopeResolver` looks up the entity from the database to construct a trusted `ScopeRef`.
2. `AccessResolver` evaluates the caller's active memberships against the resolved `ScopeRef`.
3. If the caller lacks authority over that target scope, an `AuthorizationException` (403 Forbidden) is immediately raised.

## 3. Hierarchical Viewing & Navigation Invariants

- **Parent Visibility:** An officer holding an active role at RW or RT scope is permitted to view their parent Organization (`OrganizationPolicy::view`). An RT officer is permitted to view their parent RW (`RwPolicy::view`).
- **Management Boundary:** Hierarchical viewing does **not** confer management or mutation rights. An RT officer cannot create RTs in an RW (`RwPolicy::createRt`) or assign memberships (`MembershipPolicy::manage`).
- **Isolation:** RW officers cannot view or access sibling RWs. RT officers cannot view sibling RTs even within the same RW.
- **Immediate Revocation Cutoff:** Revoked memberships (`revoked_at IS NOT NULL`) are immediately excluded from authorization queries via `Membership::scopeActive()`.
- **Multi-Membership Precision:** A user holding roles across different scopes (e.g. Ketua RT 01 and Sekretaris RW 06) receives strictly separated permissions without permission or scope bleeding across organizational boundaries.
- **Request Lifecycle Cache Flush:** `AccessResolver` is registered as a singleton with a per-request `flush()` invoked by `AssignRequestId` middleware at every API boundary, preventing stale authorization data from persisting across requests.
