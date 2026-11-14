import { Badge } from '../../../components/ui/Badge.tsx';
import type { BudgetPlanStatus } from '../../../types/finance.ts';

interface BudgetStatusBadgeProps {
  status: BudgetPlanStatus;
  label?: string;
  size?: 'sm' | 'md';
}

export const BudgetStatusBadge: React.FC<BudgetStatusBadgeProps> = ({
  status,
  label,
  size = 'sm',
}) => {
  switch (status) {
    case 'PUBLISHED':
      return (
        <Badge variant="emerald" size={size}>
          <span className="flex items-center gap-1.5">
            <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
            {label || 'Diterbitkan'}
          </span>
        </Badge>
      );
    case 'DRAFT':
      return (
        <Badge variant="amber" size={size}>
          {label || 'Konsep (Draft)'}
        </Badge>
      );
    case 'SUPERSEDED':
      return (
        <Badge variant="slate" size={size}>
          {label || 'Digantikan (Superseded)'}
        </Badge>
      );
    case 'ARCHIVED':
      return (
        <Badge variant="slate" size={size}>
          {label || 'Diarsipkan'}
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
