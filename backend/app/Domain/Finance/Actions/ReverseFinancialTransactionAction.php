<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Finance\DTOs\ReverseFinancialTransactionInput;
use App\Domain\Finance\Exceptions\CashAccountClosedException;
use App\Domain\Finance\Exceptions\InvalidTransactionException;
use App\Domain\Finance\Exceptions\TransactionAlreadyReversedException;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\FinancialTransaction;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ReverseFinancialTransactionAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(ReverseFinancialTransactionInput $input, ?User $actor = null): FinancialTransaction
    {
        if (trim($input->reversalReason) === '') {
            throw new InvalidTransactionException('Reversal reason is required.');
        }

        if ($actor === null) {
            throw new InvalidTransactionException('Authenticated user is required to reverse a transaction.');
        }

        return DB::transaction(function () use ($input, $actor): FinancialTransaction {
            /** @var FinancialTransaction|null $original */
            $original = FinancialTransaction::where('id', $input->transactionId)
                ->lockForUpdate()
                ->first();

            if ($original === null) {
                throw new InvalidTransactionException("Transaction '{$input->transactionId}' not found.");
            }

            if ($original->is_reversal) {
                throw new InvalidTransactionException('A reversal transaction cannot be reversed.');
            }

            /** @var CashAccount $account */
            $account = CashAccount::where('id', $original->cash_account_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($account->isClosed()) {
                throw new CashAccountClosedException('Cannot reverse transactions on a closed cash account.');
            }

            // Check if already reversed
            $hasReversal = FinancialTransaction::where('original_transaction_id', $original->id)->exists();
            if ($hasReversal) {
                throw new TransactionAlreadyReversedException('This transaction has already been reversed.');
            }

            $effectiveDate = $input->transactionDate ?? Carbon::now()->toDateString();

            $isPeriodClosed = \App\Domain\Finance\Models\FinancialPeriodClosure::query()
                ->where('cash_account_id', $account->id)
                ->where('period_start', '<=', $effectiveDate)
                ->where('period_end', '>=', $effectiveDate)
                ->exists();

            if ($isPeriodClosed) {
                throw new \App\Domain\Finance\Exceptions\ClosedPeriodException(
                    "Cannot reverse transaction: the accounting period for date {$effectiveDate} is closed. Corrections must be recorded in an open period."
                );
            }

            $refNumber = $original->reference_number !== null ? "REV-{$original->reference_number}" : null;

            /** @var FinancialTransaction $reversal */
            $reversal = FinancialTransaction::create([
                'organization_id' => $original->organization_id,
                'rw_id' => $original->rw_id,
                'rt_id' => $original->rt_id,
                'cash_account_id' => $original->cash_account_id,
                'category_id' => $original->category_id,
                'type' => $original->type,
                'amount' => $original->amount,
                'transaction_date' => $effectiveDate,
                'description' => "PEMBALIKAN: {$original->description}",
                'reference_number' => $refNumber,
                'evidence_file_id' => null,
                'recorded_by' => $actor->id,
                'is_reversal' => true,
                'original_transaction_id' => $original->id,
                'reversal_reason' => $input->reversalReason,
            ]);

            $this->auditLogger->record(
                actor: $actor,
                event: 'finance.transaction.reversed',
                description: "Reversed transaction {$original->id} ({$original->type->value} Rp {$original->amount}): {$input->reversalReason}",
                subject: $reversal,
                scope: $reversal->scopeRef(),
                properties: [
                    'original_transaction_id' => $original->id,
                    'reversal_transaction_id' => $reversal->id,
                    'cash_account_id' => $account->id,
                    'amount' => $original->amount,
                    'type' => $original->type->value,
                    'reversal_reason' => $input->reversalReason,
                ]
            );

            return $reversal;
        });
    }
}
