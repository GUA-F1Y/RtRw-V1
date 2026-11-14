<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\File\Models\StoredFile;
use App\Domain\Finance\DTOs\CreateExpenditureRequestInput;
use App\Domain\Finance\Enums\BudgetPlanStatus;
use App\Domain\Finance\Enums\ExpenditureRequestStatus;
use App\Domain\Finance\Exceptions\CashAccountClosedException;
use App\Domain\Finance\Exceptions\FinanceScopeMismatch;
use App\Domain\Finance\Exceptions\InvalidExpenditureRequestException;
use App\Domain\Finance\Models\BudgetLine;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\ExpenditureRequest;
use App\Domain\Identity\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class CreateExpenditureRequestAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CreateExpenditureRequestInput $input, User $actor): ExpenditureRequest
    {
        if ($input->proposedAmount <= 0) {
            throw new InvalidExpenditureRequestException('Nominal pengajuan belanja harus lebih dari 0.');
        }

        if ($input->proposedAmount > 100_000_000_000_000) {
            throw new InvalidExpenditureRequestException('Nominal pengajuan belanja melebihi batas sistem.');
        }

        /** @var BudgetLine|null $budgetLine */
        $budgetLine = BudgetLine::with(['budgetPlan', 'category'])->find($input->budgetLineId);
        if ($budgetLine === null) {
            throw new InvalidExpenditureRequestException("Pos anggaran '{$input->budgetLineId}' tidak ditemukan.");
        }

        $plan = $budgetLine->budgetPlan;
        if ($plan === null) {
            throw new InvalidExpenditureRequestException('Rencana anggaran terkait tidak ditemukan.');
        }

        if ($plan->status !== BudgetPlanStatus::Published) {
            throw new InvalidExpenditureRequestException("Pengajuan belanja hanya dapat dilakukan terhadap rencana anggaran yang berstatus Diterbitkan (PUBLISHED). Status saat ini: {$plan->status->value}.");
        }

        $plannedCarbon = Carbon::parse($input->plannedDate);
        if ($plannedCarbon->year !== $plan->year || $plannedCarbon->month !== $plan->month) {
            throw new InvalidExpenditureRequestException("Tanggal rencana belanja ({$input->plannedDate}) harus sesuai dengan periode anggaran ({$plan->year}-{$plan->month}).");
        }

        /** @var CashAccount|null $account */
        $account = CashAccount::find($input->cashAccountId);
        if ($account === null) {
            throw new InvalidExpenditureRequestException("Akun kas '{$input->cashAccountId}' tidak ditemukan.");
        }

        if ($account->isClosed()) {
            throw new CashAccountClosedException('Tidak dapat mengajukan belanja untuk akun kas yang sudah ditutup.');
        }

        if ($account->organization_id !== $plan->organization_id) {
            throw new FinanceScopeMismatch('Akun kas tidak berada dalam organisasi yang sama dengan rencana anggaran.');
        }

        if ($account->rw_id !== $plan->rw_id) {
            throw new FinanceScopeMismatch('Cakupan RW akun kas tidak sesuai dengan rencana anggaran.');
        }

        if ($plan->rt_id !== null) {
            if ($account->rt_id !== $plan->rt_id) {
                throw new FinanceScopeMismatch('Akun kas RT tidak sesuai dengan rencana anggaran RT.');
            }
        } else {
            if ($account->rt_id !== null) {
                throw new FinanceScopeMismatch('Rencana anggaran RW harus menggunakan akun kas tingkat RW, bukan akun RT.');
            }
        }

        if ($input->evidenceFileId !== null) {
            /** @var StoredFile|null $file */
            $file = StoredFile::find($input->evidenceFileId);
            if ($file === null) {
                throw new InvalidExpenditureRequestException("Berkas bukti '{$input->evidenceFileId}' tidak ditemukan.");
            }
            if ($file->organization_id !== $plan->organization_id) {
                throw new FinanceScopeMismatch('Berkas bukti tidak berada dalam organisasi yang sama.');
            }
        }

        return DB::transaction(function () use ($input, $actor, $plan, $budgetLine, $account): ExpenditureRequest {
            $status = $input->submit ? ExpenditureRequestStatus::Submitted : ExpenditureRequestStatus::Draft;
            $submittedAt = $input->submit ? now() : null;

            /** @var ExpenditureRequest $request */
            $request = ExpenditureRequest::create([
                'organization_id' => $plan->organization_id,
                'rw_id' => $plan->rw_id,
                'rt_id' => $plan->rt_id,
                'budget_line_id' => $budgetLine->id,
                'cash_account_id' => $account->id,
                'category_id' => $budgetLine->category_id,
                'title' => $input->title,
                'description' => $input->description,
                'proposed_amount' => $input->proposedAmount,
                'approved_amount' => null,
                'actual_amount' => null,
                'planned_date' => $input->plannedDate,
                'reference_number' => $input->referenceNumber,
                'evidence_file_id' => $input->evidenceFileId,
                'status' => $status,
                'requested_by' => $actor->id,
                'submitted_at' => $submittedAt,
            ]);

            $event = $input->submit ? 'finance.expenditure.submitted' : 'finance.expenditure.created';
            $this->auditLogger->record(
                actor: $actor,
                event: $event,
                description: "Expenditure proposal '{$request->title}' of Rp {$request->proposed_amount} {$status->value}",
                subject: $request,
                scope: $request->scopeRef(),
                properties: [
                    'expenditure_request_id' => $request->id,
                    'budget_line_id' => $budgetLine->id,
                    'proposed_amount' => $request->proposed_amount,
                    'status' => $status->value,
                    'requested_by' => $actor->id,
                ]
            );

            return $request;
        });
    }
}
