# PHASE 21 — FINANCE / KAS RW DOMAIN FOUNDATION & API REPORT

## 1. Executive Summary

Phase 21 establishes the **Finance Domain Foundation and REST API** for the RW-centric RT/RW management platform. It delivers an auditable, resilient cash management system for RW and RT operational cash accounts.

Key highlights:
- **Ledger Immutability**: Posted financial transactions are append-only. A dedicated PostgreSQL trigger (`financial_transactions_append_only`) strictly prevents `UPDATE` and `DELETE` at the database engine level.
- **Auditable Single Reversal**: Corrections occur via explicit compensating reversal transactions (`is_reversal = true`, referencing `original_transaction_id`). A PostgreSQL unique partial index guarantees each transaction can be reversed at most once.
- **Integer Rupiah Arithmetic**: All monetary amounts are non-negative `BIGINT` (Rupiah). Float arithmetic and float database types are strictly prohibited.
- **Authoritative Scoping**: Cash accounts belong either to an RW or to an RT within its parent RW. Normal RT-scoped users can never access or mutate another RT's or an unrelated RW's finances. Identifier manipulation and cross-tenant IDOR attacks are blocked at the route, query, policy, and domain layers.
- **Zero Regressions & 100% Test Pass Rate**:
  - Backend: **272 passed (1,551 assertions)** — baseline increased by 37 tests and 258 assertions.
  - Frontend: **214 passed** (58 suites), 0 failures.
  - Frontend Lint: `oxlint` passed with 0 warnings, 0 errors across 131 files.
  - Frontend Build: `tsc -b && vite build` succeeded with exit code 0.

---

## 2. Financial Domain Decisions & Lifecycle Rules

### 2.1 Cash Accounts
- **Hierarchy & Scope**:
  - **RW Cash Account**: `rw_id` populated, `rt_id = NULL`. Represents cash belonging to the RW organization.
  - **RT Cash Account**: `rw_id` and `rt_id` populated. `rt_id` must belong to the specified `rw_id`.
- **Status Lifecycle**:
  - `ACTIVE`: Normal operations, accepts income and expense entries.
  - `CLOSED`: Closed by authorized personnel with a mandatory audit reason.
  - **Invariant**: No new transactions may be posted to a `CLOSED` account (enforced in domain action and tested).
- **Opening Balance**:
  - Stored as `opening_balance >= 0` upon account creation.
  - Immutable after creation.

### 2.2 Financial Transactions Ledger
- **Direction / Types**: `INCOME` and `EXPENSE`.
- **Ledger Immutability**:
  - Posted ledger entries are strictly immutable.
  - No generic update or delete endpoints exist.
  - An append-only PostgreSQL trigger aborts any attempted `UPDATE` or `DELETE` SQL statement.
- **Compensating Reversals**:
  - To correct an error, a user with `finance.transaction.reverse` permission triggers the reversal mutation.
  - The reversal entry has:
    - Same absolute `amount` as the original.
    - Direction opposite or identical based on reversal semantics (represented as `is_reversal = true` pointing to `original_transaction_id`).
    - Stored `reversal_reason` for complete auditability.
    - Recording actor automatically derived from authenticated session.
  - Database constraint (`unique_reversal_per_original_transaction`) prevents duplicate reversals even under race conditions.
  - Reversals themselves cannot be reversed.

### 2.3 Balance Formula
Account balance is an aggregate calculation derived deterministically from the immutable ledger:
$$\text{Current Balance} = \text{Opening Balance} + \text{Net Income} - \text{Net Expense}$$
Where:
- $\text{Net Income} = \sum(\text{Active Income}) - \sum(\text{Reversed Income})$
- $\text{Net Expense} = \sum(\text{Active Expense}) - \sum(\text{Reversed Expense})$

This ensures reversals counteract the original transaction effect exactly once. The aggregate-based service (`AccountBalanceService`) also provides `batchCurrentBalances(Collection $accounts)` to calculate list balances without N+1 queries.

---

## 3. Database Schema & Integrity Constraints

