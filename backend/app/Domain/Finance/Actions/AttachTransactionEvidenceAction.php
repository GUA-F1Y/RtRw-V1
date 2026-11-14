<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\File\Models\Attachment;
use App\Domain\File\Models\StoredFile;
use App\Domain\Finance\Exceptions\FinanceScopeMismatch;
use App\Domain\Finance\Exceptions\InvalidTransactionException;
use App\Domain\Finance\Models\FinancialTransaction;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class AttachTransactionEvidenceAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(
        FinancialTransaction $transaction,
        StoredFile $file,
        User $actor,
        string $collection = 'evidence'
    ): Attachment {
        // Enforce organization isolation
        if ($file->organization_id !== $transaction->organization_id) {
            throw new FinanceScopeMismatch('Evidence file does not belong to the same organization as the transaction.');
        }

        // Enforce RW scope isolation
        if ($file->rw_id !== null && $file->rw_id !== $transaction->rw_id) {
            throw new FinanceScopeMismatch('Evidence file RW scope does not match the transaction RW scope.');
        }

        // Enforce RT scope isolation
        if ($file->rt_id !== null && $file->rt_id !== $transaction->rt_id) {
            throw new FinanceScopeMismatch('Evidence file RT scope does not match the transaction RT scope.');
        }

        // Prevent duplicate attachment if it is already the primary evidence or already attached
        if ($transaction->evidence_file_id === $file->id) {
            throw new InvalidTransactionException('This file is already associated as the primary evidence of the transaction.');
        }

        return DB::transaction(function () use ($transaction, $file, $actor, $collection): Attachment {
            $existing = Attachment::query()
                ->where('file_id', $file->id)
                ->where('attachable_type', $transaction->getMorphClass())
                ->where('attachable_id', $transaction->id)
                ->where('collection', $collection)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            /** @var Attachment $attachment */
            $attachment = Attachment::query()->create([
                'file_id' => $file->id,
                'attachable_type' => $transaction->getMorphClass(),
                'attachable_id' => $transaction->id,
                'collection' => $collection,
            ]);

            $this->auditLogger->record(
                actor: $actor,
                event: 'finance.transaction.evidence_attached',
                description: "Attached evidence file {$file->original_name}.{$file->extension} to transaction {$transaction->id}",
                subject: $transaction,
                scope: $transaction->scopeRef(),
                properties: [
                    'transaction_id' => $transaction->id,
                    'file_id' => $file->id,
                    'file_name' => $file->original_name.'.'.$file->extension,
                    'collection' => $collection,
                ]
            );

            return $attachment;
        });
    }
}
