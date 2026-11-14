export type OverviewScopeLevel = 'ORGANIZATION' | 'RW' | 'RT' | 'organization' | 'rw' | 'rt';

export interface OverviewScope {
  level: OverviewScopeLevel | string;
  id: string;
  name: string;
}

export interface OverviewPopulationMetrics {
  total: number;
  active: number;
  prospective: number;
  inactive: number;
  moved: number;
  deceased: number;
}

export interface OverviewHouseholdMetrics {
  active: number;
  head_assigned: number;
  head_vacant: number;
  moved: number;
  dissolved: number;
}

export interface RtOverviewMetric {
  rt_id: string;
  rt_code: string;
  resident_count: number;
  household_count: number;
  head_vacant_household_count: number;
}

export interface Overview {
  scope: OverviewScope;
  population: OverviewPopulationMetrics;
  households: OverviewHouseholdMetrics;
  rt_breakdown: RtOverviewMetric[];
}
