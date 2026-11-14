<?php

declare(strict_types=1);

namespace App\Domain\Overview\Queries;

use App\Domain\Authorization\Enums\ScopeLevel;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Organization\Enums\UnitStatus;
use App\Domain\Organization\Models\Rt;
use App\Domain\Overview\DTOs\HouseholdMetricsDto;
use App\Domain\Overview\DTOs\OverviewScopeDto;
use App\Domain\Overview\DTOs\OverviewSnapshot;
use App\Domain\Overview\DTOs\PopulationMetricsDto;
use App\Domain\Overview\DTOs\RtOverviewMetricDto;
use Illuminate\Support\Facades\DB;

final class GetOverviewQuery
{
    public function execute(ScopeRef $scopeRef, OverviewScopeDto $scopeDto): OverviewSnapshot
    {
        // 1. Population Aggregation
        $popQuery = DB::table('residents');
        if ($scopeRef->level() === ScopeLevel::Rt) {
            $popQuery->where('rt_id', $scopeRef->rtId);
        } elseif ($scopeRef->level() === ScopeLevel::Rw) {
            $popQuery->where('rw_id', $scopeRef->rwId);
        } else {
            $popQuery->where('organization_id', $scopeRef->organizationId);
        }

        $popResult = $popQuery->selectRaw("
            COUNT(*)::int AS total,
            COUNT(*) FILTER (WHERE status = 'ACTIVE')::int AS active,
            COUNT(*) FILTER (WHERE status = 'PROSPECTIVE')::int AS prospective,
            COUNT(*) FILTER (WHERE status = 'INACTIVE')::int AS inactive,
            COUNT(*) FILTER (WHERE status = 'MOVED')::int AS moved,
            COUNT(*) FILTER (WHERE status = 'DECEASED')::int AS deceased
        ")->first();

        $population = new PopulationMetricsDto(
            total: (int) ($popResult?->total ?? 0),
            active: (int) ($popResult?->active ?? 0),
            prospective: (int) ($popResult?->prospective ?? 0),
            inactive: (int) ($popResult?->inactive ?? 0),
            moved: (int) ($popResult?->moved ?? 0),
            deceased: (int) ($popResult?->deceased ?? 0),
        );

        // 2. Household Aggregation
        $hhQuery = DB::table('households');
        if ($scopeRef->level() === ScopeLevel::Rt) {
            $hhQuery->where('rt_id', $scopeRef->rtId);
        } elseif ($scopeRef->level() === ScopeLevel::Rw) {
            $hhQuery->where('rw_id', $scopeRef->rwId);
        } else {
            $hhQuery->where('organization_id', $scopeRef->organizationId);
        }

        $hhResult = $hhQuery->selectRaw("
            COUNT(*) FILTER (WHERE status = 'ACTIVE')::int AS active,
            COUNT(*) FILTER (WHERE status = 'HEAD_VACANT')::int AS head_vacant,
            COUNT(*) FILTER (WHERE status = 'MOVED')::int AS moved,
            COUNT(*) FILTER (WHERE status = 'DISSOLVED')::int AS dissolved,
            COUNT(*) FILTER (
                WHERE status = 'ACTIVE' AND id IN (
                    SELECT household_id FROM household_members
                    WHERE relationship = 'KEPALA_KELUARGA' AND is_active = true
                )
            )::int AS head_assigned
        ")->first();

        $households = new HouseholdMetricsDto(
            active: (int) ($hhResult?->active ?? 0),
            headAssigned: (int) ($hhResult?->head_assigned ?? 0),
            headVacant: (int) ($hhResult?->head_vacant ?? 0),
            moved: (int) ($hhResult?->moved ?? 0),
            dissolved: (int) ($hhResult?->dissolved ?? 0),
        );

        // 3. RT Units resolution for Breakdown
        if ($scopeRef->level() === ScopeLevel::Rt) {
            $rts = Rt::query()
                ->where('id', $scopeRef->rtId)
                ->where('status', UnitStatus::Active)
                ->get(['id', 'number', 'name']);
        } elseif ($scopeRef->level() === ScopeLevel::Rw) {
            $rts = Rt::query()
                ->where('rw_id', $scopeRef->rwId)
                ->where('status', UnitStatus::Active)
                ->orderBy('number')
                ->get(['id', 'number', 'name']);
        } else {
            $rts = Rt::query()
                ->whereHas('rw', fn ($q) => $q->where('organization_id', $scopeRef->organizationId))
                ->where('status', UnitStatus::Active)
                ->orderBy('number')
                ->get(['id', 'number', 'name']);
        }

        // 4. Grouped aggregations per RT (O(1) queries regardless of RT count)
        $residentCountsQuery = DB::table('residents');
        $householdCountsQuery = DB::table('households');

        if ($scopeRef->level() === ScopeLevel::Rt) {
            $residentCountsQuery->where('rt_id', $scopeRef->rtId);
            $householdCountsQuery->where('rt_id', $scopeRef->rtId);
        } elseif ($scopeRef->level() === ScopeLevel::Rw) {
            $residentCountsQuery->where('rw_id', $scopeRef->rwId);
            $householdCountsQuery->where('rw_id', $scopeRef->rwId);
        } else {
            $residentCountsQuery->where('organization_id', $scopeRef->organizationId);
            $householdCountsQuery->where('organization_id', $scopeRef->organizationId);
        }

        $residentCounts = $residentCountsQuery
            ->selectRaw('rt_id, COUNT(*)::int AS resident_count')
            ->groupBy('rt_id')
            ->pluck('resident_count', 'rt_id')
            ->all();

        $householdMetrics = $householdCountsQuery
            ->selectRaw("
                rt_id,
                COUNT(*) FILTER (WHERE status = 'ACTIVE')::int AS household_count,
                COUNT(*) FILTER (WHERE status = 'HEAD_VACANT')::int AS head_vacant_household_count
            ")
            ->groupBy('rt_id')
            ->get()
            ->keyBy('rt_id');

        $rtBreakdown = [];
        foreach ($rts as $rt) {
            $hh = $householdMetrics->get($rt->id);
            $rtCode = sprintf('%02d', $rt->number);
            $rtBreakdown[] = new RtOverviewMetricDto(
                rtId: $rt->id,
                rtCode: $rtCode,
                residentCount: (int) ($residentCounts[$rt->id] ?? 0),
                householdCount: (int) ($hh?->household_count ?? 0),
                headVacantHouseholdCount: (int) ($hh?->head_vacant_household_count ?? 0),
            );
        }

        return new OverviewSnapshot(
            scope: $scopeDto,
            population: $population,
            households: $households,
            rtBreakdown: $rtBreakdown,
        );
    }
}
