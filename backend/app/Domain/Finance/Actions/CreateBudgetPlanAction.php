<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Finance\DTOs\CreateBudgetPlanInput;
use App\Domain\Finance\Enums\BudgetPlanStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Exceptions\BudgetValidationException;
use App\Domain\Finance\Exceptions\CategoryTypeMismatchException;
use App\Domain\Finance\Exceptions\FinanceScopeMismatch;
use App\Domain\Finance\Models\BudgetLine;
use App\Domain\Finance\Models\BudgetPlan;
use App\Domain\Finance\Models\FinanceCategory;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Illuminate\Support\Facades\DB;

final class CreateBudgetPlanAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(
        CreateBudgetPlanInput $input,
        User $actor,
        Organization $organization,
        Rw $rw,
        ?Rt $rt = null
    ): BudgetPlan {
        if ($input->year < 2000 || $input->year > 2100) {
            throw new BudgetValidationException('Tahun rencana anggaran harus antara 2000 dan 2100.');
        }

        if ($input->month < 1 || $input->month > 12) {
            throw new BudgetValidationException('Bulan rencana anggaran harus antara 1 dan 12.');
        }

        if ($rt !== null && $rt->rw_id !== $rw->id) {
            throw new FinanceScopeMismatch('RT yang dipilih tidak berada di bawah RW yang bersangkutan.');
        }

        if ($rw->organization_id !== $organization->id) {
            throw new FinanceScopeMismatch('RW tidak berada di bawah organisasi yang bersangkutan.');
        }

        return DB::transaction(function () use ($input, $actor, $organization, $rw, $rt): BudgetPlan {
            // Check if active draft already exists for this scope/year/month
            $existingDraftQuery = BudgetPlan::query()
                ->where('organization_id', $organization->id)
                ->where('rw_id', $rw->id)
                ->where('year', $input->year)
                ->where('month', $input->month)
                ->where('status', BudgetPlanStatus::Draft);

            if ($rt !== null) {
                $existingDraftQuery->where('rt_id', $rt->id);
            } else {
                $existingDraftQuery->whereNull('rt_id');
            }

            if ($existingDraftQuery->exists()) {
                throw new BudgetValidationException('Draf rencana anggaran untuk periode ini sudah ada. Silakan perbarui draf yang ada.');
            }

            // Determine version: max version + 1
            $maxVersionQuery = BudgetPlan::query()
                ->where('organization_id', $organization->id)
                ->where('rw_id', $rw->id)
                ->where('year', $input->year)
                ->where('month', $input->month);

            if ($rt !== null) {
                $maxVersionQuery->where('rt_id', $rt->id);
            } else {
                $maxVersionQuery->whereNull('rt_id');
            }

            $version = ((int) $maxVersionQuery->max('version')) + 1;

            /** @var BudgetPlan $plan */
            $plan = BudgetPlan::create([
                'organization_id' => $organization->id,
                'rw_id' => $rw->id,
                'rt_id' => $rt?->id,
                'year' => $input->year,
                'month' => $input->month,
                'version' => $version,
                'status' => BudgetPlanStatus::Draft,
                'notes' => $input->notes,
                'created_by' => $actor->id,
            ]);

            // Validate and create budget lines
            $this->syncLines($plan, $input->lines);

            $this->auditLogger->record(
                actor: $actor,
                event: 'finance.budget.created',
                description: "Created draft budget plan for {$plan->year}-{$plan->month} (v{$plan->version})",
                subject: $plan,
                scope: $plan->scopeRef(),
                properties: [
                    'budget_plan_id' => $plan->id,
                    'year' => $plan->year,
                    'month' => $plan->month,
                    'version' => $plan->version,
                    'lines_count' => count($input->lines),
                ]
            );

            return $plan;
        });
    }

    /**
     * @param  list<array{category_id: string, planned_amount: int, notes?: string|null}>  $lines
     */
    public function syncLines(BudgetPlan $plan, array $lines): void
    {
        $seenCategories = [];
        $totalPlanned = 0;

        foreach ($lines as $line) {
            $catId = $line['category_id'] ?? null;
            $amount = (int) ($line['planned_amount'] ?? 0);

            if (! $catId) {
                throw new BudgetValidationException('Setiap pos anggaran wajib memiliki kategori pengeluaran.');
            }

            if (isset($seenCategories[$catId])) {
                throw new BudgetValidationException("Kategori pengeluaran '{$catId}' terduplikasi dalam satu rencana anggaran.");
            }
            $seenCategories[$catId] = true;

            if ($amount < 0) {
                throw new BudgetValidationException('Nominal anggaran tidak boleh bernilai negatif.');
            }

            if ($amount > 100_000_000_000_000) {
                throw new BudgetValidationException('Nominal anggaran melebihi batas maksimum sistem.');
            }

            $totalPlanned += $amount;
            if ($totalPlanned > 100_000_000_000_000) {
                throw new BudgetValidationException('Total agregat anggaran melebihi batas maksimum integer.');
            }

            /** @var FinanceCategory|null $category */
            $category = FinanceCategory::find($catId);
            if ($category === null) {
                throw new BudgetValidationException("Kategori keuangan '{$catId}' tidak ditemukan.");
            }

            if (! $category->is_active) {
                throw new BudgetValidationException("Kategori '{$category->name}' tidak aktif dan tidak dapat dianggarkan.");
            }

            if ($category->type !== TransactionType::Expense) {
                throw new CategoryTypeMismatchException("Hanya kategori bertipe PENGELUARAN (EXPENSE) yang dapat dianggarkan. Kategori '{$category->name}' bertipe {$category->type->value}.");
            }

            if ($category->organization_id !== $plan->organization_id) {
                throw new FinanceScopeMismatch('Kategori keuangan tidak berada dalam organisasi yang sama.');
            }

            if ($category->rw_id !== null && $category->rw_id !== $plan->rw_id) {
                throw new FinanceScopeMismatch('Cakupan RW kategori tidak sesuai dengan cakupan rencana anggaran.');
            }

            if ($plan->rt_id !== null) {
                if ($category->rt_id !== null && $category->rt_id !== $plan->rt_id) {
                    throw new FinanceScopeMismatch('Cakupan RT kategori tidak sesuai dengan rencana anggaran RT.');
                }
            } else {
                if ($category->rt_id !== null) {
                    throw new FinanceScopeMismatch('Rencana anggaran tingkat RW tidak dapat menggunakan kategori khusus RT.');
                }
            }

            BudgetLine::create([
                'budget_plan_id' => $plan->id,
                'category_id' => $category->id,
                'planned_amount' => $amount,
                'notes' => $line['notes'] ?? null,
            ]);
        }
    }
}