### 3.1 Migration `2026_10_14_100001_create_cash_accounts_table.php`
- `id` (UUID Primary Key)
- `organization_id` (UUID, FK -> `organizations.id` ON DELETE CASCADE)
- `rw_id` (UUID, FK -> `rws.id` ON DELETE RESTRICT)
- `rt_id` (UUID nullable, FK -> `rts.id` ON DELETE RESTRICT)
- `name` (VARCHAR 255)
- `account_number` (VARCHAR 100 nullable)
- `description` (TEXT nullable)
- `opening_balance` (BIGINT, default 0)
- `status` (VARCHAR 50, default 'ACTIVE')
- `closed_at` (TIMESTAMP nullable)
- `closed_by` (UUID nullable, FK -> `users.id` ON DELETE SET NULL)
- `close_reason` (TEXT nullable)
- Constraints:
  - `check_cash_accounts_status`: `status IN ('ACTIVE', 'CLOSED')`
  - `check_cash_accounts_opening_balance_non_negative`: `opening_balance >= 0`
  - Composite foreign keys:
    - `(organization_id, rw_id)` -> `rws(organization_id, id)`
    - `(organization_id, rw_id, rt_id)` -> `rts(organization_id, rw_id, id)`

### 3.2 Migration `2026_10_14_100002_create_finance_categories_table.php`
- `id` (UUID Primary Key)
- `organization_id` (UUID, FK -> `organizations.id` ON DELETE CASCADE)
- `rw_id` (UUID nullable, FK -> `rws.id` ON DELETE RESTRICT)
- `rt_id` (UUID nullable, FK -> `rts.id` ON DELETE RESTRICT)
- `name` (VARCHAR 255)
- `type` (VARCHAR 50): `INCOME` or `EXPENSE`
- `code` (VARCHAR 50 nullable)
- `description` (TEXT nullable)
- `is_active` (BOOLEAN, default TRUE)
- Constraints:
  - `check_finance_categories_type`: `type IN ('INCOME', 'EXPENSE')`
  - Composite FKs ensuring category hierarchy matches organization/RW/RT.

### 3.3 Migration `2026_10_14_100003_create_financial_transactions_table.php`
- `id` (UUID Primary Key)
- `organization_id` (UUID, FK -> `organizations.id` ON DELETE CASCADE)
- `rw_id` (UUID, FK -> `rws.id` ON DELETE RESTRICT)
- `rt_id` (UUID nullable, FK -> `rts.id` ON DELETE RESTRICT)
- `cash_account_id` (UUID, FK -> `cash_accounts.id` ON DELETE RESTRICT)
- `category_id` (UUID, FK -> `finance_categories.id` ON DELETE RESTRICT)
- `type` (VARCHAR 50): `INCOME` or `EXPENSE`
- `amount` (BIGINT, strictly positive)
- `transaction_date` (DATE)
- `description` (TEXT)
- `reference_number` (VARCHAR 100 nullable)
- `evidence_file_id` (UUID nullable, FK -> `stored_files.id` ON DELETE RESTRICT)
- `recorded_by` (UUID, FK -> `users.id` ON DELETE RESTRICT)
- `is_reversal` (BOOLEAN, default FALSE)
- `original_transaction_id` (UUID nullable, self-referencing FK -> `financial_transactions.id`)
- `reversal_reason` (TEXT nullable)
- Constraints & Indexes:
  - `check_financial_transactions_amount_positive`: `amount > 0`
  - `check_financial_transactions_type`: `type IN ('INCOME', 'EXPENSE')`
  - `check_financial_transactions_reversal_original`: Ensures `is_reversal = TRUE` has `original_transaction_id IS NOT NULL`.
  - Unique partial index:
    `CREATE UNIQUE INDEX unique_reversal_per_original_transaction ON financial_transactions (original_transaction_id) WHERE is_reversal = TRUE;`
  - PostgreSQL Append-Only Trigger:
    ```sql
    CREATE OR REPLACE FUNCTION financial_transactions_prevent_mutation()
    RETURNS TRIGGER AS $$
    BEGIN
        RAISE EXCEPTION 'Financial transactions are immutable append-only ledger entries and cannot be updated or deleted.';
    END;
    $$ LANGUAGE plpgsql;

    CREATE TRIGGER financial_transactions_append_only
    BEFORE UPDATE OR DELETE ON financial_transactions
    FOR EACH ROW
    EXECUTE FUNCTION financial_transactions_prevent_mutation();
    ```

---

## 4. Permissions & Role Mapping

