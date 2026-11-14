<?php

declare(strict_types=1);

namespace App\Domain\Finance\Queries;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\ScopedQuery;
use App\Domain\Finance\Enums\BudgetPlanStatus;
use App\Domain\Finance\Models\BudgetPlan;
use App\Domain\Finance\Services\BudgetCalculationService;
use App\Domain\Identity\Models\User;

final class BudgetUtilizationSummaryQuery
{
    public function __construct(
        private readonly ScopedQuery $scopedQuery,
        private readonly BudgetCalculationService $calculationService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     summary: array{
     *         total_planned: int,
     *         total_actual: int,
     *         total_commitments: int,
     *         total_remaining: int,
     *         total_variance: int,
     *         overall_utilization_percentage: float,
     *         plans_count: int
     *     },
     *     plans: list<array<string, mixed>>
     * }
     */
    public function execute(User $user, array $filters = []): array
    {
        $query = BudgetPlan::query()->with([
            'lines.category',
            'rw:id,number,name',
            'rt:id,number,name',
        ]);

        $query = $this->scopedQuery->constrain(
            $query,
            $user,
            PermissionKey::FinanceBudgetView,
            [
                'organization' => 'organization_id',
                'rw' => 'rw_id',
                'rt' => 'rt_id',
            ]
        );

        if (! empty($filters['year'])) {
            $query->where('year', (int) $filters['year']);
        }

        if (! empty($filters['month'])) {
            $query->where('month', (int) $filters['month']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        } else {
            // Default to PUBLISHED plans for utilization metrics
            $query->where('status', BudgetPlanStatus::Published);
        }

        if (! empty($filters['rw_id'])) {
            $query->where('rw_id', $filters['rw_id']);
        }

        if (array_key_exists('rt_id', $filters)) {
            if ($filters['rt_id'] === null || $filters['rt_id'] === 'null' || $filters['rt_id'] === '') {
                $query->whereNull('rt_id');
            } else {
                $query->where('rt_id', $filters['rt_id']);
            }
        }

        $plans = $query->orderByDesc('year')->orderByDesc('month')->get();

        $totalPlanned = 0;
        $totalActual = 0;
        $totalCommitments = 0;
        $planSummaries = [];

        foreach ($plans as $plan) {
            $metrics = $this->calculationService->calculatePlanMetrics($plan);

            $totalPlanned += $metrics['total_planned'];
            $totalActual += $metrics['total_actual'];
            $totalCommitments += $metrics['total_commitments'];

            $planSummaries[] = [
                'id' => $plan->id,
                'scope_type' => $plan->rt_id !== null ? 'RT' : 'RW',
                'year' => $plan->year,
                'month' => $plan->month,
                'version' => $plan->version,
                'status' => $plan->status->value,
                'rw' => $plan->rw ? [
                    'id' => $plan->rw->id,
                    'rw_number' => $plan->rw->rw_number,
                    'name' => $plan->rw->name,
                ] : null,
                'rt' => $plan->rt ? [
                    'id' => $plan->rt->id,
                    'rt_number' => $plan->rt->rt_number,
                    'name' => $plan->rt->name,
                ] : null,
                'metrics' => $metrics,
            ];
        }

        $totalRemaining = $totalPlanned - $totalActual - $totalCommitments;
        $totalVariance = $totalPlanned - $totalActual;
        $overallUtilization = $totalPlanned > 0
            ? round(($totalActual / $totalPlanned) * 100, 2)
            : 0.0;

        return [
            'summary' => [
                'total_planned' => $totalPlanned,
                'total_actual' => $totalActual,
                'total_commitments' => $totalCommitments,
                'total_remaining' => $totalRemaining,
                'total_variance' => $totalVariance,
                'overall_utilization_percentage' => $overallUtilization,
                'plans_count' => $plans->count(),
            ],
            'plans' => $planSummaries,
        ];
    }
}
