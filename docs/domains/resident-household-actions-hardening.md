# Resident & Household Domain Action Hardening Audit Report

This document details the hardening audit performed on the Resident & Household Domain Action layer of the RT/RW Community Platform.

---

## 1. Audited Actions & Components

The following 10 domain actions, 2 state machines, and support services were audited:

| Action / Service | Domain | Invariants & Hardening Scope | Status |
|---|---|---|---|
| [`RegisterResidentAction`](file:///c:/Users/USER/Desktop/RTRW/backend/app/Domain/Resident/Actions/RegisterResidentAction.php) | Resident | 16-digit NIK validation, table-wide unique NIK, trusted DB RT/RW/Org scope resolution, mandatory initial `PROSPECTIVE` status, immutable status history, no initial household assignment. | **Hardened** |
| [`VerifyResidentAction`](file:///c:/Users/USER/Desktop/RTRW/backend/app/Domain/Resident/Actions/VerifyResidentAction.php) | Resident | Strict verification gate `PROSPECTIVE -> ACTIVE` only. All other status transitions rejected with domain exception. | **Verified** |
| [`RelocateResidentAction`](file:///c:/Users/USER/Desktop/RTRW/backend/app/Domain/Resident/Actions/RelocateResidentAction.php) | Resident | 4 movement types (`TRANSFER_INTERNAL`, `MOVE_OUT_EXTERNAL`, `MOVE_IN_EXTERNAL`, `HOUSEHOLD_SPLIT`), ancestry preservation, deactivating old household membership, household vacancy/relocation handling on last member departure. | **Hardened** |
| [`RecordResidentStatusAction`](file:///c:/Users/USER/Desktop/RTRW/backend/app/Domain/Resident/Actions/RecordResidentStatusAction.php) | Resident | Entry point for civil status transitions; delegates to `ResidentStatusStateMachine`. | **Verified** |
| [`ResidentStatusStateMachine`](file:///c:/Users/USER/Desktop/RTRW/backend/app/Domain/Resident/Services/ResidentStatusStateMachine.php) | Resident | Civil death procedure, last member dissolution (`DISSOLVED`), non-head death handling, user account suspension via dedicated Identity service, Sanctum token revocation, status history recording. | **Hardened** |
| [`CreateHouseholdAction`](file:///c:/Users/USER/Desktop/RTRW/backend/app/Domain/Household/Actions/CreateHouseholdAction.php) | Household | 16-digit KK validation, active KK uniqueness per org, initial head must be strictly `ACTIVE` resident in same RT, atomic membership creation, `HEAD_VACANT` fallback. | **Hardened** |
| [`AddResidentToHouseholdAction`](file:///c:/Users/USER/Desktop/RTRW/backend/app/Domain/Household/Actions/AddResidentToHouseholdAction.php) | Household | Resident RT == Household RT, only `ACTIVE` or `HEAD_VACANT` household, resident cannot be `PROSPECTIVE`, head appointment requires `ACTIVE` resident, single active membership guarantee. | **Hardened** |
| [`ChangeHouseholdHeadAction`](file:///c:/Users/USER/Desktop/RTRW/backend/app/Domain/Household/Actions/ChangeHouseholdHeadAction.php) | Household | Candidate must be `ACTIVE` resident and active member, old head demoted, idempotent self-appointment, rejected on `ARCHIVED`/`DISSOLVED`/`MOVED` household, single active head lock. | **Hardened** |
| [`RemoveResidentFromHouseholdAction`](file:///c:/Users/USER/Desktop/RTRW/backend/app/Domain/Resident/Actions/RemoveResidentFromHouseholdAction.php) | Resident | Historical membership preserved (`left_at`, `left_reason`, `is_active=false`), head departure -> `HEAD_VACANT`, last member departure (head or non-head) -> `DISSOLVED`. | **Hardened** |
| [`DissolveHouseholdAction`](file:///c:/Users/USER/Desktop/RTRW/backend/app/Domain/Household/Actions/DissolveHouseholdAction.php) | Household | Only `ACTIVE` or `HEAD_VACANT` households, soft dissolution (`status=DISSOLVED`), all active memberships deactivated, resident current household pointers cleared, no physical deletes. | **Verified** |
| [`MoveHouseholdAction`](file:///c:/Users/USER/Desktop/RTRW/backend/app/Domain/Household/Actions/MoveHouseholdAction.php) | Household | Within-RT address update, Inter-RT transfer (relocating household and all residents atomically, recording individual movement ledgers), and External move (residents -> `MOVED`). | **Hardened** |
| [`HouseholdStatusStateMachine`](file:///c:/Users/USER/Desktop/RTRW/backend/app/Domain/Household/Services/HouseholdStatusStateMachine.php) | Household | Exact transition matrix enforcement for households. | **Verified** |
| [`AccountSuspensionService`](file:///c:/Users/USER/Desktop/RTRW/backend/app/Domain/Identity/Services/AccountSuspensionService.php) | Identity | Encapsulates user account suspension and Sanctum token revocation across identity boundary. | **Hardened** |

---

## 2. Invariant Matrix

| Invariant | Database Protection | Domain Action Protection | Test Proving Invariant |
|---|---|---|---|
| **NIK 16 Digits** | `residents_nik_format_check` regex constraint | Regex validation in `RegisterResidentAction` | `test_registration_rejects_malformed_nik` |
| **NIK Table-Wide Uniqueness** | `residents_nik_unique` index | `DuplicateNikException` check in action | `test_registration_rejects_duplicate_nik` |
| **Single Active KK per Org** | `households_active_kk_unique` partial index | `HouseholdStateConflict` check in `CreateHouseholdAction` | `test_create_household_rejects_duplicate_active_kk` |
| **Single Active Head per Household** | `household_single_active_head_unique` partial index | Concurrency lock + demote old head in `ChangeHouseholdHeadAction` | `test_change_household_head_promotes_member_and_demotes_old_head` |
| **Single Active Household per Resident** | `resident_single_active_household_unique` partial index | Check `is_active` in `AddResidentToHouseholdAction` | `test_add_resident_already_in_active_household_rejected` |
| **Resident RT == Household RT** | `check_household_member_rt_scope()` trigger & composite FK `residents_current_household_rt_fk` | Scope comparison in `AddResidentToHouseholdAction` & `CreateHouseholdAction` | `test_add_resident_to_household_cross_rt_rejected` |
| **Immutable Movement Ledger** | `prevent_residency_movement_mutation()` trigger | Insert-only via `RelocateResidentAction` & `MoveHouseholdAction` | `test_residency_movement_update_rejected_by_trigger` |
| **Immutable Status History** | `prevent_resident_status_history_mutation()` trigger | Insert-only via `ResidentStatusStateMachine` | `test_resident_status_history_update_rejected_by_trigger` |
| **Prospective Gate** | Initial state `PROSPECTIVE` | `VerifyResidentAction` strictly gates `PROSPECTIVE -> ACTIVE` | `test_verify_prospective_resident_succeeds` & `test_verify_non_prospective_resident_fails` |
| **Deceased Terminal State** | State machine transition matrix | `InvalidResidentStatusTransition` thrown on any transition from `DECEASED` | `test_deceased_resident_cannot_transition_to_any_status` |

---

## 3. State Transition Matrix

### A. Resident Status Transition Matrix

Total Combinations: $5 \times 5 = 25$ pairs. Tested comprehensively in `test_resident_status_state_machine_matrix`.

| From \ To | PROSPECTIVE | ACTIVE | INACTIVE | MOVED | DECEASED |
|---|:---:|:---:|:---:|:---:|:---:|
| **PROSPECTIVE** | ❌ Forbidden | ✅ **ALLOWED** | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden |
| **ACTIVE** | ❌ Forbidden | ❌ Forbidden | ✅ **ALLOWED** | ✅ **ALLOWED** | ✅ **ALLOWED** |
| **INACTIVE** | ❌ Forbidden | ✅ **ALLOWED** | ❌ Forbidden | ❌ Forbidden | ✅ **ALLOWED** |
| **MOVED** | ❌ Forbidden | ✅ **ALLOWED** | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden |
| **DECEASED** | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden |

*Note: `DECEASED` is strictly terminal. No transitions out of `DECEASED` are permitted under any condition.*

### B. Household Status Transition Matrix

Total Combinations: $5 \times 5 = 25$ pairs. Tested comprehensively in `test_household_status_state_machine_matrix`.

| From \ To | ACTIVE | HEAD_VACANT | MOVED | DISSOLVED | ARCHIVED |
|---|:---:|:---:|:---:|:---:|:---:|
| **ACTIVE** | ❌ Forbidden | ✅ **ALLOWED** | ✅ **ALLOWED** | ✅ **ALLOWED** | ❌ Forbidden |
| **HEAD_VACANT** | ✅ **ALLOWED** | ❌ Forbidden | ✅ **ALLOWED** | ✅ **ALLOWED** | ❌ Forbidden |
| **MOVED** | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden | ✅ **ALLOWED** |
| **DISSOLVED** | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden | ✅ **ALLOWED** |
| **ARCHIVED** | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden | ❌ Forbidden |

---

## 4. Transaction Boundaries & Locking Strategy

Every state-altering domain action runs inside an explicit `DB::transaction(...)`:

1. **Row Locking (`lockForUpdate`)**:
   - `ChangeHouseholdHeadAction`: Locks household row, current active head membership, and candidate membership.
   - `AddResidentToHouseholdAction`: Locks household and resident rows.
   - `RemoveResidentFromHouseholdAction`: Locks resident, active membership, and household row.
   - `RelocateResidentAction`: Locks resident and old household row.
   - `ResidentStatusStateMachine`: Locks resident, active membership, household, and linked user row.
   - `MoveHouseholdAction`: Locks household, all active memberships, and all resident rows.
2. **PostgreSQL Partial Unique Indexes as Final Defense**:
   - `household_single_active_head_unique`: Protects against simultaneous head appointments. Caught and mapped to `InvalidHouseholdHeadChange`.
   - `resident_single_active_household_unique`: Protects against simultaneous household additions. Caught and mapped to `HouseholdMembershipConflict`.
   - `households_active_kk_unique`: Protects against duplicate active KK registrations. Caught and mapped to `HouseholdStateConflict`.

---

## 5. Movement Ledger Rules & Schema Hardening

The Residency Movement ledger records 4 distinct movement types:
- `MOVE_IN_EXTERNAL`: Resident enters organization from outside.
- `MOVE_OUT_EXTERNAL`: Resident exits organization to outside.
- `TRANSFER_INTERNAL`: Resident moves between RTs within same organization.
- `HOUSEHOLD_SPLIT`: Resident branches into another household within same RT.

### Migration Hardening (`2026_10_03_150001_relax_residency_movements_transfer_internal_check.php`)

During the audit, a critical schema conflict was discovered:
1. **The Issue**: When an entire household moves to another RT (`MoveHouseholdAction`), its active residents relocate with the household. In this scenario, `from_household_id = to_household_id = household->id`, and `from_rt_id <> to_rt_id`.
   - The initial constraint `residency_movements_nullability_check` contained `AND from_household_id <> to_household_id` for `TRANSFER_INTERNAL`.
   - The composite foreign key `residency_movements_from_hh_fk` was defined as `(from_household_id, from_rt_id) REFERENCES households(id, rt_id)`. Because the household's `rt_id` was updated to the new RT, PostgreSQL rejected the historical `(household_id, old_rt_id)` reference.
2. **The Hardening Migration**:
   - Relaxed `TRANSFER_INTERNAL` check constraint so that `from_household_id` may equal or differ from `to_household_id`.
   - Replaced composite foreign key `residency_movements_from_hh_fk` with direct reference `from_household_id REFERENCES households(id)`, while `to_household_id, to_rt_id` continues to reference `households(id, rt_id)` at the target state.
3. **Integrity Test**: Verified via `test_transfer_internal_household_relocation_between_rts_succeeds` and `test_move_household_inter_rt_transfer_with_active_residents`.

---

## 6. Household ↔ Resident Synchronization Rules

All write paths modifying `residents.current_household_id` and `household_members.is_active` were audited to prevent invalid states:

| Invalid State | Prevention Mechanism | Status |
|---|---|---|
| **A. Active membership but `current_household_id = null`** | Actions always atomically set `current_household_id` whenever `is_active = true` is set. | **Verified** |
| **B. Pointer set but no active membership** | `current_household_id` is set only in the same transaction that inserts active `HouseholdMember`. | **Verified** |
| **C. Multiple active memberships** | Enforced by partial unique index `resident_single_active_household_unique`. | **Verified** |
| **D. Pointer != active membership household** | Actions assign the identical ID to both fields within transaction. | **Verified** |
| **E. Pointer points to household in different RT** | Enforced by PostgreSQL composite foreign key `residents_current_household_rt_fk` (`(current_household_id, rt_id) REFERENCES households(id, rt_id)`). | **Verified** |

### Vacancy & Dissolution Logic Hardening
- **Head Departed / Died + Members Remaining**: Household transitions to `HEAD_VACANT`.
- **Last Member Departed / Died (whether Head or Non-Head)**: Household transitions to `DISSOLVED`.
- **Non-Head Departed / Died + Members Remaining**: Household status remains unchanged (`ACTIVE` or `HEAD_VACANT`), remaining members stay active.

---

## 7. Identity Boundary Audit Findings

- **Current Coupling**:
  Previously, `ResidentStatusStateMachine` directly invoked Sanctum's Eloquent relation `$linkedUser->tokens()->delete()` on civil death.
- **Architectural Risk**:
  Directly calling Sanctum token deletion from the resident domain couples civil lifecycle management to infrastructure authentication concerns.
- **Hardening Applied**:
  Introduced [`AccountSuspensionService`](file:///c:/Users/USER/Desktop/RTRW/backend/app/Domain/Identity/Services/AccountSuspensionService.php) in `App\Domain\Identity\Services`. `ResidentStatusStateMachine` now delegates to this service via `suspendOnCivilDeath($userId, $residentId, $actor)`.
- **Target Boundary**:
  Identity capabilities (account state, authentication tokens) are now properly encapsulated inside the Identity domain.

---

## 8. Exception Architecture Findings

All 7 domain exceptions:
- `DuplicateNikException`
- `ResidentScopeMismatch`
- `HouseholdMembershipConflict`
- `InvalidHouseholdHeadChange`
- `InvalidResidentStatusTransition`
- `HouseholdStateConflict`
- `InvalidHouseholdStatusTransition`

extend `App\Support\Api\Exceptions\ApiException`.
- **Evaluation**: The domain actions contain zero HTTP semantics (no Request objects, no Response rendering, no session handling). `ApiException` acts solely as a lightweight typed metadata carrier (`status` integer and `errorCode` string) so Laravel's centralized error handler can transform domain exceptions into standard error envelopes without repetitive controller try-catch blocks.
- **Decision**: Maintained as an intentional, pragmatic monolithic architecture trade-off.

---

## 9. Write-Path Restriction Audit

Grep searches confirmed that direct Eloquent writes (`Resident::create`, `Resident::update`, `Household::create`, etc.) exist **exclusively** inside the authorized Domain Actions:
- `RegisterResidentAction`
- `RelocateResidentAction`
- `CreateHouseholdAction`
- `AddResidentToHouseholdAction`
- `ChangeHouseholdHeadAction`
- `RemoveResidentFromHouseholdAction`
- `DissolveHouseholdAction`
- `MoveHouseholdAction`
- `ResidentStatusStateMachine`

No controllers, model observers, or random helpers perform unmanaged mutations on Resident or Household state.

---

## 10. Summary Classification

### Verified
- Table-wide NIK uniqueness and 16-digit numeric validation.
- Scope hierarchy resolution (`Organization -> RW -> RT`).
- Head of household single source of truth (`household_members.relationship = KEPALA_KELUARGA` AND `is_active = TRUE`).
- Concurrency locks preventing dual active heads and multiple active memberships.
- Soft dissolution and historical preservation (no physical deletion).
- Full transaction rollbacks on failure simulation.

### Hardened
- Initial resident registration strictly hardcoded to `PROSPECTIVE`.
- `CreateHouseholdAction` and `AddResidentToHouseholdAction` hardened to reject `PROSPECTIVE` residents from joining or leading households.
- Civil death and departure handling hardened to transition household to `DISSOLVED` when the last remaining member departs/dies (regardless of whether they were head).
- Non-head civil death hardened so it never transitions household to `HEAD_VACANT`.
- New migration `2026_10_03_150001_relax_residency_movements_transfer_internal_check.php` to enable inter-RT household relocations with active residents.
- Identity boundary decoupled via `AccountSuspensionService`.

### Architectural Observation
- In pure Hexagonal/DDD, domain exceptions should not extend `ApiException`. In this Laravel Modular Monolith, inheriting `ApiException` is a deliberate, highly pragmatic choice that provides centralized error envelopes without polluting business logic with HTTP concerns.

### Deferred
- Event-driven cross-domain notifications (e.g. broadcasting death events to finance or letters domains) deferred to Phase 8+ when those domains are introduced.
