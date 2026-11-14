import React from 'react';
import type { ResidentStatus } from '../../../types/resident.ts';
import { RESIDENT_STATUS_MAP } from './residentConstants.ts';

// Re-export for backward compatibility (consumers importing from this file still work)
export { RESIDENT_STATUS_MAP };


interface ResidentStatusBadgeProps {
  status: ResidentStatus;
  size?: 'sm' | 'md';
  showDot?: boolean;
}

export const ResidentStatusBadge: React.FC<ResidentStatusBadgeProps> = ({
  status,
  size = 'sm',
  showDot = true,
}) => {
  const config = RESIDENT_STATUS_MAP[status] ?? RESIDENT_STATUS_MAP.INACTIVE;
  const sizeClasses = size === 'sm' ? 'text-xs px-2 py-0.5' : 'text-sm px-2.5 py-1';
  const dotSize = size === 'sm' ? 'w-1.5 h-1.5' : 'w-2 h-2';

  return (
    <span
      className={`inline-flex items-center gap-1.5 font-medium rounded-full border ${config.bg} ${config.text} ${config.border} ${sizeClasses}`}
      aria-label={`Status: ${config.label}`}
      title={config.label}
    >
      {showDot && (
        <span className={`${dotSize} rounded-full flex-shrink-0 ${config.dot}`} aria-hidden="true" />
      )}
      {config.label}
    </span>
  );
};
