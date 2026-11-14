# Overview Read Model Architecture & Aggregate Specification

## 1. Executive Summary

The **Overview Read Model** provides the authoritative demographic and domestic unit aggregation layer for the RT/RW platform. It exposes a single versioned endpoint:

```text
GET /api/v1/overview
```

This read model directly powers administrative and coordination dashboards (such as the Phase 15 RW Overview Dashboard), eliminating the need for client-side collection hydration or ad-hoc calculation in React.

---

## 2. Architecture & Request Pipeline

The Overview pipeline is strictly read-only and database-driven:

```text
HTTP GET /api/v1/overview
    ↓
auth:sanctum & active_account middleware
    ↓
GetOverviewRequest (prohibits query manipulation)
    ↓
OverviewController
    ↓
Scope & Authority Resolution (AccessResolver & OverviewPolicy)
    ↓
GetOverviewQuery (App\Domain\Overview\Queries\GetOverviewQuery)
    ↓
PostgreSQL Aggregation (FILTER & GROUP BY queries)
    ↓
OverviewSnapshot DTO (DTO Layer)
    ↓
OverviewResource (Serialization Only)
    ↓
ApiResponse (Canonical JSON Success Envelope)
    ↓
React Data Layer (useOverview() via TanStack Query)
```

### Architectural Invariants:
1. **No Collection Hydration:** The read model never fetches collections of `Resident` or `Household` models into PHP memory. All calculations occur database-side.
2. **Fixed Query Bound (O(1)):** Adding more child RT units does not generate additional SQL queries.
3. **No Idempotency / No Audit Trail on Read:** Normal read operations do not allocate idempotency records or populate mutation audit logs.
4. **Zero Model Mutation:** `GetOverviewQuery` does not modify resident, household, or membership records.

---

## 3. Scope Resolution & Multi-Membership Semantics

Authority is derived strictly on the server from the authenticated actor's active memberships. Client-supplied parameters such as `?organization_id=`, `?rw_id=`, or `?rt_id=` are disallowed and rejected with `422 validation_failed`.

### 3.1 Primary Scope Priority
When an actor possesses active memberships at multiple levels, the backend deterministically resolves the primary active scope according to organizational hierarchy:
1. **Organization Scope (`ScopeLevel::Organization`):** Highest administrative authority; aggregates all child RWs and RTs in the organization.
2. **RW Scope (`ScopeLevel::Rw`):** RW coordination authority; aggregates the specified RW and all its child RTs.
3. **RT Scope (`ScopeLevel::Rt`):** Local administration authority; aggregates only the specified RT.

### 3.2 RT-Scoped Isolation
For an RT-scoped actor, `rt_breakdown` contains strictly the authorized RT. Data from other RTs in the same RW or outside the RW is completely excluded.

---

## 4. Source Tables & Database Mapping

| Aggregate Group | Source Table | Filter Criteria | Key Aggregation Columns |
|---|---|---|---|
| **Population** | `residents` | `organization_id`, `rw_id`, or `rt_id` | `status` (`ACTIVE`, `PROSPECTIVE`, `INACTIVE`, `MOVED`, `DECEASED`) |
| **Households** | `households` | `organization_id`, `rw_id`, or `rt_id` | `status` (`ACTIVE`, `HEAD_VACANT`, `MOVED`, `DISSOLVED`) |
| **Household Head** | `household_members` | Linked via `household_id` subquery | `relationship = 'KEPALA_KELUARGA'`, `is_active = true` |
| **RT Units** | `rts` | `rw_id` or `organization_id` | `id`, `number`, `name`, `status = 'ACTIVE'` |

---

## 5. Metric Definitions

### 5.1 Population Lifecycle Metrics (`population`)
- **`total`:** The sum of all residents registered in the authorized scope across all 5 lifecycle statuses (`ACTIVE`, `PROSPECTIVE`, `INACTIVE`, `MOVED`, `DECEASED`). It is an all-inclusive demographic tally, not limited to active residents.
- **`active`:** Residents with `status = 'ACTIVE'`.
- **`prospective`:** Residents with `status = 'PROSPECTIVE'`.
- **`inactive`:** Residents with `status = 'INACTIVE'`.
- **`moved`:** Residents with `status = 'MOVED'`.
- **`deceased`:** Residents with `status = 'DECEASED'`. Deceased individuals are permanently segregated from active residents.

