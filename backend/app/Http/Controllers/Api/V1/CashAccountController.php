<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\Finance\Actions\CloseCashAccountAction;
use App\Domain\Finance\Actions\CreateCashAccountAction;
use App\Domain\Finance\Actions\UpdateCashAccountDetailsAction;
use App\Domain\Finance\DTOs\CloseCashAccountInput;
use App\Domain\Finance\DTOs\CreateCashAccountInput;
use App\Domain\Finance\DTOs\UpdateCashAccountInput;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Queries\GetCashAccountQuery;
use App\Domain\Finance\Queries\ListCashAccountsQuery;
use App\Domain\Finance\Services\AccountBalanceService;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CloseCashAccountRequest;
use App\Http\Requests\Finance\CreateCashAccountRequest;
use App\Http\Requests\Finance\ListCashAccountsRequest;
use App\Http\Requests\Finance\UpdateCashAccountRequest;
use App\Http\Resources\AccountSummaryResource;
use App\Http\Resources\CashAccountResource;
use App\Support\Api\ApiResponse;
use App\Support\Api\Contracts\QueryContract;
use App\Support\Api\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;

class CashAccountController extends Controller
{
    public function __construct(
        private readonly ListCashAccountsQuery $listQuery,
        private readonly GetCashAccountQuery $getQuery,
        private readonly AccountBalanceService $balanceService,
    ) {}

    public function index(ListCashAccountsRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('viewAny', CashAccount::class);

        $sort = QueryContract::parseSort($request->query('sort'), QueryContract::FINANCE_ACCOUNT);
        $perPage = QueryContract::resolvePerPage($request->query('per_page'));

        $paginator = $this->listQuery->execute(
            $user,
            $request->validated(),
            $sort,
            $perPage
        );

        // Batch compute current balances without N+1
        $balances = $this->balanceService->batchCurrentBalances(collect($paginator->items()));

        return ApiResponse::paginated($paginator, function (CashAccount $account) use ($balances): CashAccountResource {
            $balance = $balances[$account->id] ?? (int) $account->opening_balance;

            return CashAccountResource::withBalance($account, $balance);
        });
    }

    public function show(CashAccount $cashAccount): JsonResponse
    {
        $this->authorize('view', $cashAccount);

        $account = $this->getQuery->execute($cashAccount);
        $summary = $this->balanceService->calculate($account);

        return ApiResponse::success(CashAccountResource::withBalance($account, $summary['current_balance']));
    }

    public function summary(CashAccount $cashAccount): JsonResponse
    {
        $this->authorize('view', $cashAccount);

        $account = $this->getQuery->execute($cashAccount);
        $summary = $this->balanceService->calculate($account);

        return ApiResponse::success(new AccountSummaryResource($account, $summary));
    }

    public function store(CreateCashAccountRequest $request, CreateCashAccountAction $action): JsonResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();

        if (! empty($validated['rt_id'])) {
            /** @var Rt|null $rt */
            $rt = Rt::with('rw')->find($validated['rt_id']);
            if ($rt === null) {
                throw new ApiException("RT '{$validated['rt_id']}' not found.", 422, 'rt_not_found');
            }
            $rwId = $rt->rw_id;
            $targetScope = ScopeRef::rt($rt->rw->organization_id, $rt->rw_id, $rt->id);
        } else {
            $scopes = app(AccessResolver::class)->scopesGranting($user, PermissionKey::FinanceAccountManage);
            $rwScope = null;
            foreach ($scopes as $s) {
                if ($s->rwId !== null) {
                    $rwScope = $s;
                    break;
                }
            }
            if ($rwScope === null) {
                throw new ApiException('Cannot resolve RW scope for cash account creation.', 403, 'rw_scope_not_found');
            }
            $rwId = $rwScope->rwId;
            $targetScope = $rwScope;
        }

        $this->authorize('create', [CashAccount::class, $targetScope]);

        $input = new CreateCashAccountInput(
            rwId: $rwId,
            name: $validated['name'],
            rtId: $validated['rt_id'] ?? null,
            accountNumber: $validated['account_number'] ?? null,
            description: $validated['description'] ?? null,
            openingBalance: (int) ($validated['opening_balance'] ?? 0),
        );

        $account = $action->execute($input, $request->user());

        return ApiResponse::success(CashAccountResource::withBalance($account, (int) $account->opening_balance), status: 201);
    }

    public function update(
        CashAccount $cashAccount,
        UpdateCashAccountRequest $request,
        UpdateCashAccountDetailsAction $action
    ): JsonResponse {
        $this->authorize('update', $cashAccount);

        $validated = $request->validated();
        $input = new UpdateCashAccountInput(
            name: $validated['name'] ?? null,
            accountNumber: $validated['account_number'] ?? null,
            description: $validated['description'] ?? null,
        );

        $updated = $action->execute($cashAccount, $input, $request->user());
        $summary = $this->balanceService->calculate($updated);

        return ApiResponse::success(CashAccountResource::withBalance($updated, $summary['current_balance']));
    }

    public function close(
        CashAccount $cashAccount,
        CloseCashAccountRequest $request,
        CloseCashAccountAction $action
    ): JsonResponse {
        $this->authorize('close', $cashAccount);

        $validated = $request->validated();
        $input = new CloseCashAccountInput(
            reason: $validated['reason'],
        );

        $closed = $action->execute($cashAccount, $input, $request->user());
        $summary = $this->balanceService->calculate($closed);

        return ApiResponse::success(CashAccountResource::withBalance($closed, $summary['current_balance']));
    }
}
