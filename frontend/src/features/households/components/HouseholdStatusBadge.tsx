import React from 'react';
import type { HouseholdStatus } from '../../../types/household.ts';
import { HOUSEHOLD_STATUS_MAP } from './householdConstants.ts';

export { HOUSEHOLD_STATUS_MAP };

interface HouseholdStatusBadgeProps {
  status: HouseholdStatus;
  size?: 'sm' | 'md';
}

export const HouseholdStatusBadge: React.FC<HouseholdStatusBadgeProps> = ({
  status,
  size = 'sm',
}) => {
  const config = HOUSEHOLD_STATUS_MAP[status] ?? HOUSEHOLD_STATUS_MAP.ARCHIVED;
  const sizeClasses = size === 'sm' ? 'text-xs px-2 py-0.5' : 'text-sm px-2.5 py-1';
  const dotSize = size === 'sm' ? 'w-1.5 h-1.5' : 'w-2 h-2';

  return (
    <span
      className={`inline-flex items-center gap-1.5 font-medium rounded-full border ${config.bg} ${config.text} ${config.border} ${sizeClasses}`}
      aria-label={`Status: ${config.label}`}
      title={config.label}
    >
      <span className={`${dotSize} rounded-full flex-shrink-0 ${config.dot}`} aria-hidden="true" />
      {config.label}
    </span>
  );
};
