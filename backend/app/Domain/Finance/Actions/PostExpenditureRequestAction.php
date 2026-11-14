<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Finance\DTOs\PostExpenditureInput;
use App\Domain\Finance\DTOs\RecordFinancialTransactionInput;
use App\Domain\Finance\Enums\ExpenditureRequestStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Exceptions\InvalidExpenditureRequestException;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\ExpenditureRequest;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class PostExpenditureRequestAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly RecordFinancialTransactionAction $recordTransactionAction,
    ) {}

    public function execute(ExpenditureRequest $request, PostExpenditureInput $input, User $actor): ExpenditureRequest
    {
        if ($request->status !== ExpenditureRequestStatus::Approved) {
            throw new InvalidExpenditureRequestException(
                "Hanya pengajuan belanja yang telah disetujui (APPROVED) yang dapat dibukukan ke kas. Status saat ini: {$request->status->value}."
            );
        }

        return DB::transaction(function () use ($request, $input, $actor): ExpenditureRequest {
            /** @var ExpenditureRequest $locked */
            $locked = ExpenditureRequest::where('id', $request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ExpenditureRequestStatus::Approved) {
                throw new InvalidExpenditureRequestException('Pengajuan belanja telah berubah status.');
            }

            if ($locked->financial_transaction_id !== null) {
                throw new InvalidExpenditureRequestException('Pengajuan belanja ini sudah pernah dibukukan ke buku kas.');
            }

            $approvedCap = (int) $locked->approved_amount;
            $actualAmount = $input->actualAmount ?? $approvedCap;

            if ($actualAmount <= 0) {
                throw new InvalidExpenditureRequestException('Nominal realisasi belanja harus lebih dari 0.');
            }

            if ($actualAmount > $approvedCap) {
                throw new InvalidExpenditureRequestException(
                    "Nominal realisasi belanja (Rp {$actualAmount}) melebihi pagu yang disetujui (Rp {$approvedCap}). Diperlukan pengajuan persetujuan baru untuk kelebihan biaya."
                );
            }

            $accountId = $input->cashAccountId ?? $locked->cash_account_id;
            $account = CashAccount::find($accountId);
            if ($account === null) {
                throw new InvalidExpenditureRequestException("Akun kas '{$accountId}' tidak ditemukan.");
            }

            $transactionDate = $input->transactionDate ?? now()->toDateString();
            $refNumber = $input->referenceNumber ?? $locked->reference_number;
            $evidenceId = $input->evidenceFileId ?? $locked->evidence_file_id;

            $description = "Realisasi Belanja: {$locked->title}";
            if ($locked->description) {
                $description .= " - {$locked->description}";
            }

            // Record transaction into authoritative immutable financial ledger
            $txInput = new RecordFinancialTransactionInput(
                cashAccountId: $account->id,
                categoryId: $locked->category_id,
                type: TransactionType::Expense,
                amount: $actualAmount,
                transactionDate: $transactionDate,
                description: $description,
                referenceNumber: $refNumber,
                evidenceFileId: $evidenceId,
            );

            $transaction = $this->recordTransactionAction->execute($txInput, $actor);

            // Transition request state atomically into POSTED linked to transaction
            $locked->status = ExpenditureRequestStatus::Posted;
            $locked->actual_amount = $actualAmount;
            $locked->financial_transaction_id = $transaction->id;
            $locked->posted_by = $actor->id;
            $locked->posted_at = now();
            $locked->save();

            $this->auditLogger->record(
                actor: $actor,
                event: 'finance.expenditure.posted',
                description: "Posted expenditure '{$locked->title}' of Rp {$locked->actual_amount} to account '{$account->name}' (tx: {$transaction->id})",
                subject: $locked,
                scope: $locked->scopeRef(),
                properties: [
                    'expenditure_request_id' => $locked->id,
                    'financial_transaction_id' => $transaction->id,
                    'cash_account_id' => $account->id,
                    'actual_amount' => $locked->actual_amount,
                    'approved_cap' => $approvedCap,
                    'posted_by' => $actor->id,
                ]
            );

            return $locked->fresh(['financialTransaction', 'cashAccount', 'category', 'budgetLine']);
        });
    }
}
