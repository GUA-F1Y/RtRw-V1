# Resident & Household — Domain Action Layer Specification

## 1. Architectural Role & Boundary

The Resident & Household Domain Action layer introduces the first application and business logic for community identity and household management in the RT/RW platform.

Following the strict separation of concerns established in the platform foundation:

* **HTTP Layer (Future Phase):** Accepts HTTP requests, parses payloads via FormRequests, and evaluates authorization via Policies.
* **Domain Actions (Current Phase):** Atomic, self-contained business units that encapsulate business invariants, locking, state transitions, ledger writes, and audit recording.
* **Eloquent Models:** Kept strictly thin—defining table mappings, relationships, casts, and immutable append-only constraints.
* **PostgreSQL Database:** Enforces relational integrity, composite foreign key ancestry, partial unique constraints, and append-only trigger guarantees.

```
       ┌────────────────────────┐
       │   HTTP / API Layer     │  (Future Phase)
       │ (Controllers, Policies)│
       └───────────┬────────────┘
                   │ Invokes with typed DTOs
                   ▼
       ┌────────────────────────┐
       │   Domain Action Layer  │  (Current Phase: Invariant & State Orchestration)
       │ (Atomic DB Transaction)│
       └─────┬────────────┬─────┘
             │            │
             ▼            ▼
┌──────────────────┐┌──────────────────┐
│  Domain Models   ││ Immutable Ledgers│
│(Resident/HH/Mem) ││(Movements/Status)│
└────────────┬─────┘└─────┬────────────┘
             │            │
             ▼            ▼
┌──────────────────────────────────────┐
│       PostgreSQL 16+ Engine          │ (Triggers, Composite FKs, Partial Indexes)
└──────────────────────────────────────┘
```

---

## 2. Core Domain Actions

