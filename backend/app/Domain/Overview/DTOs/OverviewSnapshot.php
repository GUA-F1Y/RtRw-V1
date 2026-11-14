<?php

declare(strict_types=1);

namespace App\Domain\Overview\DTOs;

final readonly class OverviewSnapshot
{
    /**
     * @param  list<RtOverviewMetricDto>  $rtBreakdown
     */
    public function __construct(
        public OverviewScopeDto $scope,
        public PopulationMetricsDto $population,
        public HouseholdMetricsDto $households,
        public array $rtBreakdown,
    ) {}
}
