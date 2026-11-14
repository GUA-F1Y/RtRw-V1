# Authorization Policy & Scope Model

**Domain:** RT/RW Platform  
**Phase:** 8 — Authorization Policy Layer  
**Status:** Implemented & Verified  

---

## 1. Core Separation of Concerns

The platform enforces a strict two-layer separation:

| Layer | Responsibility | Answers |
|---|---|---|
| **Domain Action** | Business validity | _"Is this operation semantically valid?"_ |
| **Policy / Gate** | Access control | _"Does this actor have the right to perform this operation on this resource in this scope?"_ |

A Domain Action may only reject an operation for business reasons (e.g., "resident already has a household"). It **never** makes authorization decisions. Authorization is exclusively the responsibility of the Policy layer via `AccessResolver`.

---

## 2. Scope Hierarchy

```
Organization
  └── RW (Rukun Warga)
        └── RT (Rukun Tetangga)
              └── Household (KK)
                    └── Resident (Warga)
```

Every resource in the system carries an immutable **ScopeRef** (organization_id, rw_id, rt_id) that anchors it to the hierarchy.

### Scope Coverage Rules

| Actor Scope Level | Covers |
|---|---|
| `Organization` | ALL RWs and RTs within that organization |
| `RW` | The specific RW + ALL RTs nested inside it |
| `RT` | ONLY that specific RT — no siblings, no parent RW mutations |

This is implemented in `ScopeRef::covers(ScopeRef $target)`.

---

## 3. Policy Files

| Policy | Model | Location |
|---|---|---|
| `ResidentPolicy` | `Resident::class` | `app/Policies/ResidentPolicy.php` |
| `HouseholdPolicy` | `Household::class` | `app/Policies/HouseholdPolicy.php` |

Both policies are registered in `AppServiceProvider::boot()` via `Gate::policy(...)`.

All policy methods delegate exclusively to `AccessResolver::allows(User, PermissionKey, ScopeRef)`.  
**No direct role checks, no `$user->role`, no `$user->rt_id` comparisons exist.**

---

## 4. Resident Permissions

### Permission Vocabulary

| Permission Key | Enum Case | Purpose |
|---|---|---|
| `resident.directory_view` | `ResidentDirectoryView` | View public community directory (safe, no sensitive fields) |
| `resident.view` | `ResidentView` | View resident profile non-sensitive details |
| `resident.view_sensitive` | `ResidentViewSensitive` | View full NIK, phone, identity docs |
| `resident.register` | `ResidentRegister` | Register a new prospective resident into a scope |
| `resident.verify` | `ResidentVerify` | Verify a prospective resident |
| `resident.update` | `ResidentUpdate` | Update resident civil details |
| `resident.relocate` | `ResidentRelocate` | Relocate resident (internal transfer or external move) |
| `resident.remove_from_household` | `ResidentRemoveFromHousehold` | Remove resident from household membership |
| `resident.change_status` | `ResidentChangeStatus` | Record civil status change (including death) |

### Sensitive Data Boundary (Privacy Critical)

The three-tier view model ensures NIK and contact data are not exposed to billing roles:

```
resident.directory_view  ← Name, RT, basic membership info only
resident.view            ← Full non-sensitive profile
resident.view_sensitive  ← NIK, phone, identity documents, sensitive attachments
```

**Bendahara (billing) roles hold `resident.view` but NEVER `resident.view_sensitive`.**  
This is enforced by the `RoleKey` permission matrix and tested in `ResidentHouseholdPolicyTest`.

---

## 5. Household Permissions

| Permission Key | Enum Case | Purpose |
|---|---|---|
| `household.view` | `HouseholdView` | View household details (KK number masked) |
| `household.view_sensitive` | `HouseholdViewSensitive` | View full (unmasked) KK number |
| `household.create` | `HouseholdCreate` | Register a new household into a scope |
| `household.update` | `HouseholdUpdate` | Update household basic information |
| `household.add_member` | `HouseholdAddMember` | Add a member to a household |
| `household.remove_member` | `HouseholdRemoveMember` | Remove a member from a household |
| `household.change_head` | `HouseholdChangeHead` | Change or assign head of household |
| `household.move` | `HouseholdMove` | Administratively move/transfer a household |
| `household.dissolve` | `HouseholdDissolve` | Dissolve a household |

---

## 6. Role Permission Matrix

### Resident Permissions by Role

