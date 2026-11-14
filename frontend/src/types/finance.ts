export type CashAccountStatus = 'ACTIVE' | 'CLOSED';

export type TransactionType = 'INCOME' | 'EXPENSE';

export interface CashAccountScope {
  level: 'organization' | 'rw' | 'rt';
  rw_id: string | null;
  rt_id: string | null;
  rw_number: number | null;
  rt_number: number | null;
  label: string;
}

export interface CashAccount {
  id: string;
  organization_id: string;
  rw_id: string;
  rt_id: string | null;
  is_rt_account?: boolean;
  name: string;
  account_number: string | null;
  description: string | null;
  opening_balance: number;
  current_balance: number;
  status: CashAccountStatus;
  status_label?: string;
  is_active?: boolean;
  is_closed?: boolean;
  closed_at: string | null;
  closed_reason?: string | null;
  close_reason?: string | null;
  scope?: CashAccountScope;
  rw?: {
    id: string;
    rw_number?: number;
    name?: string;
  } | null;
  rt?: {
    id: string;
    rt_number?: number;
    name?: string;
  } | null;
  created_at: string;
  updated_at: string;
}

export interface AccountSummaryBreakdown {
  opening_balance: number;
  posted_income: number;
  posted_expense: number;
  reversed_income: number;
  reversed_expense: number;
  net_income: number;
  net_expense: number;
  current_balance: number;
  total_transactions_count: number;
  reversed_transactions_count: number;
}

export interface AccountSummary {
  account: CashAccount;
  summary: AccountSummaryBreakdown;
  opening_balance: number;
  current_balance: number;
  total_income: number;
  total_expense: number;
  active_income: number;
  active_expense: number;
  reversed_income: number;
  reversed_expense: number;
  transactions_count: number;
}

export interface FinanceCategory {
  id: string;
  organization_id: string;
  rw_id: string | null;
  rt_id: string | null;
  name: string;
  type: TransactionType;
  code: string | null;
  description: string | null;
  is_active: boolean;
  scope_level: 'organization' | 'rw' | 'rt';
  created_at: string;
}

export interface TransactionRecorder {
  id: string;
  name: string;
  email: string;
}

export interface TransactionReversalInfo {
  id: string;
  original_transaction_id: string;
  amount: number;
  transaction_date: string;
  reversal_reason: string;
  created_at: string;
}

export interface OriginalTransactionInfo {
  id: string;
  type: TransactionType;
  amount: number;
  transaction_date: string;
  description: string;
}

export interface EvidenceFileSummary {
  id: string;
  original_name: string;
  extension?: string;
  mime_type: string;
  size_bytes: number;
}

export interface FinancialTransaction {
  id: string;
  organization_id?: string;
  rw_id?: string;
  rt_id?: string | null;
  cash_account_id: string;
  category_id: string;
  type: TransactionType;
  type_label?: string;
  amount: number;
  transaction_date: string;
  description: string;
  reference_number: string | null;
  evidence_file_id: string | null;
  recorded_by: string;
  is_reversal: boolean;
  original_transaction_id: string | null;
  reversal_reason: string | null;
  created_at: string;
  is_reversed?: boolean;
  reversal_transaction_id?: string | null;
  reversal?: TransactionReversalInfo | null;
  original_transaction?: OriginalTransactionInfo | null;
  cash_account?: {
    id: string;
    name: string;
    status: CashAccountStatus;
  };
  account?: {
    id: string;
    name: string;
    status: CashAccountStatus;
  };
  category?: {
    id: string;
    name: string;
    type: TransactionType;
    code?: string | null;
  };
  recorder?: TransactionRecorder;
  evidence_file?: EvidenceFileSummary | null;
  evidence_files?: EvidenceFileSummary[];
}

// Request Payloads

export interface CreateCashAccountPayload {
  name: string;
  rt_id?: string | null;
  account_number?: string | null;
  description?: string | null;
  opening_balance?: number;
}

export interface UpdateCashAccountPayload {
  name?: string;
  account_number?: string | null;
  description?: string | null;
}

export interface CloseCashAccountPayload {
  reason: string;
}

export interface CreateFinanceCategoryPayload {
  name: string;
  type: TransactionType;
  rt_id?: string | null;
  code?: string | null;
  description?: string | null;
}

