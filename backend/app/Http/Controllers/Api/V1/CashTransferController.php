<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finance\Actions\RecordCashTransferAction;
use App\Domain\Finance\Actions\ReverseCashTransferAction;
use App\Domain\Finance\DTOs\RecordCashTransferInput;
use App\Domain\Finance\DTOs\ReverseCashTransferInput;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\CashTransfer;
use App\Domain\Finance\Queries\GetCashTransferQuery;
use App\Domain\Finance\Queries\ListCashTransfersQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListCashTransfersRequest;
use App\Http\Requests\Finance\RecordCashTransferRequest;
use App\Http\Requests\Finance\ReverseCashTransferRequest;
use App\Http\Resources\CashTransferResource;
use App\Support\Api\ApiResponse;
use App\Support\Api\Contracts\QueryContract;
use App\Support\Api\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;

class CashTransferController extends Controller
{
    public function __construct(
        private readonly ListCashTransfersQuery $listQuery,
        private readonly GetCashTransferQuery $getQuery,
    ) {}

    public function index(ListCashTransfersRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('viewAny', CashTransfer::class);

        $sort = QueryContract::parseSort($request->query('sort'), QueryContract::FINANCE_TRANSFER);
        $perPage = QueryContract::resolvePerPage($request->query('per_page'));

        $paginator = $this->listQuery->execute(
            $user,
            $request->validated(),
            $sort,
            $perPage
        );

        return ApiResponse::paginated($paginator, fn (CashTransfer $trx) => new CashTransferResource($trx));
    }

    public function show(CashTransfer $cashTransfer): JsonResponse
    {
        $this->authorize('view', $cashTransfer);

        $transfer = $this->getQuery->execute($cashTransfer);

        return ApiResponse::success(new CashTransferResource($transfer));
    }

    public function store(
        RecordCashTransferRequest $request,
        RecordCashTransferAction $action
    ): JsonResponse {
        $validated = $request->validated();

        /** @var CashAccount|null $sourceAccount */
        $sourceAccount = CashAccount::find($validated['source_cash_account_id']);
        if ($sourceAccount === null) {
            throw new ApiException("Akun kas asal '{$validated['source_cash_account_id']}' tidak ditemukan.", 422, 'source_account_not_found');
        }

        /** @var CashAccount|null $destAccount */
        $destAccount = CashAccount::find($validated['destination_cash_account_id']);
        if ($destAccount === null) {
            throw new ApiException("Akun kas tujuan '{$validated['destination_cash_account_id']}' tidak ditemukan.", 422, 'destination_account_not_found');
        }

        $this->authorize('create', [CashTransfer::class, [
            'source' => $sourceAccount,
            'destination' => $destAccount,
        ]]);

        $input = new RecordCashTransferInput(
            sourceCashAccountId: $validated['source_cash_account_id'],
            destinationCashAccountId: $validated['destination_cash_account_id'],
            amount: (int) $validated['amount'],
            transferDate: $validated['transfer_date'],
            description: $validated['description'],
            referenceNumber: $validated['reference_number'] ?? null,
            evidenceFileId: $validated['evidence_file_id'] ?? null,
        );

        $transfer = $action->execute($input, $request->user());
        $loaded = $this->getQuery->execute($transfer);

        return ApiResponse::success(new CashTransferResource($loaded), status: 201);
    }

    public function reverse(
        CashTransfer $cashTransfer,
        ReverseCashTransferRequest $request,
        ReverseCashTransferAction $action
    ): JsonResponse {
        $this->authorize('reverse', $cashTransfer);

        $validated = $request->validated();
        $input = new ReverseCashTransferInput(
            transferId: $cashTransfer->id,
            reversalReason: $validated['reversal_reason'],
            transferDate: $validated['transfer_date'] ?? null,
        );

        $reversal = $action->execute($input, $request->user());
        $loaded = $this->getQuery->execute($reversal);

        return ApiResponse::success(new CashTransferResource($loaded), status: 201);
    }
}
