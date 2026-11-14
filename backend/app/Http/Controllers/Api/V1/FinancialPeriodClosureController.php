<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finance\Actions\CloseFinancialPeriodAction;
use App\Domain\Finance\DTOs\CloseFinancialPeriodInput;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\FinancialPeriodClosure;
use App\Domain\Finance\Queries\GetFinancialPeriodClosureQuery;
use App\Domain\Finance\Queries\ListFinancialPeriodClosuresQuery;
use App\Domain\Finance\Queries\PreviewPeriodClosureQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CloseFinancialPeriodRequest;
use App\Http\Requests\Finance\ListFinancialPeriodClosuresRequest;
use App\Http\Resources\FinancialPeriodClosureResource;
use App\Http\Resources\PeriodClosurePreviewResource;
use App\Support\Api\ApiResponse;
use App\Support\Api\Contracts\QueryContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialPeriodClosureController extends Controller
{
    public function __construct(
        private readonly ListFinancialPeriodClosuresQuery $listQuery,
        private readonly GetFinancialPeriodClosureQuery $getQuery,
        private readonly PreviewPeriodClosureQuery $previewQuery,
    ) {}

    public function index(ListFinancialPeriodClosuresRequest $request): JsonResponse
    {
        $this->authorize('viewAny', FinancialPeriodClosure::class);

        $sort = QueryContract::parseSort($request->query('sort'), QueryContract::FINANCE_PERIOD_CLOSURE);
        $perPage = QueryContract::resolvePerPage($request->query('per_page'));

        $paginator = $this->listQuery->execute(
            $request->user(),
            $request->validated(),
            $sort,
            $perPage
        );

        return ApiResponse::paginated(
            $paginator,
            fn (FinancialPeriodClosure $c) => new FinancialPeriodClosureResource($c)
        );
    }

    public function show(FinancialPeriodClosure $financialPeriodClosure): JsonResponse
    {
        $this->authorize('view', $financialPeriodClosure);

        $closure = $this->getQuery->execute($financialPeriodClosure);

        return ApiResponse::success(new FinancialPeriodClosureResource($closure));
    }

    public function preview(CashAccount $cashAccount, int $year, int $month, Request $request): JsonResponse
    {
        $this->authorize('preview', [FinancialPeriodClosure::class, $cashAccount]);

        $preview = $this->previewQuery->execute($cashAccount, $year, $month);

        return ApiResponse::success(new PeriodClosurePreviewResource($preview));
    }

    public function close(
        CashAccount $cashAccount,
        int $year,
        int $month,
        CloseFinancialPeriodRequest $request,
        CloseFinancialPeriodAction $action
    ): JsonResponse {
        $this->authorize('close', [FinancialPeriodClosure::class, $cashAccount]);

        $validated = $request->validated();

        $input = new CloseFinancialPeriodInput(
            cashAccountId: $cashAccount->id,
            year: $year,
            month: $month,
            countedCashBalance: isset($validated['counted_cash_balance']) ? (int) $validated['counted_cash_balance'] : null,
            reconciliationNotes: $validated['reconciliation_notes'] ?? null,
        );

        $closure = $action->execute($input, $request->user());
        $loaded = $this->getQuery->execute($closure);

        return ApiResponse::success(new FinancialPeriodClosureResource($loaded), status: 201);
    }
}