export interface UpdateFinanceCategoryPayload {
  name?: string;
  code?: string | null;
  description?: string | null;
}

export interface RecordTransactionPayload {
  cash_account_id: string;
  category_id: string;
  type: TransactionType;
  amount: number;
  transaction_date: string;
  description: string;
  reference_number?: string | null;
  evidence_file_id?: string | null;
}

export interface ReverseTransactionPayload {
  reversal_reason: string;
  transaction_date?: string | null;
}

// Query Parameters

export interface CashAccountQueryParams {
  status?: CashAccountStatus | string;
  rt_id?: string;
  rw_id?: string;
  q?: string;
  page?: number;
  per_page?: number;
  sort?: string;
}

export interface FinanceCategoryQueryParams {
  type?: TransactionType | string;
  is_active?: boolean;
  q?: string;
  page?: number;
  per_page?: number;
  sort?: string;
}

export interface FinancialTransactionQueryParams {
  cash_account_id?: string;
  category_id?: string;
  type?: TransactionType | string;
  is_reversal?: boolean;
  date_from?: string;
  date_to?: string;
  rt_id?: string;
  rw_id?: string;
  q?: string;
  page?: number;
  per_page?: number;
  sort?: string;
}

// Period Closure & Reconciliation (Phase 25)

export interface FinancialPeriodClosure {
  id: string;
  organization_id: string;
  rw_id: string;
  rt_id: string | null;
  cash_account_id: string;
  account?: CashAccount | null;
  period_year: number;
  period_month: number;
  period_start: string;
  period_end: string;
  opening_balance: number;
  closing_balance: number;
  total_income: number;
  total_expense: number;
  posted_income: number;
  reversed_income: number;
  posted_expense: number;
  reversed_expense: number;
  transfers_in?: number;
  transfers_out?: number;
  net_transfer_movement?: number;
  transfer_count?: number;
  net_movement: number;
  transaction_count: number;
  reversal_count: number;
  counted_cash_balance: number | null;
  reconciliation_difference: number | null;
  reconciliation_notes: string | null;
  is_reconciled: boolean;
  has_discrepancy: boolean;
  closed_by: {
    id: string;
    name?: string;
    email?: string;
  };
  closed_at: string;
  created_at: string;
}

export interface PeriodClosurePreview {
  account_id: string;
  account_name: string;
  period_year: number;
  period_month: number;
  period_start: string;
  period_end: string;
  opening_balance: number;
  closing_balance: number;
  total_income: number;
  total_expense: number;
  posted_income: number;
  reversed_income: number;
  effective_income: number;
  posted_expense: number;
  reversed_expense: number;
  effective_expense: number;
  transfers_in?: number;
  transfers_out?: number;
  net_transfer_movement?: number;
  transfer_count?: number;
  net_movement: number;
  total_transactions_count: number;
  reversed_transactions_count: number;
  effective_transactions_count: number;
  is_already_closed: boolean;
  is_completed_month: boolean;
  is_eligible_for_closure: boolean;
  existing_closure_id: string | null;
}

export interface ClosePeriodPayload {
  counted_cash_balance?: number | null;
  reconciliation_notes?: string | null;
}

export interface PeriodClosureQueryParams {
  cash_account_id?: string;
  period_year?: number;
  period_month?: number;
  rw_id?: string;
  rt_id?: string | null;
  page?: number;
  per_page?: number;
  sort?: string;
}

export interface CashTransferAccountInfo {
  id: string;
  name: string;
  account_number: string | null;
  scope_type: 'RW' | 'RT';
  rt_id: string | null;
  rt_number: string | null;
  status: CashAccountStatus;
}

export interface CashTransfer {
  id: string;
  organization_id?: string;
  rw_id?: string;
  rt_id?: string | null;
  source_cash_account_id: string;
  destination_cash_account_id: string;
  amount: number;
  transfer_date: string;
  description: string;
  reference_number: string | null;
  evidence_file_id: string | null;
  recorded_by: string;
  is_reversal: boolean;
  original_transfer_id: string | null;
  reversal_reason: string | null;
  created_at: string;
  is_reversed?: boolean;
  source_account?: CashTransferAccountInfo | null;
  destination_account?: CashTransferAccountInfo | null;
  recorder?: TransactionRecorder | null;
  reversal?: {
    id: string;
    amount: number;
    transfer_date: string;
    reversal_reason: string;
    created_at: string;
  } | null;
  original_transfer?: {
    id: string;
    amount: number;
    transfer_date: string;
    description: string;
  } | null;
  evidence_file?: EvidenceFileSummary | null;
}