### 4.1 Permission Keys (`PermissionKey.php`)
| Permission Key | Value | Description |
|---|---|---|
| `FinanceAccountView` | `finance.account.view` | View cash accounts, category definitions, and financial summaries |
| `FinanceAccountManage` | `finance.account.manage` | Create, update, or close cash accounts |
| `FinanceCategoryManage` | `finance.category.manage` | Create and configure financial income/expense categories |
| `FinanceTransactionView` | `finance.transaction.view` | View financial ledger transactions and entry details |
| `FinanceTransactionRecord` | `finance.transaction.record` | Record income and expense ledger entries |
| `FinanceTransactionReverse` | `finance.transaction.reverse` | Execute compensating reversal on posted financial transactions |

### 4.2 Role Mapping (`RoleKey.php`)
| Role | Level | Assigned Finance Permissions |
|---|---|---|
| `KetuaRw` | RW | `FinanceAccountView`, `FinanceAccountManage`, `FinanceCategoryManage`, `FinanceTransactionView`, `FinanceTransactionRecord`, `FinanceTransactionReverse` |
| `BendaharaRw` | RW | `FinanceAccountView`, `FinanceAccountManage`, `FinanceCategoryManage`, `FinanceTransactionView`, `FinanceTransactionRecord`, `FinanceTransactionReverse` |
| `SekretarisRw` | RW | `FinanceAccountView`, `FinanceTransactionView` |
| `KetuaRt` | RT | `FinanceAccountView`, `FinanceAccountManage`, `FinanceCategoryManage`, `FinanceTransactionView`, `FinanceTransactionRecord`, `FinanceTransactionReverse` (scoped to RT) |
| `BendaharaRt` | RT | `FinanceAccountView`, `FinanceAccountManage`, `FinanceCategoryManage`, `FinanceTransactionView`, `FinanceTransactionRecord`, `FinanceTransactionReverse` (scoped to RT) |
| `SekretarisRt` | RT | `FinanceAccountView`, `FinanceTransactionView` (scoped to RT) |
| `OrganizationAdmin` | Org | All finance capabilities org-wide |

---

## 5. REST API Routes & Contracts

All endpoints live under `/api/v1/finance/...` and enforce Sanctum authentication, `EnforceScopedAccess`, and `EnforcePrivacyBoundary`.

### 5.1 Accounts
- `GET /api/v1/finance/accounts`
  - Scoped query; returns paginated cash accounts with batch-calculated `current_balance`.
  - Filters: `status`, `rt_id`, `rw_id`, `q` (search on name and account_number).
- `GET /api/v1/finance/accounts/{id}`
  - Returns account detail with current balance.
- `GET /api/v1/finance/accounts/{id}/summary`
  - Returns full breakdown: `opening_balance`, `current_balance`, `total_income`, `total_expense`, `active_income`, `active_expense`, `reversed_income`, `reversed_expense`, `transactions_count`.
- `POST /api/v1/finance/accounts`
  - Requires `Idempotency-Key` header.
  - Accepted payload: `name` (required), `rt_id` (nullable), `account_number` (optional), `description` (optional), `opening_balance` (optional integer $\ge 0$).
  - Scope is authoritatively resolved from RT or authenticated RW membership. `rw_id` and `organization_id` in payload are strictly rejected.
- `PATCH /api/v1/finance/accounts/{id}`
  - Accepted payload: `name`, `account_number`, `description`.
  - `opening_balance`, `status`, `organization_id`, `rw_id` are strictly rejected.
- `POST /api/v1/finance/accounts/{id}/close`
  - Closes active account. Accepted payload: `reason` (required).

### 5.2 Categories
- `GET /api/v1/finance/categories`
  - Lists categories in the caller's organization and scope.
  - Filters: `type` (`INCOME`, `EXPENSE`), `is_active`, `q` (search name and code).
- `GET /api/v1/finance/categories/{id}`
  - Reads category detail.
- `POST /api/v1/finance/categories`
  - Accepted payload: `name`, `type`, `rt_id`, `code`, `description`.
- `PATCH /api/v1/finance/categories/{id}`
  - Accepted payload: `name`, `code`, `description`.
  - `type`, `is_active`, scope fields are strictly rejected.

### 5.3 Transactions & Ledger
- `GET /api/v1/finance/transactions`
  - Scoped list query with eager-loaded `account`, `category`, and `recorder:id,name,email`.
  - Filters: `cash_account_id`, `category_id`, `type`, `is_reversal`, `date_from`, `date_to`, `q` (search description and reference_number).
- `GET /api/v1/finance/transactions/{id}`
  - Transaction detail with related reversal/original entry links.
