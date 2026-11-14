<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Finance\Enums\BudgetPlanStatus;
use App\Domain\Finance\Exceptions\BudgetValidationException;
use App\Domain\Finance\Models\BudgetLine;
use App\Domain\Finance\Models\BudgetPlan;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class AmendBudgetPlanAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(BudgetPlan $plan, User $actor, ?string $notes = null): BudgetPlan
    {
        if ($plan->status !== BudgetPlanStatus::Published) {
            throw new BudgetValidationException("Hanya rencana anggaran yang telah diterbitkan yang dapat diamandemen. Status saat ini: {$plan->status->value}.");
        }

        return DB::transaction(function () use ($plan, $actor, $notes): BudgetPlan {
            /** @var BudgetPlan $lockedPlan */
            $lockedPlan = BudgetPlan::where('id', $plan->id)->lockForUpdate()->firstOrFail();

            if ($lockedPlan->status !== BudgetPlanStatus::Published) {
                throw new BudgetValidationException('Rencana anggaran telah berubah status dan tidak dapat diamandemen.');
            }

            // Check if draft already exists
            $existingDraftQuery = BudgetPlan::query()
                ->where('organization_id', $lockedPlan->organization_id)
                ->where('rw_id', $lockedPlan->rw_id)
                ->where('year', $lockedPlan->year)
                ->where('month', $lockedPlan->month)
                ->where('status', BudgetPlanStatus::Draft);

            if ($lockedPlan->rt_id !== null) {
                $existingDraftQuery->where('rt_id', $lockedPlan->rt_id);
            } else {
                $existingDraftQuery->whereNull('rt_id');
            }

            if ($existingDraftQuery->exists()) {
                throw new BudgetValidationException('Draf amandemen untuk periode ini sudah ada. Silakan gunakan atau selesaikan draf tersebut.');
            }

            // Max version + 1
            $maxVersionQuery = BudgetPlan::query()
                ->where('organization_id', $lockedPlan->organization_id)
                ->where('rw_id', $lockedPlan->rw_id)
                ->where('year', $lockedPlan->year)
                ->where('month', $lockedPlan->month);

            if ($lockedPlan->rt_id !== null) {
                $maxVersionQuery->where('rt_id', $lockedPlan->rt_id);
            } else {
                $maxVersionQuery->whereNull('rt_id');
            }

            $nextVersion = ((int) $maxVersionQuery->max('version')) + 1;

            /** @var BudgetPlan $amendedDraft */
            $amendedDraft = BudgetPlan::create([
                'organization_id' => $lockedPlan->organization_id,
                'rw_id' => $lockedPlan->rw_id,
                'rt_id' => $lockedPlan->rt_id,
                'year' => $lockedPlan->year,
                'month' => $lockedPlan->month,
                'version' => $nextVersion,
                'status' => BudgetPlanStatus::Draft,
                'notes' => $notes ?? "Amandemen dari versi {$lockedPlan->version}: " . ($lockedPlan->notes ?? ''),
                'created_by' => $actor->id,
            ]);

            // Copy lines from the published plan to the new draft
            foreach ($lockedPlan->lines as $line) {
                BudgetLine::create([
                    'budget_plan_id' => $amendedDraft->id,
                    'category_id' => $line->category_id,
                    'planned_amount' => $line->planned_amount,
                    'notes' => $line->notes,
                ]);
            }

            $this->auditLogger->record(
                actor: $actor,
                event: 'finance.budget.amended',
                description: "Created amendment draft (v{$amendedDraft->version}) from published plan (v{$lockedPlan->version}) for {$amendedDraft->year}-{$amendedDraft->month}",
                subject: $amendedDraft,
                scope: $amendedDraft->scopeRef(),
                properties: [
                    'original_budget_plan_id' => $lockedPlan->id,
                    'new_budget_plan_id' => $amendedDraft->id,
                    'previous_version' => $lockedPlan->version,
                    'new_version' => $amendedDraft->version,
                ]
            );

            return $amendedDraft->fresh('lines.category');
        });
    }
}