| Action | Primary Responsibility | Key Invariants Enforced |
| :--- | :--- | :--- |
| **`RegisterResidentAction`** | Register new civil identity (`Resident`) | 16-digit numeric NIK regex; global NIK uniqueness; trusted DB hierarchy resolution (RT $\to$ RW $\to$ Org); initial status history ledger append; audit log. |
| **`VerifyResidentAction`** | Verify civil identity | Transition gate: strictly `PROSPECTIVE` $\to$ `ACTIVE`. Rejects non-prospective states with `InvalidResidentStatusTransition`. Appends status history ledger; audit log. |
| **`RelocateResidentAction`** | Execute geographical relocation | Resolves "from" scope exclusively from current DB state (never trusts client); supports `TRANSFER_INTERNAL`, `MOVE_OUT_EXTERNAL`, `MOVE_IN_EXTERNAL`, and `HOUSEHOLD_SPLIT`; satisfies database nullability checks; appends immutable `residency_movements` ledger; audit log. |
| **`RecordResidentStatusAction`** | Record legal civil status changes | Coordinates through `ResidentStatusStateMachine`; executes civil death procedures (suspends linked User, revokes Sanctum tokens, vacates household headship, deactivates membership); appends `resident_status_history`; audit log. |
| **`RemoveResidentFromHouseholdAction`** | Remove resident from household | Deactivates membership (`is_active = FALSE`, `left_at`, `left_reason`); clears `resident.current_household_id`; transitions household to `HEAD_VACANT` if head departs and members remain, or `DISSOLVED` if last member leaves; audit log. |
| **`CreateHouseholdAction`** | Register new Kartu Keluarga (`Household`) | 16-digit numeric KK regex; active KK uniqueness within organization; creates household in `ACTIVE` state if initial head provided, or `HEAD_VACANT` if no head provided; atomic membership creation; audit log. |
| **`AddResidentToHouseholdAction`** | Attach resident to household | Scope alignment (`resident.rt_id === household.rt_id`); verifies household status is membership-compatible (`ACTIVE` or `HEAD_VACANT`); asserts resident does not belong to another active KK; promotes `HEAD_VACANT` to `ACTIVE` if added as head; audit log. |
| **`ChangeHouseholdHeadAction`** | Appoint new head of household | Promotes active household member to `KEPALA_KELUARGA`; demotes existing head to non-head relationship; recovers `HEAD_VACANT` $\to$ `ACTIVE`; prevents duplicate heads; audit log. |
| **`DissolveHouseholdAction`** | Dissolve household domestic unit | Transitions status to `DISSOLVED`; deactivates all remaining active memberships; clears `current_household_id` on all linked residents; audit log. |
| **`MoveHouseholdAction`** | Relocate dwelling or external move | Supports within-RT address update; inter-RT transfer (updates household and all linked residents' RT scope, appends internal transfer movement ledger); external move (transitions household and residents to `MOVED`, appends external movement ledger); audit log. |

---

## 3. State Transition Graphs

### 3.1 Resident Civil Status Transition Graph (`ResidentStatusStateMachine`)

```
                 ┌────────────────────────┐
                 │      PROSPECTIVE       │
                 └───────────┬────────────┘
                             │ VerifyResidentAction
                             ▼
                 ┌────────────────────────┐
          ┌─────►│         ACTIVE         │◄──── RelocateResidentAction (Move In External)
          │      └─────┬──────┬─────┬─────┘
          │            │      │     │
          │ Relocate   │      │     │ RecordResidentStatusAction (Death Procedure)
          │ Out        ▼      │     ▼
          │      ┌──────────┐ │   ┌───────────┐
          │      │  MOVED   │ │   │ DECEASED  │ (Terminal State — Permanent)
          │      └──────────┘ │   └───────────┘
          │                   │ Suspend
          │                   ▼
          │             ┌───────────┐
          └─────────────┤ INACTIVE  │
                        └───────────┘
```

#### Transition Invariants:
1. `PROSPECTIVE` $\to$ `ACTIVE`: Only allowed through `VerifyResidentAction` or administrative verification.
2. `ACTIVE` $\to$ `INACTIVE` / `MOVED` / `DECEASED`: Legal transitions for administrative suspension, relocation, or civil death.
3. `INACTIVE` $\to$ `ACTIVE` / `DECEASED`: Re-activation after review, or death while inactive.
4. `MOVED` $\to$ `ACTIVE`: Re-entry into community domicile.
5. `DECEASED` $\to$ *Anything*: **FORBIDDEN**. Death is permanent. Attempted transitions throw `InvalidResidentStatusTransition` (HTTP 422).

---

### 3.2 Household Status Transition Graph (`HouseholdStatusStateMachine`)

```
                 ┌────────────────────────┐
                 │         ACTIVE         │
                 └─────┬────────────┬─────┘
                       │            │
             Head Dead │            │ All Members
              or Moved │            │ Departed / Dissolved
                       ▼            ▼
                 ┌───────────┐┌───────────┐
                 │HEAD_VACANT││ DISSOLVED │
                 └─────┬─────┘└─────┬─────┘
                       │            │ Archive
          New Head Appt│            │
     ChangeHouseholdHead│            │
                       ▼            ▼
                 ┌───────────┐┌───────────┐
                 │  ACTIVE   ││ ARCHIVED  │ (Historical retention)
                 └───────────┘└───────────┘
```

#### Transition Invariants:
1. `ACTIVE` $\to$ `HEAD_VACANT`: Triggered when the active head of family dies, relocates, or is removed, while other active members remain.
2. `HEAD_VACANT` $\to$ `ACTIVE`: Recovered when a new head of family is appointed via `ChangeHouseholdHeadAction` or `AddResidentToHouseholdAction`.
3. `ACTIVE` / `HEAD_VACANT` $\to$ `DISSOLVED`: Triggered when all members leave or via explicit `DissolveHouseholdAction`.
4. `ACTIVE` / `HEAD_VACANT` $\to$ `MOVED`: Triggered when the entire household moves externally.
5. `MOVED` / `DISSOLVED` $\to$ `ARCHIVED`: Historical archiving.
6. `ARCHIVED` $\to$ *Anything*: **FORBIDDEN**.

---

## 4. Resident Death Procedure (Section 9)

Civil death is a critical domain event executed atomically within `ResidentStatusStateMachine`:

1. **Row Lock:** Resident row is acquired with `lockForUpdate()`.
2. **Status Update:** `residents.status` set to `DECEASED`.
3. **Household Membership Deactivation:**
   - Active `household_members` record marked `is_active = FALSE`.
   - `left_at` stamped with effective death date.
   - `left_reason` set to `'DECEASED'`.
   - `residents.current_household_id` set to `NULL`.
4. **Head Vacancy Management:**
   - If the deceased resident was the active head (`relationship = KEPALA_KELUARGA`):
     - Count remaining active members in household.
     - If members remain: transition household to `HEAD_VACANT`.
     - If no members remain: transition household to `DISSOLVED`.
5. **Linked User Security Suspension:**
   - If `residents.user_id` is present, the linked `User` is **NOT deleted**.
   - `users.status` transitioned to `AccountStatus::Suspended`.
   - All active Sanctum personal access tokens revoked via `$user->tokens()->delete()`.
6. **Immutable Ledger Append:**
   - Appends entry into `resident_status_history` with `previous_status`, `new_status = DECEASED`, death certificate number (`reference_letter_number`), and recorded timestamp.
7. **Audit Record:** Emits `'resident.marked_deceased'` and `'user.suspended_on_death'`.

---

## 5. Relocation & Movement Ledger Enforcement (Sections 13 & 14)

### 5.1 Pre-Change Trusted Scope Resolution
The action layer **never** trusts client-supplied "from" parameters. When executing `RelocateResidentAction`:
```php
$fromOrgId = $resident->organization_id;
$fromRwId = $resident->rw_id;
$fromRtId = $resident->rt_id;
$fromHouseholdId = $resident->current_household_id;
```

### 5.2 Movement Nullability Matrix
The action strictly enforces PostgreSQL constraint `residency_movements_nullability_check`:

| Movement Type | `from_org`, `rw`, `rt` | `from_household` | `to_org`, `rw`, `rt` | `to_household` | Scope Rules |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `TRANSFER_INTERNAL` | **NOT NULL** | **NOT NULL** | **NOT NULL** | **NOT NULL** | `from_org = to_org`, `from_rt != to_rt`, `from_hh != to_hh` |
| `MOVE_OUT_EXTERNAL` | **NOT NULL** | **NOT NULL** | **NULL** | **NULL** | Leaves organization territory; resident becomes `MOVED` |
| `MOVE_IN_EXTERNAL` | **NULL** | **NULL** | **NOT NULL** | **NOT NULL** | Enters organization territory; resident becomes `ACTIVE` |
| `HOUSEHOLD_SPLIT` | **NOT NULL** | **NOT NULL** | **NOT NULL** | **NOT NULL** | `from_org = to_org`, `from_rt = to_rt`, `from_hh != to_hh` |

---

## 6. Concurrency Strategy & Row-Level Locking

Race conditions in concurrent civic operations are prevented using PostgreSQL row-level locks within transactions:

1. **Head of Household Changes (`ChangeHouseholdHeadAction`):**
   - Locks household row (`SELECT ... FOR UPDATE`).
   - Locks target new head membership row (`SELECT ... FOR UPDATE`).
   - Locks existing active head membership row (`SELECT ... FOR UPDATE`).
   - Demotes old head, promotes new head atomically.
   - Guaranteed by PostgreSQL partial unique index `household_single_active_head_unique`.
2. **Household Membership Assignment (`AddResidentToHouseholdAction`):**
   - Locks target resident row.
   - Locks target household row.
   - Asserts single active membership.
   - Guaranteed by PostgreSQL partial unique index `resident_single_active_household_unique`.
3. **Internal Relocation (`RelocateResidentAction`):**
   - Locks resident row.
   - Locks source household membership.
   - Deactivates source membership, updates resident RT scope, attaches to target household.
   - Appends immutable movement ledger row.

---

## 7. Transaction Rollback Guarantee

Every multi-write action is wrapped within `DB::transaction(...)`. If any failure occurs (e.g. database constraint error, ledger trigger rejection, audit logging failure):
- **All** uncommitted changes roll back immediately.
- Resident scope, status, household pointer, and membership states remain pristine.
- Verified by automated tests in `tests/Feature/ResidentHouseholdActionTest.php` (`test_rollback_relocate_resident_when_movement_ledger_fails` and `test_rollback_death_procedure_on_failure`).

---

## 8. Domain Exceptions

Domain exceptions extend `App\Support\Api\Exceptions\ApiException` to ensure clean, client-safe rendering by `ApiExceptionRenderer` without raw SQL or internal trace leakage:

| Exception | HTTP Code | Error Code | Purpose |
| :--- | :--- | :--- | :--- |
| `DuplicateNikException` | 409 | `duplicate_nik` | Thrown when attempting to register a resident with a NIK that already exists. |
| `ResidentScopeMismatch` | 422 | `resident_scope_mismatch` | Thrown when hierarchy components (Org, RW, RT) do not align, or resident RT $\ne$ household RT. |
| `HouseholdMembershipConflict` | 409 | `household_membership_conflict` | Thrown on duplicate active household membership attempts or invalid member detachment. |
| `InvalidHouseholdHeadChange` | 422 | `invalid_household_head_change` | Thrown when attempting to appoint an ineligible head or violating single active head invariants. |
| `InvalidResidentStatusTransition` | 422 | `invalid_resident_status_transition` | Thrown when an illegal lifecycle transition is requested (e.g. `DECEASED` $\to$ `ACTIVE`). |
| `HouseholdStateConflict` | 409 | `household_state_conflict` | Thrown on duplicate active KK number registration or operations on incompatible household states. |
| `InvalidHouseholdStatusTransition` | 422 | `invalid_household_status_transition` | Thrown when an illegal household lifecycle transition is requested (e.g. `ARCHIVED` $\to$ `ACTIVE`). |

---

## 9. Audit Integration

All actions record high-fidelity audit entries through the existing `App\Domain\Audit\Services\AuditLogger` service.

Recorded events include:
- `resident.registered`
- `resident.verified`
- `resident.status_changed`
- `resident.marked_deceased`
- `user.suspended_on_death`
- `resident.transferred_rt`
- `resident.moved_out_external`
- `resident.moved_in_external`
- `household.created`
- `household.member_added`
- `household.member_removed`
- `household.head_changed`
- `household.head_vacated`
- `household.split`
- `household.dissolved`
- `household.relocated_rt`
- `household.address_updated`
- `household.status_changed`
