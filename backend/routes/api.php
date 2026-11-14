<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BudgetPlanController;
use App\Http\Controllers\Api\V1\CashAccountController;
use App\Http\Controllers\Api\V1\CashTransferController;
use App\Http\Controllers\Api\V1\ExpenditureRequestController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\FinanceCategoryController;
use App\Http\Controllers\Api\V1\FinanceReportController;
use App\Http\Controllers\Api\V1\FinancialPeriodClosureController;
use App\Http\Controllers\Api\V1\FinancialTransactionController;
use App\Http\Controllers\Api\V1\HouseholdController;
use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\OverviewController;
use App\Http\Controllers\Api\V1\ResidentController;
use App\Http\Controllers\Api\V1\RtController;
use App\Http\Controllers\Api\V1\RwController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Public authentication routes
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    // Authenticated API routes
    Route::middleware(['auth:sanctum', 'active_account'])->group(function (): void {
        // Identity & session
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Overview Read Model (Aggregate)
        Route::get('/overview', [OverviewController::class, 'show']);

        // Organizations & units
        Route::get('/organizations', [OrganizationController::class, 'index']);
        Route::get('/organizations/{organization}', [OrganizationController::class, 'show']);
        Route::get('/organizations/{organization}/rws', [RwController::class, 'index']);

        Route::get('/rws/{rw}', [RwController::class, 'show']);
        Route::get('/rws/{rw}/rts', [RtController::class, 'index']);
        Route::post('/rws/{rw}/rts', [RtController::class, 'store'])->middleware('idempotent');

        Route::get('/rts/{rt}', [RtController::class, 'show']);

        // Memberships (Role assignment & scoped access)
        Route::get('/memberships', [MembershipController::class, 'index']);
        Route::post('/memberships', [MembershipController::class, 'store'])->middleware('idempotent');
        Route::delete('/memberships/{membership}', [MembershipController::class, 'destroy']);

        // File management
        Route::post('/files', [FileController::class, 'upload']);
        Route::get('/files/{file}', [FileController::class, 'show']);
        Route::get('/files/{file}/download', [FileController::class, 'download']);

        // Audit trails
        Route::get('/audit-logs', [AuditLogController::class, 'index']);

        // Residents
        Route::get('/residents', [ResidentController::class, 'index']);
        Route::post('/residents', [ResidentController::class, 'store'])->middleware('idempotent:required');
        Route::get('/residents/{resident}', [ResidentController::class, 'show']);
        Route::patch('/residents/{resident}', [ResidentController::class, 'update'])->middleware('idempotent');
        Route::post('/residents/{resident}/verify', [ResidentController::class, 'verify'])->middleware('idempotent');
        Route::post('/residents/{resident}/relocate', [ResidentController::class, 'relocate'])->middleware('idempotent:required');
        Route::post('/residents/{resident}/status', [ResidentController::class, 'status'])->middleware('idempotent');
        Route::post('/residents/{resident}/remove-from-household', [ResidentController::class, 'removeFromHousehold'])->middleware('idempotent');

        // Households
        Route::get('/households', [HouseholdController::class, 'index']);
        Route::post('/households', [HouseholdController::class, 'store'])->middleware('idempotent:required');
        Route::get('/households/{household}', [HouseholdController::class, 'show']);
        Route::patch('/households/{household}', [HouseholdController::class, 'update'])->middleware('idempotent');
        Route::post('/households/{household}/members', [HouseholdController::class, 'addMember'])->middleware('idempotent:required');
        Route::delete('/households/{household}/members/{resident}', [HouseholdController::class, 'removeMember'])->middleware('idempotent');
        Route::post('/households/{household}/change-head', [HouseholdController::class, 'changeHead'])->middleware('idempotent');
        Route::post('/households/{household}/move', [HouseholdController::class, 'move'])->middleware('idempotent:required');
        Route::post('/households/{household}/dissolve', [HouseholdController::class, 'dissolve'])->middleware('idempotent');

        // Finance: Cash Accounts
        Route::get('/finance/accounts', [CashAccountController::class, 'index']);
        Route::post('/finance/accounts', [CashAccountController::class, 'store'])->middleware('idempotent:required');
        Route::get('/finance/accounts/{cashAccount}', [CashAccountController::class, 'show']);
        Route::patch('/finance/accounts/{cashAccount}', [CashAccountController::class, 'update'])->middleware('idempotent');
        Route::post('/finance/accounts/{cashAccount}/close', [CashAccountController::class, 'close'])->middleware('idempotent');
        Route::get('/finance/accounts/{cashAccount}/summary', [CashAccountController::class, 'summary']);

        // Finance: Categories
        Route::get('/finance/categories', [FinanceCategoryController::class, 'index']);
        Route::post('/finance/categories', [FinanceCategoryController::class, 'store'])->middleware('idempotent');
        Route::get('/finance/categories/{financeCategory}', [FinanceCategoryController::class, 'show']);
        Route::patch('/finance/categories/{financeCategory}', [FinanceCategoryController::class, 'update'])->middleware('idempotent');

        // Finance: Ledger Transactions
        Route::get('/finance/transactions', [FinancialTransactionController::class, 'index']);
        Route::post('/finance/transactions', [FinancialTransactionController::class, 'store'])->middleware('idempotent:required');
        Route::get('/finance/transactions/{financialTransaction}', [FinancialTransactionController::class, 'show']);
        Route::post('/finance/transactions/{financialTransaction}/reverse', [FinancialTransactionController::class, 'reverse'])->middleware('idempotent:required');
        Route::get('/finance/transactions/{financialTransaction}/document', [FinancialTransactionController::class, 'document']);
        Route::get('/finance/transactions/{financialTransaction}/evidence', [FinancialTransactionController::class, 'listEvidence']);
        Route::post('/finance/transactions/{financialTransaction}/evidence', [FinancialTransactionController::class, 'attachEvidence'])->middleware('idempotent');
        Route::get('/finance/transactions/{financialTransaction}/evidence/download', [FinancialTransactionController::class, 'downloadPrimaryEvidence']);
        Route::get('/finance/transactions/{financialTransaction}/evidence/{file}/download', [FinancialTransactionController::class, 'downloadEvidence']);

        // Finance: Reports & Analytics
        Route::get('/finance/reports/summary', [FinanceReportController::class, 'summary']);
        Route::get('/finance/reports/cash-flow', [FinanceReportController::class, 'cashFlow']);
        Route::get('/finance/reports/categories', [FinanceReportController::class, 'categories']);
        Route::get('/finance/reports/rt-breakdown', [FinanceReportController::class, 'rtBreakdown']);
        Route::get('/finance/reports/accounts', [FinanceReportController::class, 'accounts']);
        Route::get('/finance/reports/export', [FinanceReportController::class, 'export']);

        // Finance: Period Closures & Reconciliation (Phase 25)
        Route::get('/finance/period-closures', [FinancialPeriodClosureController::class, 'index']);
        Route::get('/finance/period-closures/{financialPeriodClosure}', [FinancialPeriodClosureController::class, 'show']);
        Route::get('/finance/accounts/{cashAccount}/periods/{year}/{month}/preview', [FinancialPeriodClosureController::class, 'preview']);
        Route::post('/finance/accounts/{cashAccount}/periods/{year}/{month}/close', [FinancialPeriodClosureController::class, 'close'])->middleware('idempotent:required');

        // Finance: Internal Cash Transfers (Phase 26)
        Route::get('/finance/transfers', [CashTransferController::class, 'index']);
        Route::post('/finance/transfers', [CashTransferController::class, 'store'])->middleware('idempotent:required');
        Route::get('/finance/transfers/{cashTransfer}', [CashTransferController::class, 'show']);
        Route::post('/finance/transfers/{cashTransfer}/reverse', [CashTransferController::class, 'reverse'])->middleware('idempotent:required');

        // Finance: Budget Planning (Phase 27)
        Route::get('/finance/budgets', [BudgetPlanController::class, 'index']);
        Route::post('/finance/budgets', [BudgetPlanController::class, 'store'])->middleware('idempotent');
        Route::get('/finance/budgets/summary', [BudgetPlanController::class, 'summary']);
        Route::get('/finance/budgets/{budgetPlan}', [BudgetPlanController::class, 'show']);
        Route::put('/finance/budgets/{budgetPlan}', [BudgetPlanController::class, 'update'])->middleware('idempotent');
        Route::post('/finance/budgets/{budgetPlan}/publish', [BudgetPlanController::class, 'publish'])->middleware('idempotent:required');
        Route::post('/finance/budgets/{budgetPlan}/amend', [BudgetPlanController::class, 'amend'])->middleware('idempotent');

        // Finance: Expenditure Requests & Approval (Phase 27)
        Route::get('/finance/expenditures', [ExpenditureRequestController::class, 'index']);
        Route::post('/finance/expenditures', [ExpenditureRequestController::class, 'store'])->middleware('idempotent:required');
        Route::get('/finance/expenditures/{expenditureRequest}', [ExpenditureRequestController::class, 'show']);
        Route::post('/finance/expenditures/{expenditureRequest}/submit', [ExpenditureRequestController::class, 'submit'])->middleware('idempotent');
        Route::post('/finance/expenditures/{expenditureRequest}/approve', [ExpenditureRequestController::class, 'approve'])->middleware('idempotent:required');
        Route::post('/finance/expenditures/{expenditureRequest}/reject', [ExpenditureRequestController::class, 'reject'])->middleware('idempotent:required');
        Route::post('/finance/expenditures/{expenditureRequest}/cancel', [ExpenditureRequestController::class, 'cancel'])->middleware('idempotent');
        Route::post('/finance/expenditures/{expenditureRequest}/post', [ExpenditureRequestController::class, 'post'])->middleware('idempotent:required');
    });
});
