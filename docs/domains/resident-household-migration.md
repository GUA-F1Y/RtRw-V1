# Resident & Household — Migration Design Contract (Implemented Specification)

> **Document Status:** IMPLEMENTED — PRODUCTION MIGRATIONS & DATABASE TESTS VERIFIED  
> **Target Database:** PostgreSQL 16+  
> **Application Layer:** Laravel 13 Modular Monolith  
> **Implementation Phase:** PHASE 1 — DATABASE SCHEMA & DATABASE INTEGRITY TESTS ONLY. (No Eloquent models, services, controllers, or frontend UI implemented).

---

## 1. Executive Summary & Implementation Changelog

This document defines the production-ready PostgreSQL migration specification for the **Resident & Household** domain. The migrations and database integrity tests have been implemented and verified against PostgreSQL.

### 1.1 Implementation & Hardening Details

| Section | Implementation Detail | Mechanism | Relational & Operational Benefit |
| :--- | :--- | :--- | :--- |
| **Household Member Scope** | Prevented resident in RT A from joining household in RT B without duplicating `rt_id` column. | Trigger `household_members_enforce_scope_match()` with row-level `FOR SHARE` lock. | Avoids foreign key deadlocks during inter-RT citizen relocation while guaranteeing engine-level scope equality. |
| **Movement Ancestry** | Prevented mismatched ancestry (e.g. Org A → RW of B → RT of C → Household of D) on both origin and destination. | 6 Composite Foreign Keys on `residency_movements`. | Guarantees strict `organization → rw → rt → household` lineage on both `FROM` and `TO` sides. |
| **Movement Type Nullability** | Enforced exact field presence/absence across all 4 movement types. | PostgreSQL multi-branch `CHECK` constraint. | Eliminates reliance on application-layer validation for movement audit shapes. |
| **Household Split Rule** | Enforced same-RT split boundary. | `from_rt_id = to_rt_id` AND `from_household_id <> to_household_id`. | Guarantees household split occurs within the same RT unit. |
| **Transfer Internal Rule** | Enforced inter-RT community transfer. | `from_organization_id = to_organization_id` AND `from_rt_id <> to_rt_id` AND `from_household_id <> to_household_id`. | Prevents misusing internal transfer for intra-RT moves. |
| **Birth Date Sanity** | Omitted `CHECK (birth_date <= CURRENT_DATE)` at database level. | Column is `DATE NOT NULL`; future-date check reserved for domain validation. | Avoids PostgreSQL volatile constraint restriction while preserving strict domain validation. |
| **Current Household Delete** | Changed `residents.current_household_id` FK delete behavior. | **`ON DELETE RESTRICT`**. | Prevents silent clearing of resident household state upon household deletion. Disbandment must be an explicit domain action. |
| **Historical File Evidence** | Protected relocation and civil status evidentiary documents. | Changed file references to **`ON DELETE RESTRICT`**. | Aligns with foundation's `attachments` policy; physical file deletion cannot destroy historical evidence. Application lifecycle must prevent soft-deletion of evidence. |
| **Deletion Audit** | Audited all foreign keys across the domain. | Standardized on **`ON DELETE RESTRICT`** (except user accounts: `SET NULL`). | Guarantees zero cascading deletions; administrative history is permanent and indestructible. |

---

## 2. Canonical Sources of Truth