- `POST /api/v1/finance/transactions`
  - Requires `Idempotency-Key` header.
  - Accepted payload: `cash_account_id`, `category_id`, `type`, `amount` ($> 0$), `transaction_date`, `description`, `reference_number`, `evidence_file_id`.
  - Validates that category direction matches transaction direction.
  - Validates that target cash account is `ACTIVE`.
- `POST /api/v1/finance/transactions/{id}/reverse`
  - Requires `Idempotency-Key` header.
  - Accepted payload: `reversal_reason` (required), `transaction_date` (optional).
  - Creates compensating transaction and returns 201 with reversal entry.
  - Duplicate attempt returns HTTP 409 `transaction_already_reversed`.

---

## 6. Audit & Idempotency Integration

- **Idempotency**:
  - `POST /api/v1/finance/accounts`, `POST /api/v1/finance/transactions`, and `POST /api/v1/finance/transactions/{id}/reverse` require `Idempotency-Key` (`EnsureIdempotency` middleware with `required` mode).
  - Replaying identical payloads returns the original cached response with `Idempotent-Replayed: true`.
  - Modifying payload with same key returns HTTP 422 `idempotency_key_reused`.
- **Audit Logging**:
  - All domain actions (`CreateCashAccountAction`, `UpdateCashAccountDetailsAction`, `CloseCashAccountAction`, `CreateFinanceCategoryAction`, `UpdateFinanceCategoryAction`, `RecordFinancialTransactionAction`, `ReverseFinancialTransactionAction`) record structured audit entries via `AuditLogger`.
  - Stored audit logs are append-only.

---

## 7. Verification & Test Results

### 7.1 Backend Test Results
Command executed:
```bash
php artisan test
```
Result:
```
Tests:    272 passed (1551 assertions)
Duration: 165.67s
```

Detailed breakdown:
- `Tests\Feature\FinanceDomainActionTest`: **15 passed, 70 assertions**
  - Scope creation, negative opening balance rejection, lifecycle transitions, closed account blocking, positive amount checks, category type mismatch checks, reversal mechanics, single reversal protection, append-only trigger enforcement.
- `Tests\Feature\FinanceAuthorizationPolicyTest`: **5 passed, 64 assertions**
  - Scope boundaries (RT vs RW vs Org), permission isolation between roles, IDOR rejection.
- `Tests\Feature\FinanceDatabaseIntegrityTest`: **7 passed, 9 assertions**
  - PostgreSQL trigger updates/deletes rejection, amount positive check constraint, opening balance non-negative check constraint, unique reversal partial index, foreign key restrict on accounts and categories.
- `Tests\Feature\FinanceApiTest`: **10 passed, 101 assertions**
  - Account listing, search, filtering, detail, summary calculation, contract validation, category filtering, transaction recording, idempotency replays, duplicate reversal 409 rejection, cross-tenant IDOR protection.
- Pre-existing suites (`OverviewApiTest`, `ResidentHousehold*`, `ResourceSerializationTest`, `AuthenticationTest`, `AuthorizationScopeTest`, `FileStorageTest`, `IdempotencyTest`, `DatabaseIntegrityTest`): **235 passed**.

### 7.2 Frontend Verification
Command executed:
```bash
npm test
```
Result:
```
ℹ tests 214
ℹ suites 58
ℹ pass 214
ℹ fail 0
ℹ cancelled 0
ℹ skipped 0
ℹ duration_ms 2645.3797
```

Command executed:
```bash
npm run lint
```
Result:
```
> frontend@0.0.0 lint
> oxlint
Found 0 warnings and 0 errors.
Finished in 181ms on 131 files with 116 rules using 8 threads.
```

Command executed:
```bash
npm run build
```
Result:
```
> frontend@0.0.0 build
> tsc -b && vite build
✓ 254 modules transformed.
✓ built in 1.55s
```

---

## 8. Files Created and Modified

