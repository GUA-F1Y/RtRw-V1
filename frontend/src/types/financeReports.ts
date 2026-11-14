/**
 * Types and interfaces for Phase 23 Finance Reports, Periodic Recap, and CSV Export.
 */

export interface ReportPeriodFilter {
  date_from?: string;
  date_to?: string;
  cash_account_id?: string;
  rt_id?: string;
  rw_id?: string;
  interval?: 'daily' | 'monthly';
  type?: 'INCOME' | 'EXPENSE';
}

export interface ReportSummaryData {
  period: {
    date_from: string;
    date_to: string;
  };
  opening_balance: number;
  posted_income: number;
  reversed_income: number;
  effective_income: number;
  posted_expense: number;
  reversed_expense: number;
  effective_expense: number;
  net_movement: number;
  closing_balance: number;
  total_transactions_count: number;
  reversed_transactions_count: number;
  effective_transactions_count: number;
  cash_account_count: number;
}

export interface CashFlowBucket {
  period: string;
  label: string;
  posted_income: number;
  reversed_income: number;
  effective_income: number;
  posted_expense: number;
  reversed_expense: number;
  effective_expense: number;
  net_movement: number;
  transaction_count: number;
}

export interface CashFlowTrendData {
  interval: 'daily' | 'monthly';
  buckets: CashFlowBucket[];
}

export interface CategoryBreakdownItem {
  category_id: string;
  name: string;
  code: string | null;
  type: 'INCOME' | 'EXPENSE';
  is_active: boolean;
  posted_amount: number;
  reversed_amount: number;
  effective_amount: number;
  percentage: number;
  transaction_count: number;
}

export interface CategoryBreakdownData {
  categories: CategoryBreakdownItem[];
  summary: {
    total_effective_income: number;
    total_effective_expense: number;
  };
}

export interface RtBreakdownItem {
  rt_id: string | null;
  rt_number: string | null;
  label: string;
  scope_type: 'RW' | 'RT';
  account_count: number;
  opening_balance: number;
  effective_income: number;
  effective_expense: number;
  net_movement: number;
  closing_balance: number;
}

export interface RtBreakdownData {
  items: RtBreakdownItem[];
  summary: {
    total_accounts: number;
    total_opening_balance: number;
    total_effective_income: number;
    total_effective_expense: number;
    total_net_movement: number;
    total_closing_balance: number;
  };
}

export interface AccountComparisonItem {
  account_id: string;
  name: string;
  account_number: string | null;
  scope_type: 'RW' | 'RT';
  rt_id: string | null;
  rt_number: string | null;
  status: string;
  opening_balance: number;
  effective_income: number;
  effective_expense: number;
  net_movement: number;
  closing_balance: number;
  transaction_count: number;
}

export interface AccountComparisonData {
  accounts: AccountComparisonItem[];
  summary: {
    total_accounts: number;
    total_opening_balance: number;
    total_effective_income: number;
    total_effective_expense: number;
    total_net_movement: number;
    total_closing_balance: number;
    total_transactions: number;
  };
}