export interface RecordTransferPayload {
  source_cash_account_id: string;
  destination_cash_account_id: string;
  amount: number;
  transfer_date: string;
  description: string;
  reference_number?: string | null;
  evidence_file_id?: string | null;
}

export interface ReverseTransferPayload {
  reversal_reason: string;
  transfer_date?: string | null;
}

export interface TransferQueryParams {
  cash_account_id?: string;
  source_cash_account_id?: string;
  destination_cash_account_id?: string;
  is_reversal?: boolean;
  date_from?: string;
  date_to?: string;
  rw_id?: string;
  rt_id?: string | null;
  q?: string;
  page?: number;
  per_page?: number;
  sort?: string;
}

// -----------------------------------------------------------------------------
// Phase 27: Budget Planning & Expenditure Approvals
// -----------------------------------------------------------------------------

export type BudgetPlanStatus = 'DRAFT' | 'PUBLISHED' | 'SUPERSEDED' | 'ARCHIVED';

export type ExpenditureRequestStatus =
  | 'DRAFT'
  | 'SUBMITTED'
  | 'APPROVED'
  | 'REJECTED'
  | 'CANCELLED'
  | 'POSTED';

export interface BudgetLine {
  id: string;
  budget_plan_id: string;
  category_id: string;
  planned_amount: number;
  created_at: string;
  updated_at: string;
  effective_actual_expenses?: number;
  posted_expenses?: number;
  reversed_expenses?: number;
  outstanding_commitments?: number;
  remaining_budget?: number;
  variance_amount?: number;
  utilization_percentage?: number;
  request_counts?: {
    pending: number;
    approved: number;
    rejected: number;
    posted: number;
    total: number;
  };
  category?: {
    id: string;
    name: string;
    code: string;
    type: TransactionType;
  } | null;
}

export interface BudgetPlanSummary {
  total_planned: number;
  total_actual: number;
  total_commitments: number;
  total_remaining: number;
  total_variance: number;
  overall_utilization_percentage: number;
  lines_count: number;
}

export interface BudgetPlan {
  id: string;
  organization_id: string;
  rw_id: string;
  rt_id: string | null;
  scope_type: 'RW' | 'RT';
  year: number;
  month: number;
  version: number;
  status: BudgetPlanStatus;
  status_label?: string;
  notes: string | null;
  created_by: string;
  published_by: string | null;
  published_at: string | null;
  created_at: string;
  updated_at: string;
  total_planned_amount?: number;
  calculation?: {
    total_actual_expense: number;
    total_approved_commitment: number;
    total_available_budget: number;
    lines?: Record<string, {
      planned_amount: number;
      effective_actual_expenses: number;
      outstanding_commitments: number;
      remaining_budget: number;
    }>;
  };
  creator?: { id: string; name: string; email: string } | null;
  publisher?: { id: string; name: string; email: string } | null;
  rw?: { id: string; rw_number?: number; number?: number; name?: string } | null;
  rt?: { id: string; rt_number?: number; number?: number; name?: string } | null;
  lines?: BudgetLine[];
  summary?: BudgetPlanSummary;
}

