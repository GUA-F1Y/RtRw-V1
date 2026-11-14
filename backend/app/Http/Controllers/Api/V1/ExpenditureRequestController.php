<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finance\Actions\ApproveExpenditureRequestAction;
use App\Domain\Finance\Actions\CancelExpenditureRequestAction;
use App\Domain\Finance\Actions\CreateExpenditureRequestAction;
use App\Domain\Finance\Actions\PostExpenditureRequestAction;
use App\Domain\Finance\Actions\RejectExpenditureRequestAction;
use App\Domain\Finance\Actions\SubmitExpenditureRequestAction;
use App\Domain\Finance\DTOs\ApproveExpenditureInput;
use App\Domain\Finance\DTOs\CancelExpenditureInput;
use App\Domain\Finance\DTOs\CreateExpenditureRequestInput;
use App\Domain\Finance\DTOs\PostExpenditureInput;
use App\Domain\Finance\DTOs\RejectExpenditureInput;
use App\Domain\Finance\Models\BudgetLine;
use App\Domain\Finance\Models\ExpenditureRequest;
use App\Domain\Finance\Queries\GetExpenditureRequestQuery;
use App\Domain\Finance\Queries\ListExpenditureRequestsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ApproveExpenditureRequest;
use App\Http\Requests\Finance\CancelExpenditureRequest;
use App\Http\Requests\Finance\CreateExpenditureRequest;
use App\Http\Requests\Finance\ListExpenditureRequestsRequest;
use App\Http\Requests\Finance\PostExpenditureRequest;
use App\Http\Requests\Finance\RejectExpenditureRequest;
use App\Http\Resources\ExpenditureRequestResource;
use App\Support\Api\ApiResponse;
use App\Support\Api\Contracts\QueryContract;
use App\Support\Api\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenditureRequestController extends Controller
{
    public function __construct(
        private readonly ListExpenditureRequestsQuery $listQuery,
        private readonly GetExpenditureRequestQuery $getQuery,
    ) {}

    public function index(ListExpenditureRequestsRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('viewAny', ExpenditureRequest::class);

        $sort = QueryContract::parseSort($request->query('sort'), QueryContract::FINANCE_EXPENDITURE_REQUEST);
        $perPage = QueryContract::resolvePerPage($request->query('per_page'));

        $paginator = $this->listQuery->execute(
            $user,
            $request->validated(),
            $sort,
            $perPage
        );

        return ApiResponse::paginated($paginator, fn (ExpenditureRequest $req) => new ExpenditureRequestResource($req));
    }

    public function show(ExpenditureRequest $expenditureRequest): JsonResponse
    {
        $this->authorize('view', $expenditureRequest);

        $loaded = $this->getQuery->execute($expenditureRequest);

        return ApiResponse::success(new ExpenditureRequestResource($loaded));
    }

    public function store(
        CreateExpenditureRequest $request,
        CreateExpenditureRequestAction $action
    ): JsonResponse {
        $validated = $request->validated();

        /** @var BudgetLine|null $budgetLine */
        $budgetLine = BudgetLine::with('budgetPlan')->find($validated['budget_line_id']);
        if ($budgetLine === null) {
            throw new ApiException("Pos anggaran '{$validated['budget_line_id']}' tidak ditemukan.", 422, 'budget_line_not_found');
        }

        $plan = $budgetLine->budgetPlan;
        $this->authorize('create', [ExpenditureRequest::class, $plan->scopeRef()]);

        $input = new CreateExpenditureRequestInput(
            budgetLineId: $validated['budget_line_id'],
            cashAccountId: $validated['cash_account_id'],
            proposedAmount: (int) $validated['proposed_amount'],
            plannedDate: $validated['planned_date'],
            title: $validated['title'],
            description: $validated['description'] ?? null,
            referenceNumber: $validated['reference_number'] ?? null,
            evidenceFileId: $validated['evidence_file_id'] ?? null,
            submit: (bool) ($validated['submit'] ?? true),
        );

        $created = $action->execute($input, $request->user());
        $loaded = $this->getQuery->execute($created);

        return ApiResponse::success(new ExpenditureRequestResource($loaded), status: 201);
    }

    public function submit(
        ExpenditureRequest $expenditureRequest,
        Request $request,
        SubmitExpenditureRequestAction $action
    ): JsonResponse {
        $this->authorize('submit', $expenditureRequest);

        $submitted = $action->execute($expenditureRequest, $request->user());
        $loaded = $this->getQuery->execute($submitted);

        return ApiResponse::success(new ExpenditureRequestResource($loaded));
    }

    public function approve(
        ExpenditureRequest $expenditureRequest,
        ApproveExpenditureRequest $request,
        ApproveExpenditureRequestAction $action
    ): JsonResponse {
        $this->authorize('approve', $expenditureRequest);

        $validated = $request->validated();
        $input = new ApproveExpenditureInput(
            requestId: $expenditureRequest->id,
            approvedAmount: isset($validated['approved_amount']) ? (int) $validated['approved_amount'] : null,
            decisionReason: $validated['decision_reason'] ?? null,
        );

        $approved = $action->execute($expenditureRequest, $input, $request->user());
        $loaded = $this->getQuery->execute($approved);

        return ApiResponse::success(new ExpenditureRequestResource($loaded));
    }

    public function reject(
        ExpenditureRequest $expenditureRequest,
        RejectExpenditureRequest $request,
        RejectExpenditureRequestAction $action
    ): JsonResponse {
        $this->authorize('reject', $expenditureRequest);

        $validated = $request->validated();
        $input = new RejectExpenditureInput(
            requestId: $expenditureRequest->id,
            decisionReason: $validated['decision_reason'] ?? null,
        );

        $rejected = $action->execute($expenditureRequest, $input, $request->user());
        $loaded = $this->getQuery->execute($rejected);

        return ApiResponse::success(new ExpenditureRequestResource($loaded));
    }

    public function cancel(
        ExpenditureRequest $expenditureRequest,
        CancelExpenditureRequest $request,
        CancelExpenditureRequestAction $action
    ): JsonResponse {
        $this->authorize('cancel', $expenditureRequest);

        $validated = $request->validated();
        $input = new CancelExpenditureInput(
            requestId: $expenditureRequest->id,
            cancellationReason: $validated['cancellation_reason'] ?? null,
        );

        $cancelled = $action->execute($expenditureRequest, $input, $request->user());
        $loaded = $this->getQuery->execute($cancelled);

        return ApiResponse::success(new ExpenditureRequestResource($loaded));
    }

    public function post(
        ExpenditureRequest $expenditureRequest,
        PostExpenditureRequest $request,
        PostExpenditureRequestAction $action
    ): JsonResponse {
        $this->authorize('post', $expenditureRequest);

        $validated = $request->validated();
        $input = new PostExpenditureInput(
            requestId: $expenditureRequest->id,
            actualAmount: isset($validated['actual_amount']) ? (int) $validated['actual_amount'] : null,
            transactionDate: $validated['transaction_date'] ?? null,
            cashAccountId: $validated['cash_account_id'] ?? null,
            referenceNumber: $validated['reference_number'] ?? null,
            evidenceFileId: $validated['evidence_file_id'] ?? null,
        );

        $posted = $action->execute($expenditureRequest, $input, $request->user());
        $loaded = $this->getQuery->execute($posted);

        return ApiResponse::success(new ExpenditureRequestResource($loaded));
    }
}
