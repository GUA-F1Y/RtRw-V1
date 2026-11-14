<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Finance\DTOs\UpdateBudgetPlanInput;
use App\Domain\Finance\Enums\BudgetPlanStatus;
use App\Domain\Finance\Exceptions\BudgetValidationException;
use App\Domain\Finance\Models\BudgetLine;
use App\Domain\Finance\Models\BudgetPlan;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateBudgetPlanAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CreateBudgetPlanAction $createAction,
    ) {}

    public function execute(BudgetPlan $plan, UpdateBudgetPlanInput $input, User $actor): BudgetPlan
    {
        if ($plan->status !== BudgetPlanStatus::Draft) {
            throw new BudgetValidationException("Hanya draf rencana anggaran yang dapat disunting. Status saat ini: {$plan->status->value}.");
        }

        return DB::transaction(function () use ($plan, $input, $actor): BudgetPlan {
            /** @var BudgetPlan $lockedPlan */
            $lockedPlan = BudgetPlan::where('id', $plan->id)->lockForUpdate()->firstOrFail();

            if ($lockedPlan->status !== BudgetPlanStatus::Draft) {
                throw new BudgetValidationException('Rencana anggaran telah berubah status dan tidak dapat disunting.');
            }

            if ($input->notes !== null) {
                $lockedPlan->notes = $input->notes;
                $lockedPlan->save();
            }

            // Replace lines if lines array is supplied
            if (! empty($input->lines)) {
                // Delete existing draft lines
                BudgetLine::where('budget_plan_id', $lockedPlan->id)->delete();

                // Sync new lines using the validation logic in CreateBudgetPlanAction
                $this->createAction->syncLines($lockedPlan, $input->lines);
            }

            $this->auditLogger->record(
                actor: $actor,
                event: 'finance.budget.updated',
                description: "Updated draft budget plan for {$lockedPlan->year}-{$lockedPlan->month} (v{$lockedPlan->version})",
                subject: $lockedPlan,
                scope: $lockedPlan->scopeRef(),
                properties: [
                    'budget_plan_id' => $lockedPlan->id,
                    'year' => $lockedPlan->year,
                    'month' => $lockedPlan->month,
                    'version' => $lockedPlan->version,
                ]
            );

            return $lockedPlan->fresh('lines.category');
        });
    }
}
