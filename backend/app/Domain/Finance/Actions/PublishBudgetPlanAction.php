<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Finance\Enums\BudgetPlanStatus;
use App\Domain\Finance\Exceptions\BudgetValidationException;
use App\Domain\Finance\Models\BudgetPlan;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class PublishBudgetPlanAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(BudgetPlan $plan, User $actor): BudgetPlan
    {
        if ($plan->status !== BudgetPlanStatus::Draft) {
            throw new BudgetValidationException("Hanya draf rencana anggaran yang dapat diterbitkan. Status saat ini: {$plan->status->value}.");
        }

        return DB::transaction(function () use ($plan, $actor): BudgetPlan {
            /** @var BudgetPlan $lockedPlan */
            $lockedPlan = BudgetPlan::where('id', $plan->id)->lockForUpdate()->firstOrFail();

            if ($lockedPlan->status !== BudgetPlanStatus::Draft) {
                throw new BudgetValidationException('Rencana anggaran telah berubah status dan tidak dapat diterbitkan.');
            }

            if ($lockedPlan->lines()->count() === 0) {
                throw new BudgetValidationException('Rencana anggaran harus memiliki minimal satu pos pengeluaran sebelum diterbitkan.');
            }

            // Lock and supersede any currently published plan for the same scope/period
            $currentlyPublishedQuery = BudgetPlan::query()
                ->where('organization_id', $lockedPlan->organization_id)
                ->where('rw_id', $lockedPlan->rw_id)
                ->where('year', $lockedPlan->year)
                ->where('month', $lockedPlan->month)
                ->where('status', BudgetPlanStatus::Published)
                ->lockForUpdate();

            if ($lockedPlan->rt_id !== null) {
                $currentlyPublishedQuery->where('rt_id', $lockedPlan->rt_id);
            } else {
                $currentlyPublishedQuery->whereNull('rt_id');
            }

            $priorPublished = $currentlyPublishedQuery->get();
            foreach ($priorPublished as $prior) {
                $prior->update(['status' => BudgetPlanStatus::Superseded]);
            }

            $lockedPlan->status = BudgetPlanStatus::Published;
            $lockedPlan->published_by = $actor->id;
            $lockedPlan->published_at = now();
            $lockedPlan->save();

            $this->auditLogger->record(
                actor: $actor,
                event: 'finance.budget.published',
                description: "Published budget plan for {$lockedPlan->year}-{$lockedPlan->month} (v{$lockedPlan->version})",
                subject: $lockedPlan,
                scope: $lockedPlan->scopeRef(),
                properties: [
                    'budget_plan_id' => $lockedPlan->id,
                    'year' => $lockedPlan->year,
                    'month' => $lockedPlan->month,
                    'version' => $lockedPlan->version,
                    'published_by' => $actor->id,
                    'superseded_versions' => $priorPublished->pluck('version')->all(),
                ]
            );

            return $lockedPlan->fresh('lines.category');
        });
    }
}
