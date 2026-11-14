import { API_BASE_URL, apiClient, getAuthToken } from '../../../lib/api/client.ts';
import { parseApiError } from '../../../lib/api/errors.ts';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import { buildQueryString } from '../../../lib/api/queryParams.ts';
import type { ApiSuccessResponse } from '../../../lib/api/types.ts';
import type {
  AccountSummary,
  AmendBudgetPlanPayload,
  ApproveExpenditurePayload,
  BudgetPlan,
  BudgetPlanFilterParams,
  BudgetUtilizationFilterParams,
  BudgetUtilizationSummaryResult,
  CancelExpenditurePayload,
  CashAccount,
  CashAccountQueryParams,
  CashTransfer,
  CloseCashAccountPayload,
  ClosePeriodPayload,
  CreateBudgetPlanPayload,
  CreateCashAccountPayload,
  CreateExpenditureRequestPayload,
  CreateFinanceCategoryPayload,
  ExpenditureRequest,
  ExpenditureRequestFilterParams,
  FinanceCategory,
  FinanceCategoryQueryParams,
  FinancialPeriodClosure,
  FinancialTransaction,
  FinancialTransactionQueryParams,
  PeriodClosurePreview,
  PeriodClosureQueryParams,
  PostExpenditurePayload,
  PublishBudgetPlanPayload,
  RecordTransactionPayload,
  RecordTransferPayload,
  RejectExpenditurePayload,
  ReverseTransactionPayload,
  ReverseTransferPayload,
  TransferQueryParams,
  UpdateBudgetPlanPayload,
  UpdateCashAccountPayload,
  UpdateFinanceCategoryPayload,
} from '../../../types/finance.ts';
import type {
  AccountComparisonData,
  CashFlowTrendData,
  CategoryBreakdownData,
  ReportPeriodFilter,
  ReportSummaryData,
  RtBreakdownData,
} from '../../../types/financeReports.ts';

export interface FinanceRequestOptions {
  signal?: AbortSignal;
  idempotencyKey?: string;
}

