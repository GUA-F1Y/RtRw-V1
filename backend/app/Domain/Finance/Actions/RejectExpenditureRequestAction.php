<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Finance\DTOs\RejectExpenditureInput;
use App\Domain\Finance\Enums\ExpenditureRequestStatus;
use App\Domain\Finance\Exceptions\InvalidExpenditureRequestException;
use App\Domain\Finance\Exceptions\SelfApprovalException;
use App\Domain\Finance\Models\ExpenditureRequest;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class RejectExpenditureRequestAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(ExpenditureRequest $request, RejectExpenditureInput $input, User $actor): ExpenditureRequest
    {
        // Enforce segregation of duties
        if ($request->requested_by === $actor->id) {
            throw new SelfApprovalException('Pemohon pengajuan belanja dilarang menolak pengajuannya sendiri.');
        }

        if ($request->status !== ExpenditureRequestStatus::Submitted) {
            throw new InvalidExpenditureRequestException("Hanya pengajuan belanja berstatus Menunggu Persetujuan (SUBMITTED) yang dapat ditolak. Status saat ini: {$request->status->value}.");
        }

        return DB::transaction(function () use ($request, $input, $actor): ExpenditureRequest {
            /** @var ExpenditureRequest $locked */
            $locked = ExpenditureRequest::where('id', $request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ExpenditureRequestStatus::Submitted) {
                throw new InvalidExpenditureRequestException('Pengajuan belanja telah berubah status.');
            }

            $locked->status = ExpenditureRequestStatus::Rejected;
            $locked->approved_by = $actor->id;
            $locked->decision_at = now();
            $locked->decision_reason = $input->decisionReason;
            $locked->save();

            $this->auditLogger->record(
                actor: $actor,
                event: 'finance.expenditure.rejected',
                description: "Rejected expenditure proposal '{$locked->title}'",
                subject: $locked,
                scope: $locked->scopeRef(),
                properties: [
                    'expenditure_request_id' => $locked->id,
                    'proposed_amount' => $locked->proposed_amount,
                    'rejected_by' => $actor->id,
                    'decision_reason' => $locked->decision_reason,
                ]
            );

            return $locked;
        });
    }
}