### Created Files
- `database/migrations/2026_10_14_100001_create_cash_accounts_table.php`
- `database/migrations/2026_10_14_100002_create_finance_categories_table.php`
- `database/migrations/2026_10_14_100003_create_financial_transactions_table.php`
- `app/Domain/Finance/Enums/CashAccountStatus.php`
- `app/Domain/Finance/Enums/TransactionType.php`
- `app/Domain/Finance/Exceptions/CashAccountClosedException.php`
- `app/Domain/Finance/Exceptions/CategoryTypeMismatchException.php`
- `app/Domain/Finance/Exceptions/FinanceScopeMismatch.php`
- `app/Domain/Finance/Exceptions/InvalidTransactionException.php`
- `app/Domain/Finance/Exceptions/TransactionAlreadyReversedException.php`
- `app/Domain/Finance/Models/CashAccount.php`
- `app/Domain/Finance/Models/FinanceCategory.php`
- `app/Domain/Finance/Models/FinancialTransaction.php`
- `app/Domain/Finance/DTOs/CreateCashAccountInput.php`
- `app/Domain/Finance/DTOs/UpdateCashAccountInput.php`
- `app/Domain/Finance/DTOs/CloseCashAccountInput.php`
- `app/Domain/Finance/DTOs/CreateFinanceCategoryInput.php`
- `app/Domain/Finance/DTOs/UpdateFinanceCategoryInput.php`
- `app/Domain/Finance/DTOs/RecordFinancialTransactionInput.php`
- `app/Domain/Finance/DTOs/ReverseFinancialTransactionInput.php`
- `app/Domain/Finance/Services/AccountBalanceService.php`
- `app/Domain/Finance/Actions/CreateCashAccountAction.php`
- `app/Domain/Finance/Actions/UpdateCashAccountDetailsAction.php`
- `app/Domain/Finance/Actions/CloseCashAccountAction.php`
- `app/Domain/Finance/Actions/CreateFinanceCategoryAction.php`
- `app/Domain/Finance/Actions/UpdateFinanceCategoryAction.php`
- `app/Domain/Finance/Actions/RecordFinancialTransactionAction.php`
- `app/Domain/Finance/Actions/ReverseFinancialTransactionAction.php`
- `app/Domain/Finance/Queries/ListCashAccountsQuery.php`
- `app/Domain/Finance/Queries/GetCashAccountQuery.php`
- `app/Domain/Finance/Queries/ListFinanceCategoriesQuery.php`
- `app/Domain/Finance/Queries/ListFinancialTransactionsQuery.php`
- `app/Domain/Finance/Queries/GetFinancialTransactionQuery.php`
- `app/Policies/CashAccountPolicy.php`
- `app/Policies/FinanceCategoryPolicy.php`
- `app/Policies/FinancialTransactionPolicy.php`
- `app/Http/Requests/Finance/CreateCashAccountRequest.php`
- `app/Http/Requests/Finance/UpdateCashAccountRequest.php`
- `app/Http/Requests/Finance/CloseCashAccountRequest.php`
- `app/Http/Requests/Finance/ListCashAccountsRequest.php`
- `app/Http/Requests/Finance/CreateFinanceCategoryRequest.php`
- `app/Http/Requests/Finance/UpdateFinanceCategoryRequest.php`
- `app/Http/Requests/Finance/ListFinanceCategoriesRequest.php`
- `app/Http/Requests/Finance/RecordFinancialTransactionRequest.php`
- `app/Http/Requests/Finance/ReverseFinancialTransactionRequest.php`
- `app/Http/Requests/Finance/ListFinancialTransactionsRequest.php`
- `app/Http/Resources/CashAccountResource.php`
- `app/Http/Resources/FinanceCategoryResource.php`
- `app/Http/Resources/FinancialTransactionResource.php`
- `app/Http/Resources/AccountSummaryResource.php`
- `app/Http/Controllers/Api/V1/CashAccountController.php`
- `app/Http/Controllers/Api/V1/FinanceCategoryController.php`
- `app/Http/Controllers/Api/V1/FinancialTransactionController.php`
- `tests/Feature/FinanceDomainActionTest.php`
- `tests/Feature/FinanceAuthorizationPolicyTest.php`
- `tests/Feature/FinanceDatabaseIntegrityTest.php`
- `tests/Feature/FinanceApiTest.php`

### Modified Files
- `app/Domain/Authorization/Enums/PermissionKey.php` (added 6 finance permissions)
- `app/Domain/Authorization/Enums/RoleKey.php` (mapped permissions to roles)
- `app/Support/Api/Contracts/MutationContract.php` (registered finance operations, accepted/rejected field rules, and mandatory idempotency)
- `app/Support/Api/Contracts/QueryContract.php` (registered filters, sorts, search fields for finance resources)
- `routes/api.php` (registered 14 finance routes with idempotency middleware)

---

## 9. Conclusion

Phase 21 is fully implemented and passes all tests with zero regressions. The financial foundation provides complete ledger immutability, single-compensating reversal guarantees, strict PostgreSQL integrity checks, authoritative hierarchical scoping, and standardized REST API endpoints ready for future UI integration.
