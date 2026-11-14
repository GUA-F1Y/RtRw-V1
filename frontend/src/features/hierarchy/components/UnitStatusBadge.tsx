import React from 'react';
import { Badge } from '../../../components/ui/Badge.tsx';

/**
 * Canonical unit status presentation mapping.
 * Only maps actual UnitStatus enum values from the backend: 'active' | 'inactive'.
 */
export type UnitStatusValue = 'active' | 'inactive';

export interface UnitStatusConfig {
  label: string;
  badgeVariant: 'emerald' | 'rose' | 'slate';
  dotColor: string;
  description: string;
}

const UNIT_STATUS_MAP: Record<UnitStatusValue, UnitStatusConfig> = {
  active: {
    label: 'Aktif',
    badgeVariant: 'emerald',
    dotColor: 'bg-emerald-400',
    description: 'Unit beroperasi secara normal',
  },
  inactive: {
    label: 'Tidak Aktif',
    badgeVariant: 'rose',
    dotColor: 'bg-rose-400',
    description: 'Unit sementara tidak beroperasi',
  },
};

interface UnitStatusBadgeProps {
  status: string; // string so unknown values degrade gracefully
}

export const UnitStatusBadge: React.FC<UnitStatusBadgeProps> = ({ status }) => {
  const config = UNIT_STATUS_MAP[status as UnitStatusValue];

  if (!config) {
    // Unknown status from backend — degrade gracefully, do not throw
    return (
      <Badge variant="slate" size="sm">
        {status}
      </Badge>
    );
  }

  return (
    <span className="inline-flex items-center gap-1.5">
      <span className={`w-1.5 h-1.5 rounded-full ${config.dotColor}`} aria-hidden="true" />
      <Badge variant={config.badgeVariant} size="sm">
        {config.label}
      </Badge>
    </span>
  );
};
