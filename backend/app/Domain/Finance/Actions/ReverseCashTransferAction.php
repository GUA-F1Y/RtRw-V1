<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Finance\DTOs\ReverseCashTransferInput;
use App\Domain\Finance\Exceptions\CashAccountClosedException;
use App\Domain\Finance\Exceptions\ClosedPeriodException;
use App\Domain\Finance\Exceptions\InsufficientFundsException;
use App\Domain\Finance\Exceptions\InvalidTransferException;
use App\Domain\Finance\Exceptions\TransferAlreadyReversedException;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\CashTransfer;
use App\Domain\Finance\Models\FinancialPeriodClosure;
use App\Domain\Finance\Services\AccountBalanceService;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ReverseCashTransferAction
{
    public function __construct(
        private readonly AccountBalanceService $balanceService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(ReverseCashTransferInput $input, User $actor): CashTransfer
    {
        if (trim($input->reversalReason) === '') {
            throw new InvalidTransferException('A meaningful reversal reason is required to reverse a cash transfer.');
        }

        return DB::transaction(function () use ($input, $actor): CashTransfer {
            /** @var CashTransfer|null $original */
            $original = CashTransfer::where('id', $input->transferId)->first();

            if ($original === null) {
                throw new InvalidTransferException("Cash transfer '{$input->transferId}' not found.");
            }

            if ($original->is_reversal) {
                throw new InvalidTransferException('Cannot reverse a cash transfer that is itself a reversal compensating entry.');
            }

            if ($original->reversal()->exists()) {
                throw new TransferAlreadyReversedException('This cash transfer has already been reversed.');
            }

            $reversalDate = $input->transferDate ?? Carbon::now()->toDateString();

            // Deterministic row locking in ascending UUID order
            $accountIds = [$original->source_cash_account_id, $original->destination_cash_account_id];
            sort($accountIds);

            $lockedAccounts = CashAccount::whereIn('id', $accountIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var CashAccount|null $origSourceAccount */
            $origSourceAccount = $lockedAccounts->get($original->source_cash_account_id);
            /** @var CashAccount|null $origDestAccount */
            $origDestAccount = $lockedAccounts->get($original->destination_cash_account_id);

            if ($origSourceAccount === null || $origDestAccount === null) {
                throw new InvalidTransferException('One or both accounts involved in the transfer no longer exist.');
            }

            if ($origSourceAccount->isClosed() || $origDestAccount->isClosed()) {
                throw new CashAccountClosedException('Cannot reverse transfer: one or both involved cash accounts are closed.');
            }

            // Closed-period checks for both accounts on the reversal date
            $sourceClosed = FinancialPeriodClosure::query()
                ->where('cash_account_id', $origSourceAccount->id)
                ->where('period_start', '<=', $reversalDate)
                ->where('period_end', '>=', $reversalDate)
                ->exists();

            if ($sourceClosed) {
                throw new ClosedPeriodException(
                    "Cannot reverse transfer: account '{$origSourceAccount->name}' has a closed accounting period on {$reversalDate}. Reversal must be dated in an open period."
                );
            }

            $destClosed = FinancialPeriodClosure::query()
                ->where('cash_account_id', $origDestAccount->id)
                ->where('period_start', '<=', $reversalDate)
                ->where('period_end', '>=', $reversalDate)
                ->exists();

            if ($destClosed) {
                throw new ClosedPeriodException(
                    "Cannot reverse transfer: account '{$origDestAccount->name}' has a closed accounting period on {$reversalDate}. Reversal must be dated in an open period."
                );
            }

            // Available funds check on compensating source (the original destination account must have funds to return)
            $destBalance = $this->balanceService->calculate($origDestAccount)['current_balance'];
            if ($destBalance < $original->amount) {
                throw new InsufficientFundsException(
                    "Tidak dapat membatalkan transfer: Saldo akun penerima '{$origDestAccount->name}' tidak mencukupi untuk mengembalikan dana. Saldo tersedia: Rp {$destBalance}, dana yang harus dikembalikan: Rp {$original->amount}."
                );
            }

            // Compensating reversal transfer reverses direction:
            // source becomes original destination, destination becomes original source
            /** @var CashTransfer $reversalTransfer */
            $reversalTransfer = CashTransfer::create([
                'organization_id' => $original->organization_id,
                'rw_id' => $original->rw_id,
                'rt_id' => $original->rt_id,
                'source_cash_account_id' => $original->destination_cash_account_id,
                'destination_cash_account_id' => $original->source_cash_account_id,
                'amount' => $original->amount,
                'transfer_date' => $reversalDate,
                'description' => "Pembalikan Transfer: {$input->reversalReason}",
                'reference_number' => $original->reference_number ? "REV-{$original->reference_number}" : null,
                'evidence_file_id' => null,
                'recorded_by' => $actor->id,
                'is_reversal' => true,
                'original_transfer_id' => $original->id,
                'reversal_reason' => $input->reversalReason,
            ]);

            // Audit log
            $this->auditLogger->record(
                actor: $actor,
                event: 'finance.transfer.reversed',
                description: "Cash transfer {$original->id} reversed by compensating transfer {$reversalTransfer->id}.",
                subject: $reversalTransfer,
                scope: $reversalTransfer->scopeRef(),
                properties: [
                    'transfer_id' => $reversalTransfer->id,
                    'original_transfer_id' => $original->id,
                    'reversal_reason' => $input->reversalReason,
                    'amount' => $reversalTransfer->amount,
                    'transfer_date' => $reversalTransfer->transfer_date->toDateString(),
                ]
            );

            return $reversalTransfer->load(['sourceAccount', 'destinationAccount', 'recorder', 'originalTransfer']);
        });
    }
}
