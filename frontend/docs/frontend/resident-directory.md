# Resident Directory & Resident Detail UI

> **Phase 17** — Read-only resident browsing feature.

## Overview

The Resident Directory provides a searchable, filterable, paginated directory of all residents within the RW's scope. The Resident Detail page shows full resident identity, household assignment, status history, and movement history for a single resident.

Both pages are **READ-ONLY**. No mutation buttons exist anywhere.

---

## Routes

| Path | Component | Description |
|------|-----------|-------------|
| `/app/residents` | `ResidentsPage` | Searchable resident directory |
| `/app/residents/:residentId` | `ResidentDetailPage` | Resident detail view |

---

## Directory Page (`/app/residents`)

### URL Query State

All filter/search/pagination state is persisted in URL query parameters:

| Param | Type | Description |
|-------|------|-------------|
| `q` | string | Debounced name search (300ms) |
| `status` | `ResidentStatus \| ''` | Filter by status |
| `gender` | `ResidentGender \| ''` | Filter by gender |
| `residency_type` | `ResidencyType \| ''` | Filter by residency type |
| `marital_status` | `ResidentMaritalStatus \| ''` | Filter by marital status |
| `sort` | sort token | Sort order |
| `page` | number | Current page (default 1) |

Browser back/forward preserves filter state automatically via React Router `useSearchParams`.

### Search Debounce

- Local state `searchInput` controls the text input (instant feedback)
- 300ms debounce writes to URL `q` param
- Prevents excessive API calls while typing

### Layout

| Breakpoint | Layout |
|-----------|--------|
| `< md` | Mobile: `ResidentCard` list |
| `≥ md` | Desktop: `ResidentTable` |

### Data Hook

```ts
useResidents({
  page,
  per_page: 20,
  q,           // from URL
  status,      // from URL
  gender,      // from URL
  residency_type, // from URL
  marital_status, // from URL
  sort,        // from URL
})
```

### Empty States

- **No residents at all**: "Belum ada warga terdaftar"
- **No results for search/filter**: "Tidak ada warga yang cocok"
- **Error**: `Alert` component with error message

---

## Detail Page (`/app/residents/:residentId`)

### Data Hook

```ts
useResident(residentId, {
  include: ['status_history', 'movements']
})
```

### Sections

| Section | Component | Source fields |
|---------|-----------|---------------|
| Identity | `ResidentIdentitySection` | All `Resident` fields by tier |
| Household | `ResidentHouseholdSection` | `current_household_id` |
| Status History | `ResidentStatusHistorySection` | `resident.status_history[]` |
| Movement History | `ResidentMovementHistory` | `resident.movements[]` |

### Error Handling

- `404`: "Data warga tidak ditemukan" with back link
- Other errors: Generic error message with back link
- Loading: `DetailSkeleton` (animated pulse)

---

## Privacy Tiers

Server controls which fields are returned based on the caller's authorization level.

| Tier | Available fields |
|------|-----------------|
| `directory` | `id`, `name`, `gender`, `age`, `residency_type`, `status`, org/rw/rt IDs |
| `normal` | + `birth_place`, `birth_date`, `marital_status`, `occupation`, `education`, `current_household_id`, `nik` (may be masked), `phone` (may be masked) |
| `sensitive` | + `religion`, `blood_type`, `user_id` |

**Implementation rule**: Fields are rendered as-is from the server. Masked values (e.g. `320101******0005`) are valid and display as returned. Fields absent from the response are silently omitted — no `"—"` fallback for sensitive fields.

---

## Component Map

```
src/features/residents/
├── api/
│   ├── residentApi.ts          # HTTP client methods
│   ├── residentKeys.ts         # TanStack Query key factory
│   └── residentQueries.ts      # useResidents(), useResident() hooks
├── components/
│   ├── residentConstants.ts    # RESIDENT_STATUS_MAP (pure constants, no JSX)
│   ├── ResidentStatusBadge.tsx # Canonical status badge
│   ├── ResidentTable.tsx       # Desktop table + skeleton
│   ├── ResidentCard.tsx        # Mobile card + skeleton
│   ├── ResidentFilters.tsx     # Search + collapsible filter panel
│   ├── ResidentPagination.tsx  # Backend-driven windowed pagination
│   ├── ResidentIdentitySection.tsx   # Detail: identity fields
│   ├── ResidentHouseholdSection.tsx  # Detail: household assignment
│   ├── ResidentStatusHistory.tsx     # Detail: status history timeline
│   └── ResidentMovementHistory.tsx   # Detail: movement history timeline
└── pages/
    ├── ResidentsPage.tsx        # /app/residents
    └── ResidentDetailPage.tsx   # /app/residents/:residentId
```

---

## Architecture Decisions

### URL as Source of Truth
Filter/search/pagination state lives in URL params rather than React state. This enables:
- Browser back/forward navigation with full filter preservation
- Shareable/bookmarkable filtered views
- No desync between UI and navigation state

### No Scope Switcher
The RT filter does not allow arbitrary UUID entry. The backend only returns residents authorized for the calling user's scope. RT filtering via `rt_id` is intentionally excluded from the UI to prevent scope confusion.

### Debounce at Feature Layer
Search debounce (300ms) is implemented in `ResidentsPage` via `setTimeout`/`useEffect`, not in the API client or query layer. This keeps the HTTP layer stateless.

### `residentConstants.ts` (separate from `.tsx`)
`RESIDENT_STATUS_MAP` is defined in a pure TypeScript file (no JSX) to satisfy the `react/only-export-components` oxlint rule, which warns when component files export non-component values. Components import from `residentConstants.ts` directly.

---

## Testing

Tests: `tests/residents.test.ts`

Covers:
- `residentKeys` query key factory — namespacing, stability, collision avoidance
- `RESIDENT_STATUS_MAP` — all 5 statuses have Indonesian labels and required display properties
- `ResidentFilterState` shape — all required filter keys present
- Pagination math — `from`/`to` calculation, page range windowing, edge cases
- Privacy contract — list/detail query keys never encode NIK/phone/religion
- `residentApi` function surface — `list`, `getById`, `register`, `updateProfile`, `verify`, `relocate`, `changeStatus`
