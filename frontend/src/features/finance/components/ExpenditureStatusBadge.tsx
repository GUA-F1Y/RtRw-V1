import { Badge } from '../../../components/ui/Badge.tsx';
import type { ExpenditureRequestStatus } from '../../../types/finance.ts';

interface ExpenditureStatusBadgeProps {
  status: ExpenditureRequestStatus;
  label?: string;
  size?: 'sm' | 'md';
}

export const ExpenditureStatusBadge: React.FC<ExpenditureStatusBadgeProps> = ({
  status,
  label,
  size = 'sm',
}) => {
  switch (status) {
    case 'POSTED':
      return (
        <Badge variant="emerald" size={size}>
          <span className="flex items-center gap-1.5">
            <svg className="w-3 h-3 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" />
            </svg>
            {label || 'Tercatat di Kas (Posted)'}
          </span>
        </Badge>
      );
    case 'APPROVED':
      return (
        <Badge variant="blue" size={size}>
          <span className="flex items-center gap-1.5">
            <span className="w-1.5 h-1.5 rounded-full bg-blue-400" />
            {label || 'Disetujui'}
          </span>
        </Badge>
      );
    case 'SUBMITTED':
      return (
        <Badge variant="amber" size={size}>
          <span className="flex items-center gap-1.5">
            <span className="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse" />
            {label || 'Menunggu Persetujuan'}
          </span>
        </Badge>
      );
    case 'DRAFT':
      return (
        <Badge variant="slate" size={size}>
          {label || 'Konsep'}
        </Badge>
      );
    case 'REJECTED':
      return (
        <Badge variant="rose" size={size}>
          {label || 'Ditolak'}
        </Badge>
      );
    case 'CANCELLED':
      return (
        <Badge variant="slate" size={size}>
          {label || 'Dibatalkan'}
        </Badge>
      );
    default:
      return (
        <Badge variant="slate" size={size}>
          {label || status}
        </Badge>
      );
  }
};