export interface ExpenditureRequest {
  id: string;
  organization_id: string;
  rw_id: string;
  rt_id: string | null;
  scope_type: 'RW' | 'RT';
  budget_line_id: string;
  cash_account_id: string;
  category_id: string;
  title: string;
  description: string | null;
  proposed_amount: number;
  amount?: number;
  approved_amount: number | null;
  actual_amount: number | null;
  planned_date: string;
  reference_number: string | null;
  request_number?: string | null;
  vendor_name?: string | null;
  vendor_bank_account?: string | null;
  urgency?: string;
  evidence_file_id: string | null;
  status: ExpenditureRequestStatus;
  status_label?: string;
  requested_by: string;
  requester_user_id?: string;
  submitted_at: string | null;
  approved_by: string | null;
  decision_at: string | null;
  decided_at?: string | null;
  decision_reason: string | null;
  cancellation_reason?: string | null;
  financial_transaction_id: string | null;
  posted_by: string | null;
  posted_at: string | null;
  created_at: string;
  updated_at: string;
  budget_line?: {
    id: string;
    planned_amount: number;
    category_id: string;
    category?: { id: string; name: string; code: string } | null;
    plan?: { id: string; year: number; month: number; version: number; status: BudgetPlanStatus } | null;
    budget_plan?: { id: string; year: number; month: number; version: number; status: BudgetPlanStatus } | null;
  } | null;
  cash_account?: {
    id: string;
    name: string;
    account_number: string | null;
    status: CashAccountStatus;
    current_balance: number;
  } | null;
  category?: {
    id: string;
    name: string;
    code: string;
    type: TransactionType;
  } | null;
  requester?: { id: string; name: string; email: string } | null;
  approver?: { id: string; name: string; email: string } | null;
  poster?: { id: string; name: string; email: string } | null;
  financial_transaction?: {
    id: string;
    amount: number;
    transaction_date: string;
    reference_number: string | null;
    description: string | null;
  } | null;
  evidence_file?: EvidenceFileSummary | null;
  rw?: { id: string; rw_number?: number; number?: number; name?: string } | null;
  rt?: { id: string; rt_number?: number; number?: number; name?: string } | null;
}

export interface CreateBudgetPlanPayload {
  year: number;
  month: number;
  rt_id?: string | null;
  notes?: string | null;
  lines: Array<{
    category_id: string;
    planned_amount: number;
  }>;
}

export interface UpdateBudgetPlanPayload {
  notes?: string | null;
  lines: Array<{
    category_id: string;
    planned_amount: number;
  }>;
}

export interface PublishBudgetPlanPayload {
  confirmation: true;
}

export interface AmendBudgetPlanPayload {
  notes?: string | null;
}

export interface CreateExpenditureRequestPayload {
  budget_line_id: string;
  cash_account_id: string;
  proposed_amount: number;
  planned_date: string;
  title: string;
  description?: string | null;
  reference_number?: string | null;
  evidence_file_id?: string | null;
  submit?: boolean;
}

export interface ApproveExpenditurePayload {
  approved_amount?: number | null;
  decision_reason?: string | null;
}

export interface RejectExpenditurePayload {
  decision_reason?: string | null;
}

export interface CancelExpenditurePayload {
  cancellation_reason?: string | null;
  reason?: string | null;
}

export interface PostExpenditurePayload {
  actual_amount?: number | null;
  transaction_date?: string | null;
  cash_account_id?: string | null;
  reference_number?: string | null;
  evidence_file_id?: string | null;
}

export interface BudgetPlanFilterParams {
  year?: number;
  month?: number;
  status?: BudgetPlanStatus;
  version?: number;
  scope_type?: string;
  rw_id?: string;
  rt_id?: string | null;
  page?: number;
  per_page?: number;
  sort?: string;
}

export interface ExpenditureRequestFilterParams {
  budget_line_id?: string;
  cash_account_id?: string;
  category_id?: string;
  status?: ExpenditureRequestStatus;
  planned_from?: string;
  planned_to?: string;
  start_date?: string;
  end_date?: string;
  rw_id?: string;
  rt_id?: string | null;
  q?: string;
  page?: number;
  per_page?: number;
  sort?: string;
}

export interface BudgetUtilizationFilterParams {
  year?: number;
  month?: number;
  rw_id?: string;
  rt_id?: string | null;
}

export interface BudgetUtilizationSummaryResult {
  summary: {
    total_planned: number;
    total_actual: number;
    total_commitments: number;
    total_remaining: number;
    total_variance: number;
    overall_utilization_percentage: number;
    plans_count: number;
  };
  plans: Array<{
    plan: BudgetPlan;
    metrics: {
      total_planned: number;
      total_actual: number;
      total_commitments: number;
      total_remaining: number;
      total_variance: number;
      utilization_percentage: number;
    };
    lines: BudgetLine[];
  }>;
}

