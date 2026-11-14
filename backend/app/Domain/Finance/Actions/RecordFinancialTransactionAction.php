<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\File\Models\StoredFile;
use App\Domain\Finance\DTOs\RecordFinancialTransactionInput;
use App\Domain\Finance\Exceptions\CashAccountClosedException;
use App\Domain\Finance\Exceptions\CategoryTypeMismatchException;
use App\Domain\Finance\Exceptions\FinanceScopeMismatch;
use App\Domain\Finance\Exceptions\InvalidTransactionException;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\FinanceCategory;
use App\Domain\Finance\Models\FinancialTransaction;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class RecordFinancialTransactionAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(RecordFinancialTransactionInput $input, ?User $actor = null): FinancialTransaction
    {
        if ($input->amount <= 0) {
            throw new InvalidTransactionException('Transaction amount must be a positive integer.');
        }

        if ($actor === null) {
            throw new InvalidTransactionException('Authenticated user is required to record a transaction.');
        }

        return DB::transaction(function () use ($input, $actor): FinancialTransaction {
            /** @var CashAccount|null $account */
            $account = CashAccount::where('id', $input->cashAccountId)
                ->lockForUpdate()
                ->first();

            if ($account === null) {
                throw new InvalidTransactionException("Cash account '{$input->cashAccountId}' not found.");
            }

            if ($account->isClosed()) {
                throw new CashAccountClosedException('Cannot post transactions to a closed cash account.');
            }

            $isPeriodClosed = \App\Domain\Finance\Models\FinancialPeriodClosure::query()
                ->where('cash_account_id', $account->id)
                ->where('period_start', '<=', $input->transactionDate)
                ->where('period_end', '>=', $input->transactionDate)
                ->exists();

            if ($isPeriodClosed) {
                throw new \App\Domain\Finance\Exceptions\ClosedPeriodException(
                    "Cannot record transaction: the accounting period for date {$input->transactionDate} is closed."
                );
            }

            /** @var FinanceCategory|null $category */
            $category = FinanceCategory::find($input->categoryId);
            if ($category === null) {
                throw new InvalidTransactionException("Category '{$input->categoryId}' not found.");
            }

            if (! $category->is_active) {
                throw new InvalidTransactionException("Category '{$category->name}' is inactive and cannot be used.");
            }

            if ($category->organization_id !== $account->organization_id) {
                throw new FinanceScopeMismatch('Category does not belong to the same organization as the cash account.');
            }

            if ($category->rw_id !== null && $category->rw_id !== $account->rw_id) {
                throw new FinanceScopeMismatch('Category RW scope does not match the cash account RW scope.');
            }

            if ($category->rt_id !== null && $category->rt_id !== $account->rt_id) {
                throw new FinanceScopeMismatch('Category RT scope does not match the cash account RT scope.');
            }

            if ($category->type !== $input->type) {
                throw new CategoryTypeMismatchException(
                    "Cannot record a {$input->type->value} transaction with {$category->type->value} category '{$category->name}'."
                );
            }

            if ($input->evidenceFileId !== null) {
                /** @var StoredFile|null $file */
                $file = StoredFile::find($input->evidenceFileId);
                if ($file === null) {
                    throw new InvalidTransactionException("Evidence file '{$input->evidenceFileId}' not found.");
                }
                if ($file->organization_id !== $account->organization_id) {
                    throw new FinanceScopeMismatch('Evidence file does not belong to the same organization.');
                }
                if ($file->rw_id !== null && $file->rw_id !== $account->rw_id) {
                    throw new FinanceScopeMismatch('Evidence file RW scope does not match the cash account RW scope.');
                }
                if ($file->rt_id !== null && $file->rt_id !== $account->rt_id) {
                    throw new FinanceScopeMismatch('Evidence file RT scope does not match the cash account RT scope.');
                }
            }

            /** @var FinancialTransaction $transaction */
            $transaction = FinancialTransaction::create([
                'organization_id' => $account->organization_id,
                'rw_id' => $account->rw_id,
                'rt_id' => $account->rt_id,
                'cash_account_id' => $account->id,
                'category_id' => $category->id,
                'type' => $input->type,
                'amount' => $input->amount,
                'transaction_date' => $input->transactionDate,
                'description' => $input->description,
                'reference_number' => $input->referenceNumber,
                'evidence_file_id' => $input->evidenceFileId,
                'recorded_by' => $actor->id,
                'is_reversal' => false,
                'original_transaction_id' => null,
                'reversal_reason' => null,
            ]);

            $this->auditLogger->record(
                actor: $actor,
                event: 'finance.transaction.recorded',
                description: "Recorded {$transaction->type->value} of Rp {$transaction->amount} in {$account->name}",
                subject: $transaction,
                scope: $transaction->scopeRef(),
                properties: [
                    'transaction_id' => $transaction->id,
                    'cash_account_id' => $account->id,
                    'category_id' => $category->id,
                    'type' => $transaction->type->value,
                    'amount' => $transaction->amount,
                    'transaction_date' => $transaction->transaction_date->toDateString(),
                ]
            );

            return $transaction;
        });
    }
}