### 5.2 Household Composition Metrics (`households`)
- **`active`:** Households with `status = 'ACTIVE'`.
- **`head_assigned`:** Households with `status = 'ACTIVE'` having an active head membership (`relationship = 'KEPALA_KELUARGA' AND is_active = true`).
- **`head_vacant`:** Households with `status = 'HEAD_VACANT'`.
- **`moved`:** Households with `status = 'MOVED'`.
- **`dissolved`:** Households with `status = 'DISSOLVED'`. Historical head memberships of dissolved households never count toward active `head_assigned`.

### 5.3 RT Breakdown (`rt_breakdown[]`)
For each visible RT:
- `rt_id`: UUID of the RT.
- `rt_code`: Zero-padded string code (e.g. `'01'`).
- `resident_count`: Total residents registered in the RT.
- `household_count`: Active households in the RT.
- `head_vacant_household_count`: Head-vacant households in the RT.

---

## 6. Query Strategy & Performance

`GetOverviewQuery` executes a fixed batch of 4 optimized SQL queries regardless of the number of RT units:

```sql
-- Query 1: Population Aggregation
SELECT
    COUNT(*)::int AS total,
    COUNT(*) FILTER (WHERE status = 'ACTIVE')::int AS active,
    COUNT(*) FILTER (WHERE status = 'PROSPECTIVE')::int AS prospective,
    COUNT(*) FILTER (WHERE status = 'INACTIVE')::int AS inactive,
    COUNT(*) FILTER (WHERE status = 'MOVED')::int AS moved,
    COUNT(*) FILTER (WHERE status = 'DECEASED')::int AS deceased
FROM residents
WHERE rw_id = :rw_id;

-- Query 2: Household Aggregation
SELECT
    COUNT(*) FILTER (WHERE status = 'ACTIVE')::int AS active,
    COUNT(*) FILTER (WHERE status = 'HEAD_VACANT')::int AS head_vacant,
    COUNT(*) FILTER (WHERE status = 'MOVED')::int AS moved,
    COUNT(*) FILTER (WHERE status = 'DISSOLVED')::int AS dissolved,
    COUNT(*) FILTER (
        WHERE status = 'ACTIVE' AND id IN (
            SELECT household_id FROM household_members
            WHERE relationship = 'KEPALA_KELUARGA' AND is_active = true
        )
    )::int AS head_assigned
FROM households
WHERE rw_id = :rw_id;

-- Query 3: Visible RT Units
SELECT id, number, name FROM rts
WHERE rw_id = :rw_id AND status = 'ACTIVE'
ORDER BY number ASC;

-- Query 4: Grouped Aggregations per RT
SELECT rt_id, COUNT(*)::int AS resident_count
FROM residents WHERE rw_id = :rw_id GROUP BY rt_id;

SELECT rt_id,
    COUNT(*) FILTER (WHERE status = 'ACTIVE')::int AS household_count,
    COUNT(*) FILTER (WHERE status = 'HEAD_VACANT')::int AS head_vacant_household_count
FROM households WHERE rw_id = :rw_id GROUP BY rt_id;
```

---

## 7. Consistency Model & Invalidation

The Overview Read Model reflects real-time database state without intermediate cache lag.

On the frontend, TanStack Query caches the overview under `['overview', 'current']`. The cache is automatically invalidated by any domain mutation that alters population or household state:

- **Resident Mutations:** Registration, verification, status change, relocation, departure from household.
- **Household Mutations:** Creation, adding member, removing member, changing head, moving household, dissolving household.

Profile updates (editing non-demographic fields like blood type or phone) do not invalidate overview metrics.

---

## 8. Privacy & Data Minimization

The Overview response strictly omits sensitive personally identifiable information (PII):
- No NIK.
- No Kartu Keluarga (KK) numbers.
- No citizen names.
- No physical addresses or dwelling notes.
- No phone numbers or communication channels.
- No resident arrays or hydrated member collections.

---

## 9. Future Extension Boundaries

When multi-scope switching is introduced in a future phase:
- Scope switching must be mediated by explicit token/membership context or validated session switching, not arbitrary query parameters.
- Temporal snapshotting (historical overview by date) will be added as a distinct query with effective-date parameters.