| Domain Concept | Canonical Source of Truth | Secondary / Derived Representation | Database Enforcement Mechanism |
| :--- | :--- | :--- | :--- |
| **Civil Identity** | `residents` table | Auto-filled into Letters / Invoices | Table-wide `UNIQUE (nik)` + 16-digit regex CHECK |
| **Head of Household** | `household_members` table | `Household::activeHead` Eloquent relation | Partial unique index (`relationship = 'KEPALA_KELUARGA' AND is_active = TRUE`) |
| **Household Composition**| `household_members` table | `residents.current_household_id` | Atomic transaction + Partial unique index (`UNIQUE (resident_id) WHERE is_active = TRUE`) |
| **Current Resident Scope**| `residents.rt_id` | Derives `rw_id` and `organization_id` | Composite FK `(current_household_id, rt_id) REFERENCES households(id, rt_id)` |
| **Current Household** | `residents.current_household_id` + `household_members.is_active` | Cached household roster | Composite FK on RT + atomic domain action |
| **Relocation History** | `residency_movements` table | Timeline audit views | Native PL/pgSQL append-only trigger + composite ancestry FKs |
| **Civil Status History** | `resident_status_history` table| `residents.status` | Native PL/pgSQL append-only trigger |
| **Authentication Link** | `users` table | `residents.user_id` (nullable 1-to-1) | Foreign key link; Unique constraint; Account lifecycle policy |
| **Administrative Scope** | `memberships` table | None (User's active roles/scopes) | `AccessResolver` evaluation |
| **Document Attachments** | `files` & `attachments` | Polymorphic morph-map link | Morph classes `resident` & `household` with `StoredFilePolicy` |

---

## 3. Scope Integrity Engineering

### 3.1 Household Member Scope Integrity (`household_members.rt_id`)
A resident residing in RT 03 must **never** be assigned as a member of a household in RT 02.

Trigger `household_members_enforce_scope_match()` executes `BEFORE INSERT OR UPDATE OF household_id, resident_id, is_active`:
- Uses `SELECT rt_id FROM residents WHERE id = NEW.resident_id FOR SHARE` to serialize against concurrent resident relocation.
- Guarantees `resident.rt_id == household.rt_id` at the moment of assignment without adding a denormalized scope column that would cause foreign key deadlocks upon resident relocation.

```sql
CREATE OR REPLACE FUNCTION household_members_enforce_scope_match()
RETURNS trigger AS $$
DECLARE
    v_household_rt UUID;
    v_resident_rt  UUID;
BEGIN
    IF NEW.is_active = TRUE THEN
        SELECT rt_id INTO v_household_rt
        FROM households
        WHERE id = NEW.household_id;

        -- Acquire row-level share lock on resident to prevent concurrent RT mutation
        SELECT rt_id INTO v_resident_rt
        FROM residents
        WHERE id = NEW.resident_id
        FOR SHARE;

        IF v_household_rt IS NULL THEN
            RAISE EXCEPTION 'Target household does not exist.'
                USING ERRCODE = 'foreign_key_violation';
        END IF;

        IF v_resident_rt IS NULL THEN
            RAISE EXCEPTION 'Target resident does not exist.'
                USING ERRCODE = 'foreign_key_violation';
        END IF;

        IF v_household_rt <> v_resident_rt THEN
            RAISE EXCEPTION 'Scope mismatch: Resident in RT (%) cannot join Household in RT (%).',
                v_resident_rt, v_household_rt
                USING ERRCODE = 'check_violation';
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER household_members_scope_guard
BEFORE INSERT OR UPDATE OF household_id, resident_id, is_active ON household_members
FOR EACH ROW EXECUTE FUNCTION household_members_enforce_scope_match();
```

---

## 4. Movement Integrity & Ancestry Engineering

### 4.1 Strict Organizational Ancestry via Composite Foreign Keys
`residency_movements` enforces ancestry using **6 composite foreign keys**:
1. `FOREIGN KEY (from_rw_id, from_organization_id) REFERENCES rws(id, organization_id) ON DELETE RESTRICT`
2. `FOREIGN KEY (from_rt_id, from_rw_id) REFERENCES rts(id, rw_id) ON DELETE RESTRICT`
3. `FOREIGN KEY (from_household_id, from_rt_id) REFERENCES households(id, rt_id) ON DELETE RESTRICT`
4. `FOREIGN KEY (to_rw_id, to_organization_id) REFERENCES rws(id, organization_id) ON DELETE RESTRICT`
5. `FOREIGN KEY (to_rt_id, to_rw_id) REFERENCES rts(id, rw_id) ON DELETE RESTRICT`
6. `FOREIGN KEY (to_household_id, to_rt_id) REFERENCES households(id, rt_id) ON DELETE RESTRICT`

### 4.2 Movement Type Nullability & Identity Enforcement (PostgreSQL CHECK Constraint)

```sql
ALTER TABLE residency_movements ADD CONSTRAINT residency_movements_nullability_check CHECK (
    (
        movement_type = 'MOVE_IN_EXTERNAL'
        AND from_organization_id IS NULL AND from_rw_id IS NULL AND from_rt_id IS NULL AND from_household_id IS NULL
        AND to_organization_id IS NOT NULL AND to_rw_id IS NOT NULL AND to_rt_id IS NOT NULL AND to_household_id IS NOT NULL
    )
    OR
    (
        movement_type = 'MOVE_OUT_EXTERNAL'
        AND from_organization_id IS NOT NULL AND from_rw_id IS NOT NULL AND from_rt_id IS NOT NULL AND from_household_id IS NOT NULL
        AND to_organization_id IS NULL AND to_rw_id IS NULL AND to_rt_id IS NULL AND to_household_id IS NULL
    )
    OR
    (
        movement_type = 'TRANSFER_INTERNAL'
        AND from_organization_id IS NOT NULL AND from_rw_id IS NOT NULL AND from_rt_id IS NOT NULL AND from_household_id IS NOT NULL
        AND to_organization_id IS NOT NULL AND to_rw_id IS NOT NULL AND to_rt_id IS NOT NULL AND to_household_id IS NOT NULL
        AND from_organization_id = to_organization_id
        AND from_rt_id <> to_rt_id
        AND from_household_id <> to_household_id
    )
    OR
    (
        movement_type = 'HOUSEHOLD_SPLIT'
        AND from_organization_id IS NOT NULL AND from_rw_id IS NOT NULL AND from_rt_id IS NOT NULL AND from_household_id IS NOT NULL
        AND to_organization_id IS NOT NULL AND to_rw_id IS NOT NULL AND to_rt_id IS NOT NULL AND to_household_id IS NOT NULL
        AND from_organization_id = to_organization_id
        AND from_rt_id = to_rt_id
        AND from_household_id <> to_household_id
    )
);
```

---

## 5. Deletion Audit & Historical Preservation

### 5.1 Exhaustive Foreign Key Deletion Audit

| Table | Foreign Key Column | Referenced Table | Action on Delete | Architectural Justification |
| :--- | :--- | :--- | :--- | :--- |
| `households` | `(rw_id, organization_id)` | `rws(id, organization_id)` | **RESTRICT** | RW cannot be deleted while containing households |
| `households` | `(rt_id, rw_id)` | `rts(id, rw_id)` | **RESTRICT** | RT cannot be deleted while containing households |
| `residents` | `user_id` | `users(id)` | **SET NULL** | Citizen civil record persists even if login account is revoked |
| `residents` | `(rw_id, organization_id)` | `rws(id, organization_id)` | **RESTRICT** | RW cannot be deleted while containing residents |
| `residents` | `(rt_id, rw_id)` | `rts(id, rw_id)` | **RESTRICT** | RT cannot be deleted while containing residents |
| `residents` | `(current_household_id, rt_id)` | `households(id, rt_id)` | **RESTRICT** | Prevents silent clearing of resident household state |
| `household_members` | `household_id` | `households(id)` | **RESTRICT** | Household cannot be deleted while family records exist |
| `household_members` | `resident_id` | `residents(id)` | **RESTRICT** | Resident cannot be deleted while family records exist |
| `residency_movements`| `resident_id` | `residents(id)` | **RESTRICT** | Historical citizen link cannot be deleted |
| `residency_movements`| `from_organization_id` | `organizations(id)` | **RESTRICT** | Historical origin organization cannot be deleted |
| `residency_movements`| `(from_rw_id, from_org_id)` | `rws(id, organization_id)` | **RESTRICT** | Historical origin RW cannot be deleted |
| `residency_movements`| `(from_rt_id, from_rw_id)` | `rts(id, rw_id)` | **RESTRICT** | Historical origin RT cannot be deleted |
| `residency_movements`| `(from_hh_id, from_rt_id)` | `households(id, rt_id)` | **RESTRICT** | Historical origin household cannot be deleted |
| `residency_movements`| `to_organization_id` | `organizations(id)` | **RESTRICT** | Historical destination organization cannot be deleted |
| `residency_movements`| `(to_rw_id, to_org_id)` | `rws(id, organization_id)` | **RESTRICT** | Historical destination RW cannot be deleted |
| `residency_movements`| `(to_rt_id, to_rw_id)` | `rts(id, rw_id)` | **RESTRICT** | Historical destination RT cannot be deleted |
| `residency_movements`| `(to_hh_id, to_rt_id)` | `households(id, rt_id)` | **RESTRICT** | Historical destination household cannot be deleted |
| `residency_movements`| `attachment_file_id` | `files(id)` | **RESTRICT** | Historical movement evidence cannot be physically purged |
| `residency_movements`| `recorded_by` | `users(id)` | **RESTRICT** | Operator audit trail is permanent |
| `resident_status_history`| `resident_id` | `residents(id)` | **RESTRICT** | Civil status history cannot be deleted |
| `resident_status_history`| `attachment_file_id` | `files(id)` | **RESTRICT** | Historical status change evidence cannot be physically purged |
| `resident_status_history`| `recorded_by` | `users(id)` | **RESTRICT** | Operator audit trail is permanent |

**Zero cascading deletes (`CASCADE`) exist across the domain.**

---

## 6. Table Contracts & Production Migration Mapping

### Actual Migration Files Created
1. `backend/database/migrations/2026_10_03_140001_create_households_table.php` (table: `households`)
2. `backend/database/migrations/2026_10_03_140002_create_residents_table.php` (table: `residents`)
3. `backend/database/migrations/2026_10_03_140003_create_household_members_table.php` (table: `household_members`)
4. `backend/database/migrations/2026_10_03_140004_create_residency_movements_table.php` (table: `residency_movements`)
5. `backend/database/migrations/2026_10_03_140005_create_resident_status_history_table.php` (table: `resident_status_history`)
6. `backend/database/migrations/2026_10_03_140006_create_resident_household_triggers.php` (triggers and functions)

---

## 7. Append-Only Immutability Triggers

```sql
CREATE OR REPLACE FUNCTION residency_movements_prevent_mutation() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'Residency movement records are immutable append-only events and cannot be updated or deleted.'
        USING ERRCODE = 'integrity_constraint_violation';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER residency_movements_append_only
BEFORE UPDATE OR DELETE ON residency_movements
FOR EACH ROW EXECUTE FUNCTION residency_movements_prevent_mutation();

CREATE OR REPLACE FUNCTION resident_status_history_prevent_mutation() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'Resident status history records are immutable append-only events and cannot be updated or deleted.'
        USING ERRCODE = 'integrity_constraint_violation';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER resident_status_history_append_only
BEFORE UPDATE OR DELETE ON resident_status_history
FOR EACH ROW EXECUTE FUNCTION resident_status_history_prevent_mutation();
```

---

## 8. Database vs Domain Invariant Matrix

| Invariant / Rule | Database Tier | Domain Service Tier | Technical Rationale |
| :--- | :--- | :--- | :--- |
| **NIK 16-Digit Format** | `CHECK (nik ~ '^[0-9]{16}$')` | Form Validation | Malformed strings rejected at the SQL engine level |
| **Lifetime NIK Uniqueness** | `UNIQUE (nik)` | Form Validation | Table-wide guarantee; deceased NIKs are never recycled |
| **One Active Household per Resident** | Partial Unique Index on `household_members` | Assignment Action | Engine guarantees citizen cannot belong to two families |
| **One Active Head per Household** | Partial Unique Index on `household_members` | Head Appointment Action| Engine guarantees family cannot have two heads |
| **Scope Ancestry (RT in RW in Org)** | Composite Foreign Keys | Scoped Action | Multi-tenant cross-wiring is physically impossible |
| **Resident & Household Scope Alignment** | Trigger `household_members_scope_guard` | Assignment Action | Cross-RT family membership rejected with concurrency row lock |
| **Current Household Scope Alignment** | Composite FK `(current_household_id, rt_id)` | Assignment Action | Cross-RT placement physically impossible |
| **Birth Date Sanity** | Column is `DATE NOT NULL` | Form Validation (`birth_date <= today`) | Reserved for domain validation to avoid PostgreSQL volatile CHECK constraints |
| **Movement Field Nullability** | PostgreSQL multi-branch `CHECK` | Action Validation | Enforces exact presence/absence of all 8 location FKs |
| **Movement Ancestry Lineage** | 6 Composite Foreign Keys | Action Validation | Engine verifies origin and destination ancestry chains |
| **Movement History Immutability** | PL/pgSQL Append-Only Trigger | Read-only Repository | Engine prevents SQL `UPDATE` and `DELETE` |
| **Civil Status History Immutability** | PL/pgSQL Append-Only Trigger | Read-only Repository | Engine prevents SQL `UPDATE` and `DELETE` |
| **State Transitions (e.g. PROSPECTIVE → ACTIVE)** | `CHECK` restricts valid enums | State Machine Service | Legal transition workflow belongs in domain layer |
| **Household `HEAD_VACANT` Synchronization**| `CHECK` restricts valid enums | Household Service | Multi-row family vacancy evaluation belongs in domain |
| **Historical Evidence Preservation** | `ON DELETE RESTRICT` on file FKs | File Service | Prevents physical deletion of evidentiary documents; app lifecycle guards soft-deletes |

---

## 9. Comprehensive Database Test Contract & Verification Results

### Test Suite: `Tests\Feature\ResidentHouseholdDatabaseIntegrityTest`
Executed against PostgreSQL with **27 passed tests (34 assertions)**:

1. `test_duplicate_nik_throws_unique_constraint_violation` (PASS)
2. `test_deceased_nik_cannot_be_reused_by_another_resident` (PASS)
3. `test_nik_format_regex_rejects_non_16_digits` (PASS)
4. `test_duplicate_active_kk_number_in_same_organization_is_rejected` (PASS)
5. `test_archived_or_dissolved_kk_number_may_be_reused` (PASS)
6. `test_multiple_active_heads_in_same_household_is_rejected` (PASS)
7. `test_one_resident_cannot_have_two_active_household_memberships` (PASS)
8. `test_resident_in_rt1_cannot_join_household_in_rt2_via_trigger` (PASS)
9. `test_resident_and_household_in_same_rt_membership_succeeds` (PASS)
10. `test_cross_rt_current_household_link_is_rejected_by_composite_fk` (PASS)
11. `test_household_mismatched_ancestry_is_rejected_by_composite_fk` (PASS)
12. `test_move_in_external_requires_null_from_and_not_null_to` (PASS)
13. `test_move_out_external_requires_not_null_from_and_null_to` (PASS)
14. `test_transfer_internal_in_same_rt_is_rejected` (PASS)
15. `test_transfer_internal_between_different_rts_succeeds` (PASS)
16. `test_household_split_in_different_rt_is_rejected` (PASS)
17. `test_household_split_within_same_rt_succeeds` (PASS)
18. `test_residency_movement_update_rejected_by_trigger` (PASS)
19. `test_residency_movement_delete_rejected_by_trigger` (PASS)
20. `test_resident_status_history_update_rejected_by_trigger` (PASS)
21. `test_resident_status_history_delete_rejected_by_trigger` (PASS)
22. `test_household_cannot_be_deleted_when_referenced_by_members` (PASS)
23. `test_household_cannot_be_deleted_when_referenced_by_resident_current_household` (PASS)
24. `test_resident_cannot_be_deleted_when_referenced_by_movements` (PASS)
25. `test_evidence_file_cannot_be_physically_deleted_when_referenced_by_movement` (PASS)
26. `test_concurrent_household_head_assignment_results_in_single_winner` (PASS)
27. `test_concurrent_resident_household_assignment_enforces_single_active_kk` (PASS)

---

## 10. Verification & Quality Gates

- **Backend Total Tests:** `php artisan test`  
  **57 passed (158 assertions)** across 8 feature test suites (0 failures).
- **Rollback Verification:**  
  `php artisan migrate:rollback --step=6` executed cleanly; all tables, triggers, and functions dropped without error; `php artisan migrate` re-applied without error.
- **Fresh Migration Verification:**  
  `php artisan migrate:fresh --database=pgsql` executed and created all tables cleanly.
- **Frontend Linter:** `oxlint`  
  **0 warnings, 0 errors** across 25 files.
- **Frontend Build:** `tsc -b && vite build`  
  **Built clean in 362ms**.
