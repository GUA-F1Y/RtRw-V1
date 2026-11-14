<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finance\Models\FinancialTransaction;
use App\Domain\Finance\Queries\FinanceAccountComparisonQuery;
use App\Domain\Finance\Queries\FinanceCashFlowTrendQuery;
use App\Domain\Finance\Queries\FinanceCategoryBreakdownQuery;
use App\Domain\Finance\Queries\FinanceReportSummaryQuery;
use App\Domain\Finance\Queries\FinanceRtBreakdownQuery;
use App\Domain\Finance\Services\FinanceCsvExportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinanceReportRequest;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceReportController extends Controller
{
    public function __construct(
        private readonly FinanceReportSummaryQuery $summaryQuery,
        private readonly FinanceCashFlowTrendQuery $cashFlowQuery,
        private readonly FinanceCategoryBreakdownQuery $categoryBreakdownQuery,
        private readonly FinanceRtBreakdownQuery $rtBreakdownQuery,
        private readonly FinanceAccountComparisonQuery $accountComparisonQuery,
        private readonly FinanceCsvExportService $csvExportService,
    ) {}

    public function summary(FinanceReportRequest $request): JsonResponse
    {
        $this->authorize('viewAny', FinancialTransaction::class);
        $user = $request->user();

        $result = $this->summaryQuery->execute($user, $request->validated());

        return ApiResponse::success($result);
    }

    public function cashFlow(FinanceReportRequest $request): JsonResponse
    {
        $this->authorize('viewAny', FinancialTransaction::class);
        $user = $request->user();

        $result = $this->cashFlowQuery->execute($user, $request->validated());

        return ApiResponse::success($result);
    }

    public function categories(FinanceReportRequest $request): JsonResponse
    {
        $this->authorize('viewAny', FinancialTransaction::class);
        $user = $request->user();

        $result = $this->categoryBreakdownQuery->execute($user, $request->validated());

        return ApiResponse::success($result);
    }

    public function rtBreakdown(FinanceReportRequest $request): JsonResponse
    {
        $this->authorize('viewAny', FinancialTransaction::class);
        $user = $request->user();

        $result = $this->rtBreakdownQuery->execute($user, $request->validated());

        return ApiResponse::success($result);
    }

    public function accounts(FinanceReportRequest $request): JsonResponse
    {
        $this->authorize('viewAny', FinancialTransaction::class);
        $user = $request->user();

        $result = $this->accountComparisonQuery->execute($user, $request->validated());

        return ApiResponse::success($result);
    }

    public function export(FinanceReportRequest $request): StreamedResponse
    {
        $this->authorize('viewAny', FinancialTransaction::class);
        $user = $request->user();

        return $this->csvExportService->export($user, $request->validated());
    }
}
