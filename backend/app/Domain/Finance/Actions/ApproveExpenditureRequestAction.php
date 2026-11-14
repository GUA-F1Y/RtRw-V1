<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Finance\DTOs\ApproveExpenditureInput;
use App\Domain\Finance\Enums\ExpenditureRequestStatus;
use App\Domain\Finance\Exceptions\InsufficientBudgetException;
use App\Domain\Finance\Exceptions\InvalidExpenditureRequestException;
use App\Domain\Finance\Exceptions\SelfApprovalException;
use App\Domain\Finance\Models\BudgetLine;
use App\Domain\Finance\Models\ExpenditureRequest;
use App\Domain\Finance\Services\BudgetCalculationService;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class ApproveExpenditureRequestAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly BudgetCalculationService $calculationService,
    ) {}

    public function execute(ExpenditureRequest $request, ApproveExpenditureInput $input, User $actor): ExpenditureRequest
    {
        // Enforce segregation of duties
        if ($request->requested_by === $actor->id) {
            throw new SelfApprovalException('Pemohon pengajuan belanja dilarang menyetujui pengajuannya sendiri.');
        }

        if ($request->status !== ExpenditureRequestStatus::Submitted) {
            throw new InvalidExpenditureRequestException("Hanya pengajuan belanja berstatus Menunggu Persetujuan (SUBMITTED) yang dapat disetujui. Status saat ini: {$request->status->value}.");
        }

        return DB::transaction(function () use ($request, $input, $actor): ExpenditureRequest {
            /** @var ExpenditureRequest $locked */
            $locked = ExpenditureRequest::where('id', $request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ExpenditureRequestStatus::Submitted) {
                throw new InvalidExpenditureRequestException('Pengajuan belanja telah berubah status.');
            }

            /** @var BudgetLine $budgetLine */
            $budgetLine = BudgetLine::where('id', $locked->budget_line_id)->lockForUpdate()->firstOrFail();

            $metrics = $this->calculationService->calculateLineMetrics($budgetLine);
            $available = $metrics['remaining_budget'];

            $approvedAmount = $input->approvedAmount ?? $locked->proposed_amount;

            if ($approvedAmount <= 0) {
                throw new InvalidExpenditureRequestException('Nominal persetujuan harus lebih dari 0.');
            }

            if ($approvedAmount > $locked->proposed_amount) {
                throw new InvalidExpenditureRequestException("Nominal persetujuan (Rp {$approvedAmount}) tidak boleh melebihi nominal yang diajukan (Rp {$locked->proposed_amount}).");
            }

            if ($approvedAmount > $available) {
                throw new InsufficientBudgetException(
                    "Sisa pagu anggaran tidak mencukupi untuk menyetujui pengajuan ini. Sisa tersedia: Rp " . number_format($available, 0, ',', '.') . ", Disetujui: Rp " . number_format($approvedAmount, 0, ',', '.')
                );
            }

            $locked->status = ExpenditureRequestStatus::Approved;
            $locked->approved_amount = $approvedAmount;
            $locked->approved_by = $actor->id;
            $locked->decision_at = now();
            $locked->decision_reason = $input->decisionReason;
            $locked->save();

            $this->auditLogger->record(
                actor: $actor,
                event: 'finance.expenditure.approved',
                description: "Approved expenditure proposal '{$locked->title}' for Rp {$locked->approved_amount} (cap Rp {$locked->proposed_amount})",
                subject: $locked,
                scope: $locked->scopeRef(),
                properties: [
                    'expenditure_request_id' => $locked->id,
                    'proposed_amount' => $locked->proposed_amount,
                    'approved_amount' => $locked->approved_amount,
                    'approver_id' => $actor->id,
                    'decision_reason' => $locked->decision_reason,
                ]
            );

            return $locked;
        });
    }
}