export const financeApi = {
  // --- Cash Accounts ---

  /**
   * List cash accounts within caller's authorized scope with server-side pagination & filters.
   * GET /api/v1/finance/accounts
   */
  listAccounts: async (
    params?: CashAccountQueryParams,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<CashAccount[]>> => {
    return apiClient.get<CashAccount[]>('finance/accounts', {
      params: params as Record<string, unknown>,
      signal: options?.signal,
    });
  },

  /**
   * Get cash account detail by ID.
   * GET /api/v1/finance/accounts/{id}
   */
  getAccountById: async (
    id: string,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<CashAccount>> => {
    return apiClient.get<CashAccount>(`finance/accounts/${encodeURIComponent(id)}`, {
      signal: options?.signal,
    });
  },

  /**
   * Get financial balance summary breakdown for a cash account.
   * GET /api/v1/finance/accounts/{id}/summary
   */
  getAccountSummary: async (
    id: string,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<AccountSummary>> => {
    return apiClient.get<AccountSummary>(`finance/accounts/${encodeURIComponent(id)}/summary`, {
      signal: options?.signal,
    });
  },

  /**
   * Create a new cash account.
   * POST /api/v1/finance/accounts
   * Requires Idempotency-Key
   */
  createAccount: async (
    payload: CreateCashAccountPayload,
    options?: FinanceRequestOptions
  ): Promise<ApiSuccessResponse<CashAccount>> => {
    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.post<CashAccount>('finance/accounts', payload, {
      idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Update editable cash account details (name, account_number, description).
   * PATCH /api/v1/finance/accounts/{id}
   */
  updateAccount: async (
    id: string,
    payload: UpdateCashAccountPayload,
    options?: FinanceRequestOptions
  ): Promise<ApiSuccessResponse<CashAccount>> => {
    return apiClient.patch<CashAccount>(`finance/accounts/${encodeURIComponent(id)}`, payload, {
      idempotencyKey: options?.idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Close a cash account with a mandatory audit reason.
   * POST /api/v1/finance/accounts/{id}/close
   */
  closeAccount: async (
    id: string,
    payload: CloseCashAccountPayload,
    options?: FinanceRequestOptions
  ): Promise<ApiSuccessResponse<CashAccount>> => {
    return apiClient.post<CashAccount>(`finance/accounts/${encodeURIComponent(id)}/close`, payload, {
      idempotencyKey: options?.idempotencyKey,
      signal: options?.signal,
    });
  },

  // --- Financial Categories ---

  /**
   * List financial categories in caller's organization and scope.
   * GET /api/v1/finance/categories
   */
  listCategories: async (
    params?: FinanceCategoryQueryParams,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<FinanceCategory[]>> => {
    return apiClient.get<FinanceCategory[]>('finance/categories', {
      params: params as Record<string, unknown>,
      signal: options?.signal,
    });
  },

  /**
   * Get finance category detail by ID.
   * GET /api/v1/finance/categories/{id}
   */
  getCategoryById: async (
    id: string,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<FinanceCategory>> => {
    return apiClient.get<FinanceCategory>(`finance/categories/${encodeURIComponent(id)}`, {
      signal: options?.signal,
    });
  },

  /**
   * Create a new finance category.
   * POST /api/v1/finance/categories
   */
  createCategory: async (
    payload: CreateFinanceCategoryPayload,
    options?: FinanceRequestOptions
  ): Promise<ApiSuccessResponse<FinanceCategory>> => {
    return apiClient.post<FinanceCategory>('finance/categories', payload, {
      idempotencyKey: options?.idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Update category details.
   * PATCH /api/v1/finance/categories/{id}
   */
  updateCategory: async (
    id: string,
    payload: UpdateFinanceCategoryPayload,
    options?: FinanceRequestOptions
  ): Promise<ApiSuccessResponse<FinanceCategory>> => {
    return apiClient.patch<FinanceCategory>(`finance/categories/${encodeURIComponent(id)}`, payload, {
      idempotencyKey: options?.idempotencyKey,
      signal: options?.signal,
    });
  },

  // --- Financial Transactions & Ledger ---

  /**
   * List financial transactions across authorized accounts or filtered by account.
   * GET /api/v1/finance/transactions
   */
  listTransactions: async (
    params?: FinancialTransactionQueryParams,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<FinancialTransaction[]>> => {
    return apiClient.get<FinancialTransaction[]>('finance/transactions', {
      params: params as Record<string, unknown>,
      signal: options?.signal,
    });
  },

  /**
   * Get single transaction detail with reversal relationships.
   * GET /api/v1/finance/transactions/{id}
   */
  getTransactionById: async (
    id: string,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<FinancialTransaction>> => {
    return apiClient.get<FinancialTransaction>(`finance/transactions/${encodeURIComponent(id)}`, {
      signal: options?.signal,
    });
  },

  /**
   * Record a new INCOME or EXPENSE ledger entry.
   * POST /api/v1/finance/transactions
   * Requires Idempotency-Key
   */
  recordTransaction: async (
    payload: RecordTransactionPayload,
    options?: FinanceRequestOptions
  ): Promise<ApiSuccessResponse<FinancialTransaction>> => {
    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.post<FinancialTransaction>('finance/transactions', payload, {
      idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Reverse an existing posted transaction through compensating ledger entry.
   * POST /api/v1/finance/transactions/{id}/reverse
   * Requires Idempotency-Key
   */
  reverseTransaction: async (
    id: string,
    payload: ReverseTransactionPayload,
    options?: FinanceRequestOptions
  ): Promise<ApiSuccessResponse<FinancialTransaction>> => {
    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.post<FinancialTransaction>(
      `finance/transactions/${encodeURIComponent(id)}/reverse`,
      payload,
      {
        idempotencyKey,
        signal: options?.signal,
      }
    );
  },

  // --- Reports & Analytics ---

  /**
   * Period summary metrics: opening/closing balances, effective income/expense, transaction counts.
   * GET /api/v1/finance/reports/summary
   */
  getReportSummary: async (
    params?: ReportPeriodFilter,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<ReportSummaryData>> => {
    return apiClient.get<ReportSummaryData>('finance/reports/summary', {
      params: params as Record<string, unknown>,
      signal: options?.signal,
    });
  },

  /**
   * Cash flow trend buckets (daily or monthly aggregation).
   * GET /api/v1/finance/reports/cash-flow
   */
  getReportCashFlow: async (
    params?: ReportPeriodFilter,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<CashFlowTrendData>> => {
    return apiClient.get<CashFlowTrendData>('finance/reports/cash-flow', {
      params: params as Record<string, unknown>,
      signal: options?.signal,
    });
  },

  /**
   * Category breakdown grouped by pos category with directional percentages.
   * GET /api/v1/finance/reports/categories
   */
  getReportCategories: async (
    params?: ReportPeriodFilter,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<CategoryBreakdownData>> => {
    return apiClient.get<CategoryBreakdownData>('finance/reports/categories', {
      params: params as Record<string, unknown>,
      signal: options?.signal,
    });
  },

  /**
   * RT breakdown for authorized RW scope (RT units vs RW-level accounts).
   * GET /api/v1/finance/reports/rt-breakdown
   */
  getReportRtBreakdown: async (
    params?: ReportPeriodFilter,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<RtBreakdownData>> => {
    return apiClient.get<RtBreakdownData>('finance/reports/rt-breakdown', {
      params: params as Record<string, unknown>,
      signal: options?.signal,
    });
  },

  /**
   * Side-by-side performance comparison across permitted cash accounts.
   * GET /api/v1/finance/reports/accounts
   */
  getReportAccounts: async (
    params?: ReportPeriodFilter,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<AccountComparisonData>> => {
    return apiClient.get<AccountComparisonData>('finance/reports/accounts', {
      params: params as Record<string, unknown>,
      signal: options?.signal,
    });
  },

  /**
   * Stream and download server-produced CSV export of filtered ledger transactions.
   * GET /api/v1/finance/reports/export
   */
  exportReportCsv: async (
    params?: ReportPeriodFilter,
    options?: { signal?: AbortSignal }
  ): Promise<{ blob: Blob; filename: string }> => {
    const queryString = buildQueryString(params as Record<string, unknown>);
    const url = `${API_BASE_URL}/finance/reports/export${queryString}`;
    const headers = new Headers();
    const token = getAuthToken();
    if (token) {
      headers.set('Authorization', `Bearer ${token}`);
    }

    const res = await fetch(url, { headers, signal: options?.signal });
    if (!res.ok) {
      const err = await parseApiError(res);
      throw err;
    }

    const contentDisposition = res.headers.get('content-disposition') || '';
    const filenameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
    const filename = filenameMatch ? filenameMatch[1] : 'laporan-keuangan.csv';
    const blob = await res.blob();
    return { blob, filename };
  },

  // --- Financial Documents & Evidence Workflow (Phase 24) ---

  /**
   * Generate or download official transaction document (Receipt / Voucher / Reversal).
   * GET /api/v1/finance/transactions/{id}/document
   */
  downloadTransactionDocument: async (
    id: string,
    options?: {
      format?: 'pdf' | 'html';
      disposition?: 'inline' | 'attachment';
      signal?: AbortSignal;
    }
  ): Promise<{ blob: Blob; filename: string }> => {
    const params: Record<string, string> = {};
    if (options?.format) params.format = options.format;
    if (options?.disposition) params.disposition = options.disposition;
    const queryString = buildQueryString(params);
    const url = `${API_BASE_URL}/finance/transactions/${encodeURIComponent(id)}/document${queryString}`;
    const headers = new Headers();
    const token = getAuthToken();
    if (token) {
      headers.set('Authorization', `Bearer ${token}`);
    }

    const res = await fetch(url, { headers, signal: options?.signal });
    if (!res.ok) {
      const err = await parseApiError(res);
      throw err;
    }

    const contentDisposition = res.headers.get('content-disposition') || '';
    const filenameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
    const filename = filenameMatch ? filenameMatch[1] : `dokumen-${id}.pdf`;
    const blob = await res.blob();
    return { blob, filename };
  },

  /**
   * List all evidence files associated with the transaction.
   * GET /api/v1/finance/transactions/{id}/evidence
   */
  listTransactionEvidence: async (
    id: string,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<import('../../../types/finance.ts').EvidenceFileSummary[]>> => {
    return apiClient.get<import('../../../types/finance.ts').EvidenceFileSummary[]>(
      `finance/transactions/${encodeURIComponent(id)}/evidence`,
      { signal: options?.signal }
    );
  },

  /**
   * Attach an evidence file or upload one directly to an existing posted transaction.
   * POST /api/v1/finance/transactions/{id}/evidence
   */
  attachTransactionEvidence: async (
    id: string,
    payload: { file_id?: string; file?: File },
    options?: FinanceRequestOptions
  ): Promise<ApiSuccessResponse<FinancialTransaction>> => {
    let body: unknown;
    if (payload.file) {
      const fd = new FormData();
      fd.append('file', payload.file);
      body = fd;
    } else {
      body = { file_id: payload.file_id };
    }

    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.post<FinancialTransaction>(
      `finance/transactions/${encodeURIComponent(id)}/evidence`,
      body,
      {
        idempotencyKey,
        signal: options?.signal,
      }
    );
  },

  /**
   * Download or preview evidence file associated with the transaction.
   * GET /api/v1/finance/transactions/{transactionId}/evidence/{fileId}/download
   */
  downloadTransactionEvidence: async (
    transactionId: string,
    fileId?: string,
    options?: { disposition?: 'inline' | 'attachment'; signal?: AbortSignal }
  ): Promise<{ blob: Blob; filename: string }> => {
    const params: Record<string, string> = {};
    if (options?.disposition) params.disposition = options.disposition;
    const queryString = buildQueryString(params);
    const endpoint = fileId
      ? `finance/transactions/${encodeURIComponent(transactionId)}/evidence/${encodeURIComponent(fileId)}/download`
      : `finance/transactions/${encodeURIComponent(transactionId)}/evidence/download`;
    const url = `${API_BASE_URL}/${endpoint}${queryString}`;
    const headers = new Headers();
    const token = getAuthToken();
    if (token) {
      headers.set('Authorization', `Bearer ${token}`);
    }

    const res = await fetch(url, { headers, signal: options?.signal });
    if (!res.ok) {
      const err = await parseApiError(res);
      throw err;
    }

    const contentDisposition = res.headers.get('content-disposition') || '';
    const filenameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
    const filename = filenameMatch ? filenameMatch[1] : 'bukti-transaksi';
    const blob = await res.blob();
    return { blob, filename };
  },

  // --- Financial Period Closures & Reconciliation (Phase 25) ---

  /**
   * List historical period closures with filters and pagination.
   * GET /api/v1/finance/period-closures
   */
  listPeriodClosures: async (
    params?: PeriodClosureQueryParams,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<FinancialPeriodClosure[]>> => {
    return apiClient.get<FinancialPeriodClosure[]>('finance/period-closures', {
      params: params as Record<string, unknown>,
      signal: options?.signal,
    });
  },

  /**
   * Get single closure detail by ID.
   * GET /api/v1/finance/period-closures/{id}
   */
  getPeriodClosureById: async (
    id: string,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<FinancialPeriodClosure>> => {
    return apiClient.get<FinancialPeriodClosure>(`finance/period-closures/${encodeURIComponent(id)}`, {
      signal: options?.signal,
    });
  },

  /**
   * Preview prospective ledger closing snapshot for an account and calendar month.
   * GET /api/v1/finance/accounts/{accountId}/periods/{year}/{month}/preview
   */
  previewPeriodClosure: async (
    accountId: string,
    year: number,
    month: number,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<PeriodClosurePreview>> => {
    return apiClient.get<PeriodClosurePreview>(
      `finance/accounts/${encodeURIComponent(accountId)}/periods/${year}/${month}/preview`,
      { signal: options?.signal }
    );
  },

  /**
   * Close a completed monthly period with optional physical cash reconciliation.
   * POST /api/v1/finance/accounts/{accountId}/periods/{year}/{month}/close
   * Requires Idempotency-Key
   */
  closePeriod: async (
    accountId: string,
    year: number,
    month: number,
    payload: ClosePeriodPayload,
    options?: FinanceRequestOptions
  ): Promise<ApiSuccessResponse<FinancialPeriodClosure>> => {
    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.post<FinancialPeriodClosure>(
      `finance/accounts/${encodeURIComponent(accountId)}/periods/${year}/${month}/close`,
      payload,
      {
        idempotencyKey,
        signal: options?.signal,
      }
    );
  },

  // --- Internal Cash Transfers (Phase 26) ---

  /**
   * List authorized cash transfers with pagination and whitelisted filters.
   * GET /api/v1/finance/transfers
   */
  listTransfers: async (
    params?: TransferQueryParams,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<CashTransfer[]>> => {
    return apiClient.get<CashTransfer[]>('finance/transfers', {
      params: params as Record<string, unknown>,
      signal: options?.signal,
    });
  },

  /**
   * Retrieve transfer detail by ID.
   * GET /api/v1/finance/transfers/{id}
   */
  getTransferById: async (
    id: string,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<CashTransfer>> => {
    return apiClient.get<CashTransfer>(`finance/transfers/${encodeURIComponent(id)}`, {
      signal: options?.signal,
    });
  },

  /**
   * Record a new internal cash transfer between two accounts.
   * POST /api/v1/finance/transfers
   * Requires Idempotency-Key
   */
  recordTransfer: async (
    payload: RecordTransferPayload,
    options?: FinanceRequestOptions
  ): Promise<ApiSuccessResponse<CashTransfer>> => {
    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.post<CashTransfer>('finance/transfers', payload, {
      idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Reverse an erroneous posted transfer by posting a compensating transfer.
   * POST /api/v1/finance/transfers/{id}/reverse
   * Requires Idempotency-Key
   */
  reverseTransfer: async (
    id: string,
    payload: ReverseTransferPayload,
    options?: FinanceRequestOptions
  ): Promise<ApiSuccessResponse<CashTransfer>> => {
    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.post<CashTransfer>(
      `finance/transfers/${encodeURIComponent(id)}/reverse`,
      payload,
      {
        idempotencyKey,
        signal: options?.signal,
      }
    );
  },

  // --- Phase 27: Budget Planning & Approvals ---

  /**
   * List budget plans within caller's scope.
   * GET /api/v1/finance/budgets
   */
  listBudgetPlans: async (
    params?: BudgetPlanFilterParams,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<BudgetPlan[]>> => {
    return apiClient.get<BudgetPlan[]>('finance/budgets', {
      params: params as Record<string, unknown>,
      signal: options?.signal,
    });
  },

  /**
   * Get single budget plan with lines and calculation summary.
   * GET /api/v1/finance/budgets/{id}
   */
  getBudgetPlan: async (
    id: string,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<BudgetPlan>> => {
    return apiClient.get<BudgetPlan>(`finance/budgets/${encodeURIComponent(id)}`, {
      signal: options?.signal,
    });
  },

  /**
   * Create a new draft budget plan.
   * POST /api/v1/finance/budgets
   * Requires Idempotency-Key
   */
  createBudgetPlan: async (
    payload: CreateBudgetPlanPayload,
    options?: FinanceRequestOptions
  ): Promise<ApiSuccessResponse<BudgetPlan>> => {
    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.post<BudgetPlan>('finance/budgets', payload, {
      idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Update a draft budget plan.
   * PUT /api/v1/finance/budgets/{id}
   * Requires Idempotency-Key
   */
  updateBudgetPlan: async (
    id: string,
    payload: UpdateBudgetPlanPayload,
    options?: FinanceRequestOptions
  ): Promise<ApiSuccessResponse<BudgetPlan>> => {
    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.put<BudgetPlan>(`finance/budgets/${encodeURIComponent(id)}`, payload, {
      idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Publish a draft budget plan (Ketua RW / Ketua RT only).
   * POST /api/v1/finance/budgets/{id}/publish
   * Requires Idempotency-Key
   */
  publishBudgetPlan: async (
    id: string,
    payload: PublishBudgetPlanPayload = { confirmation: true },
    options?: FinanceRequestOptions
  ): Promise<ApiSuccessResponse<BudgetPlan>> => {
    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.post<BudgetPlan>(`finance/budgets/${encodeURIComponent(id)}/publish`, payload, {
      idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Create an amendment (new version) from a published budget plan.
   * POST /api/v1/finance/budgets/{id}/amend
   * Requires Idempotency-Key
   */
  amendBudgetPlan: async (
    id: string,
    payload?: AmendBudgetPlanPayload,
    options?: FinanceRequestOptions
  ): Promise<ApiSuccessResponse<BudgetPlan>> => {
    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.post<BudgetPlan>(`finance/budgets/${encodeURIComponent(id)}/amend`, payload || {}, {
      idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Get budget utilization summary across plans.
   * GET /api/v1/finance/budgets/summary/utilization
   */
  getBudgetUtilizationSummary: async (
    params?: BudgetUtilizationFilterParams,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<BudgetUtilizationSummaryResult>> => {
    return apiClient.get<BudgetUtilizationSummaryResult>('finance/budgets/summary/utilization', {
      params: params as Record<string, unknown>,
      signal: options?.signal,
    });
  },

  // --- Phase 27: Expenditure Requests ---

  /**
   * List expenditure requests with filters.
   * GET /api/v1/finance/expenditures
   */
  listExpenditureRequests: async (
    params?: ExpenditureRequestFilterParams,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<ExpenditureRequest[]>> => {
    return apiClient.get<ExpenditureRequest[]>('finance/expenditures', {
      params: params as Record<string, unknown>,
      signal: options?.signal,
    });
  },

  /**
   * Get single expenditure request detail.
   * GET /api/v1/finance/expenditures/{id}
   */
  getExpenditureRequest: async (
    id: string,
    options?: { signal?: AbortSignal }
  ): Promise<ApiSuccessResponse<ExpenditureRequest>> => {
    return apiClient.get<ExpenditureRequest>(`finance/expenditures/${encodeURIComponent(id)}`, {
      signal: options?.signal,
    });
  },

  /**
   * Create an expenditure request against a published budget line.
   * POST /api/v1/finance/expenditures
   * Requires Idempotency-Key
   */
  createExpenditureRequest: async (
    payload: CreateExpenditureRequestPayload,
    options?: FinanceRequestOptions
  ): Promise<ApiSuccessResponse<ExpenditureRequest>> => {
    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.post<ExpenditureRequest>('finance/expenditures', payload, {
      idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Submit a draft expenditure request for approval.
   * POST /api/v1/finance/expenditures/{id}/submit
   * Requires Idempotency-Key
   */
  submitExpenditureRequest: async (
    id: string,
    options?: FinanceRequestOptions
  ): Promise<ApiSuccessResponse<ExpenditureRequest>> => {
    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.post<ExpenditureRequest>(`finance/expenditures/${encodeURIComponent(id)}/submit`, {}, {
      idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Approve a submitted expenditure request (Ketua RW / Ketua RT only; no self-approval).
   * POST /api/v1/finance/expenditures/{id}/approve
   * Requires Idempotency-Key
   */
  approveExpenditureRequest: async (
    id: string,
    payload: ApproveExpenditurePayload = {},
    options?: FinanceRequestOptions
  ): Promise<ApiSuccessResponse<ExpenditureRequest>> => {
    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.post<ExpenditureRequest>(`finance/expenditures/${encodeURIComponent(id)}/approve`, payload, {
      idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Reject a submitted expenditure request.
   * POST /api/v1/finance/expenditures/{id}/reject
   * Requires Idempotency-Key
   */
  rejectExpenditureRequest: async (
    id: string,
    payload: RejectExpenditurePayload = {},
    options?: FinanceRequestOptions
  ): Promise<ApiSuccessResponse<ExpenditureRequest>> => {
    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.post<ExpenditureRequest>(`finance/expenditures/${encodeURIComponent(id)}/reject`, payload, {
      idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Cancel an expenditure request (draft or submitted).
   * POST /api/v1/finance/expenditures/{id}/cancel
   * Requires Idempotency-Key
   */
  cancelExpenditureRequest: async (
    id: string,
    payload: CancelExpenditurePayload = {},
    options?: FinanceRequestOptions
  ): Promise<ApiSuccessResponse<ExpenditureRequest>> => {
    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.post<ExpenditureRequest>(`finance/expenditures/${encodeURIComponent(id)}/cancel`, payload, {
      idempotencyKey,
      signal: options?.signal,
    });
  },

  /**
   * Post an approved expenditure request to the immutable ledger (Bendahara only).
   * POST /api/v1/finance/expenditures/{id}/post
   * Requires Idempotency-Key
   */
  postExpenditureRequest: async (
    id: string,
    payload: PostExpenditurePayload = {},
    options?: FinanceRequestOptions
  ): Promise<ApiSuccessResponse<ExpenditureRequest>> => {
    const idempotencyKey = options?.idempotencyKey || generateIdempotencyKey();
    return apiClient.post<ExpenditureRequest>(`finance/expenditures/${encodeURIComponent(id)}/post`, payload, {
      idempotencyKey,
      signal: options?.signal,
    });
  },
};
