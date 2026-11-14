<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finance\Actions\AmendBudgetPlanAction;
use App\Domain\Finance\Actions\CreateBudgetPlanAction;
use App\Domain\Finance\Actions\PublishBudgetPlanAction;
use App\Domain\Finance\Actions\UpdateBudgetPlanAction;
use App\Domain\Finance\DTOs\CreateBudgetPlanInput;
use App\Domain\Finance\DTOs\UpdateBudgetPlanInput;
use App\Domain\Finance\Models\BudgetPlan;
use App\Domain\Finance\Queries\BudgetUtilizationSummaryQuery;
use App\Domain\Finance\Queries\GetBudgetPlanQuery;
use App\Domain\Finance\Queries\ListBudgetPlansQuery;
use App\Domain\Finance\Services\BudgetCalculationService;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\AmendBudgetPlanRequest;
use App\Http\Requests\Finance\BudgetUtilizationRequest;
use App\Http\Requests\Finance\CreateBudgetPlanRequest;
use App\Http\Requests\Finance\ListBudgetPlansRequest;
use App\Http\Requests\Finance\PublishBudgetPlanRequest;
use App\Http\Requests\Finance\UpdateBudgetPlanRequest;
use App\Http\Resources\BudgetPlanResource;
use App\Support\Api\ApiResponse;
use App\Support\Api\Contracts\QueryContract;
use App\Support\Api\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;

class BudgetPlanController extends Controller
{
    public function __construct(
        private readonly ListBudgetPlansQuery $listQuery,
        private readonly GetBudgetPlanQuery $getQuery,
        private readonly BudgetCalculationService $calculationService,
        private readonly BudgetUtilizationSummaryQuery $utilizationQuery,
    ) {}

    public function index(ListBudgetPlansRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('viewAny', BudgetPlan::class);

        $sort = QueryContract::parseSort($request->query('sort'), QueryContract::FINANCE_BUDGET_PLAN);
        $perPage = QueryContract::resolvePerPage($request->query('per_page'));

        $paginator = $this->listQuery->execute(
            $user,
            $request->validated(),
            $sort,
            $perPage
        );

        return ApiResponse::paginated($paginator, fn (BudgetPlan $plan) => new BudgetPlanResource($plan));
    }

    public function show(BudgetPlan $budgetPlan): JsonResponse
    {
        $this->authorize('view', $budgetPlan);

        $plan = $this->getQuery->execute($budgetPlan);
        $summary = $this->calculationService->calculatePlanMetrics($plan);
        $lineMetrics = $this->calculationService->batchLineMetrics($plan->lines);

        // Attach line-level metrics
        foreach ($plan->lines as $line) {
            if (isset($lineMetrics[$line->id])) {
                $line->additional = ['metrics' => $lineMetrics[$line->id]];
            }
        }

        $resource = new BudgetPlanResource($plan);
        $resource->additional = ['summary' => $summary];

        return ApiResponse::success($resource);
    }

    public function store(
        CreateBudgetPlanRequest $request,
        CreateBudgetPlanAction $action
    ): JsonResponse {
        $validated = $request->validated();
        $user = $request->user();

        // Resolve user's active membership or target scope
        // If rt_id provided, resolve RT and its parent RW
        /** @var Rt|null $rt */
        $rt = null;
        if (! empty($validated['rt_id'])) {
            $rt = Rt::with('rw.organization')->find($validated['rt_id']);
            if ($rt === null) {
                throw new ApiException("RT '{$validated['rt_id']}' tidak ditemukan.", 422, 'rt_not_found');
            }
            $rw = $rt->rw;
            $organization = $rw->organization;
        } else {
            // Find active membership of user to resolve RW
            $membership = $user->memberships()->active()->whereNotNull('rw_id')->first();
            if ($membership === null) {
                // Check if org admin
                $orgMembership = $user->memberships()->active()->whereNotNull('organization_id')->first();
                if ($orgMembership && $orgMembership->organization) {
                    $organization = $orgMembership->organization;
                    $rw = $organization->rws()->first();
                    if ($rw === null) {
                        throw new ApiException('Tidak ada RW yang terdaftar pada organisasi ini.', 422, 'no_rw_found');
                    }
                } else {
                    throw new ApiException('Pengguna tidak memiliki cakupan RW yang sah.', 403, 'no_rw_scope');
                }
            } else {
                $rw = $membership->rw;
                $organization = $membership->organization;
            }
        }

        $this->authorize('create', [BudgetPlan::class, $rt ? $rt->scopeRef() : $rw->scopeRef()]);

        $input = new CreateBudgetPlanInput(
            year: (int) $validated['year'],
            month: (int) $validated['month'],
            rtId: $rt?->id,
            notes: $validated['notes'] ?? null,
            lines: $validated['lines'],
        );

        $plan = $action->execute($input, $user, $organization, $rw, $rt);
        $loaded = $this->getQuery->execute($plan);

        return ApiResponse::success(new BudgetPlanResource($loaded), status: 201);
    }

    public function update(
        BudgetPlan $budgetPlan,
        UpdateBudgetPlanRequest $request,
        UpdateBudgetPlanAction $action
    ): JsonResponse {
        $this->authorize('update', $budgetPlan);

        $validated = $request->validated();
        $input = new UpdateBudgetPlanInput(
            notes: $validated['notes'] ?? null,
            lines: $validated['lines'] ?? [],
        );

        $updated = $action->execute($budgetPlan, $input, $request->user());
        $loaded = $this->getQuery->execute($updated);

        return ApiResponse::success(new BudgetPlanResource($loaded));
    }

    public function publish(
        BudgetPlan $budgetPlan,
        PublishBudgetPlanRequest $request,
        PublishBudgetPlanAction $action
    ): JsonResponse {
        $this->authorize('publish', $budgetPlan);

        $published = $action->execute($budgetPlan, $request->user());
        $loaded = $this->getQuery->execute($published);

        return ApiResponse::success(new BudgetPlanResource($loaded));
    }

    public function amend(
        BudgetPlan $budgetPlan,
        AmendBudgetPlanRequest $request,
        AmendBudgetPlanAction $action
    ): JsonResponse {
        $this->authorize('amend', $budgetPlan);

        $validated = $request->validated();
        $amended = $action->execute($budgetPlan, $request->user(), $validated['notes'] ?? null);
        $loaded = $this->getQuery->execute($amended);

        return ApiResponse::success(new BudgetPlanResource($loaded), status: 201);
    }

    public function summary(BudgetUtilizationRequest $request): JsonResponse
    {
        $this->authorize('viewAny', BudgetPlan::class);

        $result = $this->utilizationQuery->execute($request->user(), $request->validated());

        return ApiResponse::success($result);
    }
}
