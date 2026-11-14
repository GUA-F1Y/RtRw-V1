<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\File\Models\StoredFile;
use App\Domain\Finance\DTOs\RecordCashTransferInput;
use App\Domain\Finance\Exceptions\CashAccountClosedException;
use App\Domain\Finance\Exceptions\ClosedPeriodException;
use App\Domain\Finance\Exceptions\FinanceScopeMismatch;
use App\Domain\Finance\Exceptions\InsufficientFundsException;
use App\Domain\Finance\Exceptions\InvalidTransferException;
use App\Domain\Finance\Exceptions\SameAccountTransferException;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\CashTransfer;
use App\Domain\Finance\Models\FinancialPeriodClosure;
use App\Domain\Finance\Services\AccountBalanceService;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class RecordCashTransferAction
{
    public function __construct(
        private readonly AccountBalanceService $balanceService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(RecordCashTransferInput $input, User $actor): CashTransfer
    {
        // 1. Basic validation: source and destination must differ
        if ($input->sourceCashAccountId === $input->destinationCashAccountId) {
            throw new SameAccountTransferException('Source and destination cash accounts must be different accounts.');
        }

        if ($input->amount <= 0) {
            throw new InvalidTransferException('Transfer amount must be a positive integer Rupiah value.');
        }

        return DB::transaction(function () use ($input, $actor): CashTransfer {
            // 2. Deterministic row locking in ascending UUID order to prevent deadlocks
            $accountIds = [$input->sourceCashAccountId, $input->destinationCashAccountId];
            sort($accountIds);

            $lockedAccounts = CashAccount::whereIn('id', $accountIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var CashAccount|null $sourceAccount */
            $sourceAccount = $lockedAccounts->get($input->sourceCashAccountId);
            /** @var CashAccount|null $destinationAccount */
            $destinationAccount = $lockedAccounts->get($input->destinationCashAccountId);

            if ($sourceAccount === null) {
                throw new InvalidTransferException("Source cash account '{$input->sourceCashAccountId}' not found.");
            }

            if ($destinationAccount === null) {
                throw new InvalidTransferException("Destination cash account '{$input->destinationCashAccountId}' not found.");
            }

            // 3. Organization boundary check
            if ($sourceAccount->organization_id !== $destinationAccount->organization_id) {
                throw new FinanceScopeMismatch('Source and destination accounts must belong to the same organization.');
            }

            // 4. Account lifecycle check
            if ($sourceAccount->isClosed()) {
                throw new CashAccountClosedException("Cannot transfer from closed cash account '{$sourceAccount->name}'.");
            }

            if ($destinationAccount->isClosed()) {
                throw new CashAccountClosedException("Cannot transfer to closed cash account '{$destinationAccount->name}'.");
            }

            // 5. Closed-period checks for both accounts
            $sourcePeriodClosed = FinancialPeriodClosure::query()
                ->where('cash_account_id', $sourceAccount->id)
                ->where('period_start', '<=', $input->transferDate)
                ->where('period_end', '>=', $input->transferDate)
                ->exists();

            if ($sourcePeriodClosed) {
                throw new ClosedPeriodException(
                    "Cannot transfer funds: source account '{$sourceAccount->name}' has a closed accounting period on {$input->transferDate}."
                );
            }

            $destPeriodClosed = FinancialPeriodClosure::query()
                ->where('cash_account_id', $destinationAccount->id)
                ->where('period_start', '<=', $input->transferDate)
                ->where('period_end', '>=', $input->transferDate)
                ->exists();

            if ($destPeriodClosed) {
                throw new ClosedPeriodException(
                    "Cannot transfer funds: destination account '{$destinationAccount->name}' has a closed accounting period on {$input->transferDate}."
                );
            }

            // 6. Available funds check under row lock
            $sourceBalance = $this->balanceService->calculate($sourceAccount)['current_balance'];
            if ($sourceBalance < $input->amount) {
                throw new InsufficientFundsException(
                    "Saldo kas akun '{$sourceAccount->name}' tidak mencukupi untuk transfer. Saldo saat ini: Rp {$sourceBalance}, nominal transfer: Rp {$input->amount}."
                );
            }

            // 7. Optional evidence file validation
            if ($input->evidenceFileId !== null) {
                $evidenceFile = StoredFile::find($input->evidenceFileId);
                if ($evidenceFile === null) {
                    throw new InvalidTransferException("Evidence file '{$input->evidenceFileId}' not found.");
                }
                if ($evidenceFile->organization_id !== $sourceAccount->organization_id) {
                    throw new FinanceScopeMismatch('Evidence file does not belong to the same organization as the cash accounts.');
                }
            }

            // 8. Scope resolution
            $rtId = ($sourceAccount->rt_id !== null && $sourceAccount->rt_id === $destinationAccount->rt_id)
                ? $sourceAccount->rt_id
                : null;

            // 9. Persist immutable transfer record
            /** @var CashTransfer $transfer */
            $transfer = CashTransfer::create([
                'organization_id' => $sourceAccount->organization_id,
                'rw_id' => $sourceAccount->rw_id,
                'rt_id' => $rtId,
                'source_cash_account_id' => $sourceAccount->id,
                'destination_cash_account_id' => $destinationAccount->id,
                'amount' => $input->amount,
                'transfer_date' => $input->transferDate,
                'description' => $input->description,
                'reference_number' => $input->referenceNumber,
                'evidence_file_id' => $input->evidenceFileId,
                'recorded_by' => $actor->id,
                'is_reversal' => false,
                'original_transfer_id' => null,
                'reversal_reason' => null,
            ]);

            // 10. Audit log
            $this->auditLogger->record(
                actor: $actor,
                event: 'finance.transfer.recorded',
                description: "Internal cash transfer of Rp {$transfer->amount} from '{$sourceAccount->name}' to '{$destinationAccount->name}' recorded.",
                subject: $transfer,
                scope: $transfer->scopeRef(),
                properties: [
                    'transfer_id' => $transfer->id,
                    'source_cash_account_id' => $transfer->source_cash_account_id,
                    'destination_cash_account_id' => $transfer->destination_cash_account_id,
                    'amount' => $transfer->amount,
                    'transfer_date' => $transfer->transfer_date->toDateString(),
                    'reference_number' => $transfer->reference_number,
                ]
            );

            return $transfer->load(['sourceAccount', 'destinationAccount', 'recorder']);
        });
    }
}
