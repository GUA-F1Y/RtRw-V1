<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Finance\Enums\ExpenditureRequestStatus;
use App\Domain\Finance\Exceptions\InvalidExpenditureRequestException;
use App\Domain\Finance\Models\ExpenditureRequest;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class SubmitExpenditureRequestAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(ExpenditureRequest $request, User $actor): ExpenditureRequest
    {
        if ($request->status !== ExpenditureRequestStatus::Draft) {
            throw new InvalidExpenditureRequestException("Hanya draf pengajuan belanja yang dapat diajukan. Status saat ini: {$request->status->value}.");
        }

        return DB::transaction(function () use ($request, $actor): ExpenditureRequest {
            /** @var ExpenditureRequest $locked */
            $locked = ExpenditureRequest::where('id', $request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ExpenditureRequestStatus::Draft) {
                throw new InvalidExpenditureRequestException('Pengajuan belanja telah berubah status.');
            }

            $locked->status = ExpenditureRequestStatus::Submitted;
            $locked->submitted_at = now();
            $locked->save();

            $this->auditLogger->record(
                actor: $actor,
                event: 'finance.expenditure.submitted',
                description: "Submitted expenditure proposal '{$locked->title}' of Rp {$locked->proposed_amount}",
                subject: $locked,
                scope: $locked->scopeRef(),
                properties: [
                    'expenditure_request_id' => $locked->id,
                    'proposed_amount' => $locked->proposed_amount,
                ]
            );

            return $locked;
        });
    }
}
