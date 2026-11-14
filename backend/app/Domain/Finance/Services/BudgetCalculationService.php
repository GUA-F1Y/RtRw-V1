<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Enums\ExpenditureRequestStatus;
use App\Domain\Finance\Models\BudgetLine;
use App\Domain\Finance\Models\BudgetPlan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BudgetCalculationService
{
    /**
     * Compute comprehensive metrics for a single budget line.
     *
     * @return array{
     *     planned_amount: int,
     *     effective_actual_expenses: int,
     *     posted_expenses: int,
     *     reversed_expenses: int,
     *     outstanding_commitments: int,
     *     remaining_budget: int,
     *     variance_amount: int,
     *     utilization_percentage: float,
     *     request_counts: array{
     *         pending: int,
     *         approved: int,
     *         rejected: int,
     *         posted: int,
     *         total: int
     *     }
     * }
     */
    public function calculateLineMetrics(BudgetLine $line): array
    {
        $batch = $this->batchLineMetrics(new Collection([$line]));

        return $batch[$line->id] ?? [
            'planned_amount' => (int) $line->planned_amount,
            'effective_actual_expenses' => 0,
            'posted_expenses' => 0,
            'reversed_expenses' => 0,
            'outstanding_commitments' => 0,
            'remaining_budget' => (int) $line->planned_amount,
            'variance_amount' => (int) $line->planned_amount,
            'utilization_percentage' => 0.0,
            'request_counts' => [
                'pending' => 0,
                'approved' => 0,
                'rejected' => 0,
                'posted' => 0,
                'total' => 0,
            ],
        ];
    }

    /**
     * Batch compute metrics for multiple budget lines across plans, preventing N+1 queries.
     *
     * @param  Collection<int, BudgetLine>  $lines
     * @return array<string, array{
     *     planned_amount: int,
     *     effective_actual_expenses: int,
     *     posted_expenses: int,
     *     reversed_expenses: int,
     *     outstanding_commitments: int,
     *     remaining_budget: int,
     *     variance_amount: int,
     *     utilization_percentage: float,
     *     request_counts: array{
     *         pending: int,
     *         approved: int,
     *         rejected: int,
     *         posted: int,
     *         total: int
     *     }
     * }>
     */
    public function batchLineMetrics(Collection $lines): array
    {
        if ($lines->isEmpty()) {
            return [];
        }

        $lines->each(fn ($l) => $l->loadMissing('budgetPlan'));

        $lineIds = $lines->pluck('id')->all();

        // 1. Commitments and request counts from expenditure_requests
        $requestStats = DB::table('expenditure_requests')
            ->whereIn('budget_line_id', $lineIds)
            ->groupBy('budget_line_id')
            ->select('budget_line_id')
            ->selectRaw(<<<'SQL'
                COALESCE(SUM(CASE WHEN status = 'APPROVED' THEN approved_amount ELSE 0 END), 0) AS commitments,
                COALESCE(SUM(CASE WHEN status = 'SUBMITTED' THEN 1 ELSE 0 END), 0) AS count_pending,
                COALESCE(SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END), 0) AS count_approved,
                COALESCE(SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END), 0) AS count_rejected,
                COALESCE(SUM(CASE WHEN status = 'POSTED' THEN 1 ELSE 0 END), 0) AS count_posted,
                COUNT(*) AS count_total
            SQL)
            ->get()
            ->keyBy('budget_line_id');

        // 2. Query actual expenses per budget line
        // Each budget line corresponds to (category_id, plan->organization_id, plan->rw_id, plan->rt_id, plan->year, plan->month)
        $metrics = [];

        foreach ($lines as $line) {
            $plan = $line->budgetPlan;
            $periodStart = Carbon::create($plan->year, $plan->month, 1)->startOfMonth()->toDateString();
            $periodEnd = Carbon::create($plan->year, $plan->month, 1)->endOfMonth()->toDateString();

            $txQuery = DB::table('financial_transactions')
                ->where('category_id', $line->category_id)
                ->where('organization_id', $plan->organization_id)
                ->where('rw_id', $plan->rw_id)
                ->where('type', 'EXPENSE')
                ->where('transaction_date', '>=', $periodStart)
                ->where('transaction_date', '<=', $periodEnd);

            if ($plan->rt_id !== null) {
                $txQuery->where('rt_id', $plan->rt_id);
            } else {
                $txQuery->whereNull('rt_id');
            }

            $txAgg = $txQuery->selectRaw(<<<'SQL'
                COALESCE(SUM(CASE WHEN is_reversal = FALSE THEN amount ELSE 0 END), 0) AS posted_expenses,
                COALESCE(SUM(CASE WHEN is_reversal = TRUE THEN amount ELSE 0 END), 0) AS reversed_expenses
            SQL)->first();

            $postedExpenses = (int) ($txAgg->posted_expenses ?? 0);
            $reversedExpenses = (int) ($txAgg->reversed_expenses ?? 0);
            $effectiveActualExpenses = $postedExpenses - $reversedExpenses;

            $reqRow = $requestStats->get($line->id);
            $commitments = (int) ($reqRow->commitments ?? 0);
            $pendingCount = (int) ($reqRow->count_pending ?? 0);
            $approvedCount = (int) ($reqRow->count_approved ?? 0);
            $rejectedCount = (int) ($reqRow->count_rejected ?? 0);
            $postedCount = (int) ($reqRow->count_posted ?? 0);
            $totalCount = (int) ($reqRow->count_total ?? 0);

            $plannedAmount = (int) $line->planned_amount;
            $remainingBudget = $plannedAmount - $effectiveActualExpenses - $commitments;
            $varianceAmount = $plannedAmount - $effectiveActualExpenses;
            $utilizationPercentage = $plannedAmount > 0
                ? round(($effectiveActualExpenses / $plannedAmount) * 100, 2)
                : 0.0;

            $metrics[$line->id] = [
                'planned_amount' => $plannedAmount,
                'effective_actual_expenses' => $effectiveActualExpenses,
                'posted_expenses' => $postedExpenses,
                'reversed_expenses' => $reversedExpenses,
                'outstanding_commitments' => $commitments,
                'remaining_budget' => $remainingBudget,
                'variance_amount' => $varianceAmount,
                'utilization_percentage' => $utilizationPercentage,
                'request_counts' => [
                    'pending' => $pendingCount,
                    'approved' => $approvedCount,
                    'rejected' => $rejectedCount,
                    'posted' => $postedCount,
                    'total' => $totalCount,
                ],
            ];
        }

        return $metrics;
    }

    /**
     * Compute aggregated plan-level summary metrics.
     *
     * @return array{
     *     total_planned: int,
     *     total_actual: int,
     *     total_commitments: int,
     *     total_remaining: int,
     *     total_variance: int,
     *     overall_utilization_percentage: float,
     *     total_lines_count: int,
     *     request_counts: array{
     *         pending: int,
     *         approved: int,
     *         rejected: int,
     *         posted: int,
     *         total: int
     *     }
     * }
     */
    public function calculatePlanMetrics(BudgetPlan $plan): array
    {
        $plan->loadMissing('lines');
        $lineMetrics = $this->batchLineMetrics($plan->lines);

        $totalPlanned = 0;
        $totalActual = 0;
        $totalCommitments = 0;
        $pending = 0;
        $approved = 0;
        $rejected = 0;
        $posted = 0;
        $totalReqs = 0;

        foreach ($lineMetrics as $m) {
            $totalPlanned += $m['planned_amount'];
            $totalActual += $m['effective_actual_expenses'];
            $totalCommitments += $m['outstanding_commitments'];
            $pending += $m['request_counts']['pending'];
            $approved += $m['request_counts']['approved'];
            $rejected += $m['request_counts']['rejected'];
            $posted += $m['request_counts']['posted'];
            $totalReqs += $m['request_counts']['total'];
        }

        $totalRemaining = $totalPlanned - $totalActual - $totalCommitments;
        $totalVariance = $totalPlanned - $totalActual;
        $utilizationPercentage = $totalPlanned > 0
            ? round(($totalActual / $totalPlanned) * 100, 2)
            : 0.0;

        return [
            'total_planned' => $totalPlanned,
            'total_actual' => $totalActual,
            'total_commitments' => $totalCommitments,
            'total_remaining' => $totalRemaining,
            'total_variance' => $totalVariance,
            'overall_utilization_percentage' => $utilizationPercentage,
            'total_lines_count' => $plan->lines->count(),
            'request_counts' => [
                'pending' => $pending,
                'approved' => $approved,
                'rejected' => $rejected,
                'posted' => $posted,
                'total' => $totalReqs,
            ],
        ];
    }
}
