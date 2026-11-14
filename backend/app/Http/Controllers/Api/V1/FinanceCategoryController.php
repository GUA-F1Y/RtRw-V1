<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Authorization\ScopeRef;
use App\Domain\Finance\Actions\CreateFinanceCategoryAction;
use App\Domain\Finance\Actions\UpdateFinanceCategoryAction;
use App\Domain\Finance\DTOs\CreateFinanceCategoryInput;
use App\Domain\Finance\DTOs\UpdateFinanceCategoryInput;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\FinanceCategory;
use App\Domain\Finance\Queries\ListFinanceCategoriesQuery;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CreateFinanceCategoryRequest;
use App\Http\Requests\Finance\ListFinanceCategoriesRequest;
use App\Http\Requests\Finance\UpdateFinanceCategoryRequest;
use App\Http\Resources\FinanceCategoryResource;
use App\Support\Api\ApiResponse;
use App\Support\Api\Contracts\QueryContract;
use App\Support\Api\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;

class FinanceCategoryController extends Controller
{
    public function __construct(
        private readonly ListFinanceCategoriesQuery $listQuery,
    ) {}

    public function index(ListFinanceCategoriesRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('viewAny', FinanceCategory::class);

        $sort = QueryContract::parseSort($request->query('sort'), QueryContract::FINANCE_CATEGORY);
        $perPage = QueryContract::resolvePerPage($request->query('per_page'));

        $paginator = $this->listQuery->execute(
            $user,
            $request->validated(),
            $sort,
            $perPage
        );

        return ApiResponse::paginated($paginator, fn (FinanceCategory $cat) => new FinanceCategoryResource($cat));
    }

    public function show(FinanceCategory $financeCategory): JsonResponse
    {
        $this->authorize('view', $financeCategory);

        return ApiResponse::success(new FinanceCategoryResource($financeCategory));
    }

    public function store(CreateFinanceCategoryRequest $request, CreateFinanceCategoryAction $action): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $targetScope = null;
        $rwId = null;
        $rtId = null;

        if (! empty($validated['rt_id'])) {
            /** @var Rt|null $rt */
            $rt = Rt::with('rw')->find($validated['rt_id']);
            if ($rt === null) {
                throw new ApiException("RT '{$validated['rt_id']}' not found.", 422, 'rt_not_found');
            }
            $rtId = $rt->id;
            $rwId = $rt->rw_id;
            $targetScope = ScopeRef::rt($rt->rw->organization_id, $rt->rw_id, $rt->id);
        } else {
            $membership = $user->memberships()->whereNull('revoked_at')->first();
            if ($membership !== null) {
                $targetScope = $membership->scopeRef();
                $rwId = $membership->rw_id;
            }
        }

        $this->authorize('create', [FinanceCategory::class, $targetScope]);

        $input = new CreateFinanceCategoryInput(
            name: $validated['name'],
            type: TransactionType::from($validated['type']),
            rwId: $rwId,
            rtId: $rtId,
            code: $validated['code'] ?? null,
            description: $validated['description'] ?? null,
        );

        $category = $action->execute($input, $user);

        return ApiResponse::success(new FinanceCategoryResource($category), status: 201);
    }

    public function update(
        FinanceCategory $financeCategory,
        UpdateFinanceCategoryRequest $request,
        UpdateFinanceCategoryAction $action
    ): JsonResponse {
        $this->authorize('update', $financeCategory);

        $validated = $request->validated();
        $input = new UpdateFinanceCategoryInput(
            name: $validated['name'] ?? null,
            code: $validated['code'] ?? null,
            description: $validated['description'] ?? null,
            isActive: isset($validated['is_active']) ? (bool) $validated['is_active'] : null,
        );

        $updated = $action->execute($financeCategory, $input, $request->user());

        return ApiResponse::success(new FinanceCategoryResource($updated));
    }
}