| Permission | OrgAdmin | KetuaRw | SekretarisRw | BendaharaRw | KetuaRt | SekretarisRt | BendaharaRt |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `directory_view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `view_sensitive` | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ❌ |
| `register` | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ❌ |
| `verify` | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ❌ |
| `update` | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ❌ |
| `relocate` | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| `remove_from_household` | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ | ❌ |
| `change_status` | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |

### Household Permissions by Role

| Permission | OrgAdmin | KetuaRw | SekretarisRw | BendaharaRw | KetuaRt | SekretarisRt | BendaharaRt |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `view_sensitive` | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ❌ |
| `create` | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ❌ |
| `update` | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ❌ |
| `add_member` | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ❌ |
| `remove_member` | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ | ❌ |
| `change_head` | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| `move` | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| `dissolve` | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |

---

## 7. Cross-Scope Operations (Dual Scope Enforcement)

Two operations require authority over **both source and target scopes**:

### `resident.relocate` (ResidentPolicy::relocate)

When relocating a resident to a different RT:

```php
public function relocate(User $user, Resident $resident, ?ScopeRef $targetScope = null): bool
{
    // Check source: actor must have relocate permission on resident's current RT
    if (!$this->access->allows($user, PermissionKey::ResidentRelocate, $resident->scopeRef())) {
        return false;
    }

    // Check target: if target scope specified, actor must also cover it
    if ($targetScope !== null && !$this->access->allows($user, PermissionKey::ResidentRelocate, $targetScope)) {
        return false;
    }

    return true;
}
```

**Practical outcome:**
| Actor | Source: RT A1 | Target: RT A2 (sibling) | Allowed? |
|---|---|---|---|
| KetuaRt (RT A1) | ✅ has authority | ❌ no authority | ❌ DENIED |
| KetuaRw (RW A) | ✅ covers child RT A1 | ✅ covers child RT A2 | ✅ ALLOWED |
| KetuaRw (RW A) | ✅ covers RT A1 | ❌ cannot cover sibling RW B | ❌ DENIED |

### `household.move` (HouseholdPolicy::move)

Same dual-scope pattern applied to household administrative transfer.

---

## 8. Member Dual-Scope Guard

`addMember` and `removeMember` also enforce dual-scope when a `$resident` argument is provided:

```php
public function addMember(User $user, Household $household, ?Resident $resident = null): bool
{
    // Household source scope
    if (!$this->access->allows($user, PermissionKey::HouseholdAddMember, $household->scopeRef())) {
        return false;
    }
    // Resident scope — must also have authority over the resident's RT
    if ($resident !== null && !$this->access->allows($user, PermissionKey::HouseholdAddMember, $resident->scopeRef())) {
        return false;
    }
    return true;
}
```

This prevents RT A1 officers from pulling residents from RT A2 into their household.

---

## 9. Bypass Audit

The following patterns were verified absent from `app/`:

| Pattern | Result |
|---|---|
| `$user->role` direct check | ✅ Not found |
| `$user->rt_id ===` comparison | ✅ Not found |
| `is_admin` flag bypass | ✅ Not found |
| `hasRole()` direct check | ✅ Not found |
| `superuser` / `admin_bypass` | ✅ Not found |

All authorization decisions flow exclusively through `AccessResolver` → `ScopeRef::covers()`.

The one occurrence of `$resident->rt_id ===` in `RelocateResidentAction` is a **domain validation** (preventing same-RT transfer as a business rule), not an authorization bypass.

---

## 10. File Index

| File | Purpose |
|---|---|
| [`app/Policies/ResidentPolicy.php`](file:///c:/Users/USER/Desktop/RTRW/backend/app/Policies/ResidentPolicy.php) | 9 resident authorization methods |
| [`app/Policies/HouseholdPolicy.php`](file:///c:/Users/USER/Desktop/RTRW/backend/app/Policies/HouseholdPolicy.php) | 8 household authorization methods |
| [`app/Providers/AppServiceProvider.php`](file:///c:/Users/USER/Desktop/RTRW/backend/app/Providers/AppServiceProvider.php) | Policy registration via `Gate::policy()` |
| [`app/Domain/Authorization/Enums/PermissionKey.php`](file:///c:/Users/USER/Desktop/RTRW/backend/app/Domain/Authorization/Enums/PermissionKey.php) | 17 new domain permission cases |
| [`app/Domain/Authorization/Enums/RoleKey.php`](file:///c:/Users/USER/Desktop/RTRW/backend/app/Domain/Authorization/Enums/RoleKey.php) | Role → permission mapping (single source of truth) |
| [`tests/Feature/ResidentHouseholdPolicyTest.php`](file:///c:/Users/USER/Desktop/RTRW/backend/tests/Feature/ResidentHouseholdPolicyTest.php) | 15 tests, 94 assertions |
