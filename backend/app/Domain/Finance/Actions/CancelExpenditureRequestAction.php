<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Finance\DTOs\CancelExpenditureInput;
use App\Domain\Finance\Enums\ExpenditureRequestStatus;
use App\Domain\Finance\Exceptions\InvalidExpenditureRequestException;
use App\Domain\Finance\Models\ExpenditureRequest;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class CancelExpenditureRequestAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(ExpenditureRequest $request, CancelExpenditureInput $input, User $actor): ExpenditureRequest
    {
        if (in_array($request->status, [
            ExpenditureRequestStatus::Posted,
            ExpenditureRequestStatus::Rejected,
            ExpenditureRequestStatus::Cancelled,
        ], true)) {
            throw new InvalidExpenditureRequestException("Pengajuan belanja berstatus {$request->status->value} tidak dapat dibatalkan.");
        }

        return DB::transaction(function () use ($request, $input, $actor): ExpenditureRequest {
            /** @var ExpenditureRequest $locked */
            $locked = ExpenditureRequest::where('id', $request->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [
                ExpenditureRequestStatus::Posted,
                ExpenditureRequestStatus::Rejected,
                ExpenditureRequestStatus::Cancelled,
            ], true)) {
                throw new InvalidExpenditureRequestException('Pengajuan belanja telah berubah status.');
            }

            $previousStatus = $locked->status;
            $locked->status = ExpenditureRequestStatus::Cancelled;
            if ($input->cancellationReason !== null) {
                $locked->decision_reason = $input->cancellationReason;
            }
            $locked->save();

            $this->auditLogger->record(
                actor: $actor,
                event: 'finance.expenditure.cancelled',
                description: "Cancelled expenditure proposal '{$locked->title}' (previously {$previousStatus->value})",
                subject: $locked,
                scope: $locked->scopeRef(),
                properties: [
                    'expenditure_request_id' => $locked->id,
                    'previous_status' => $previousStatus->value,
                    'cancellation_reason' => $input->cancellationReason,
                    'cancelled_by' => $actor->id,
                ]
            );

            return $locked;
        });
    }
}
