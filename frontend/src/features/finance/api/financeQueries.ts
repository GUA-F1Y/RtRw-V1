import { useMutation, useQuery, useQueryClient, type UseQueryOptions } from '@tanstack/react-query';
import type { ApiError } from '../../../lib/api/errors.ts';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
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
import { overviewKeys } from '../../overview/api/overviewKeys.ts';
import { financeApi } from './financeApi.ts';
import { financeKeys } from './financeKeys.ts';

// -------------------------------------------------------------
// Read Queries
// -------------------------------------------------------------

export function useCashAccounts(
  params?: CashAccountQueryParams,
  options?: Omit<UseQueryOptions<ApiSuccessResponse<CashAccount[]>, ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<ApiSuccessResponse<CashAccount[]>, ApiError>({
    queryKey: financeKeys.accountList(params),
    queryFn: ({ signal }) => financeApi.listAccounts(params, { signal }),
    ...options,
  });
}

export function useCashAccount(
  id: string,
  options?: Omit<UseQueryOptions<ApiSuccessResponse<CashAccount>, ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<ApiSuccessResponse<CashAccount>, ApiError>({
    queryKey: financeKeys.accountDetail(id),
    queryFn: ({ signal }) => financeApi.getAccountById(id, { signal }),
    enabled: Boolean(id) && (options?.enabled ?? true),
    ...options,
  });
}

export function useAccountSummary(
  id: string,
  options?: Omit<UseQueryOptions<ApiSuccessResponse<AccountSummary>, ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<ApiSuccessResponse<AccountSummary>, ApiError>({
    queryKey: financeKeys.accountSummary(id),
    queryFn: ({ signal }) => financeApi.getAccountSummary(id, { signal }),
    enabled: Boolean(id) && (options?.enabled ?? true),
    ...options,
  });
}

export function useFinanceCategories(
  params?: FinanceCategoryQueryParams,
  options?: Omit<UseQueryOptions<ApiSuccessResponse<FinanceCategory[]>, ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<ApiSuccessResponse<FinanceCategory[]>, ApiError>({
    queryKey: financeKeys.categoryList(params),
    queryFn: ({ signal }) => financeApi.listCategories(params, { signal }),
    ...options,
  });
}

export function useFinanceCategory(
  id: string,
  options?: Omit<UseQueryOptions<ApiSuccessResponse<FinanceCategory>, ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<ApiSuccessResponse<FinanceCategory>, ApiError>({
    queryKey: financeKeys.categoryDetail(id),
    queryFn: ({ signal }) => financeApi.getCategoryById(id, { signal }),
    enabled: Boolean(id) && (options?.enabled ?? true),
    ...options,
  });
}

export function useFinancialTransactions(
  params?: FinancialTransactionQueryParams,
  options?: Omit<UseQueryOptions<ApiSuccessResponse<FinancialTransaction[]>, ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<ApiSuccessResponse<FinancialTransaction[]>, ApiError>({
    queryKey: financeKeys.transactionList(params),
    queryFn: ({ signal }) => financeApi.listTransactions(params, { signal }),
    ...options,
  });
}

export function useFinancialTransaction(
  id: string,
  options?: Omit<UseQueryOptions<ApiSuccessResponse<FinancialTransaction>, ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<ApiSuccessResponse<FinancialTransaction>, ApiError>({
    queryKey: financeKeys.transactionDetail(id),
    queryFn: ({ signal }) => financeApi.getTransactionById(id, { signal }),
    enabled: Boolean(id) && (options?.enabled ?? true),
    ...options,
  });
}

// -------------------------------------------------------------
// Mutation Hooks
// -------------------------------------------------------------

export function useCreateCashAccount() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<CashAccount>,
    ApiError,
    { payload: CreateCashAccountPayload; idempotencyKey?: string }
  >({
    mutationFn: ({ payload, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return financeApi.createAccount(payload, { idempotencyKey: key });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: financeKeys.accountLists() });
      queryClient.invalidateQueries({ queryKey: overviewKeys.all });
    },
  });
}

export function useUpdateCashAccount() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<CashAccount>,
    ApiError,
    { id: string; payload: UpdateCashAccountPayload }
  >({
    mutationFn: ({ id, payload }) => financeApi.updateAccount(id, payload),
    onSuccess: (_response, { id }) => {
      queryClient.invalidateQueries({ queryKey: financeKeys.accountDetail(id) });
      queryClient.invalidateQueries({ queryKey: financeKeys.accountLists() });
    },
  });
}

export function useCloseCashAccount() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<CashAccount>,
    ApiError,
    { id: string; payload: CloseCashAccountPayload }
  >({
    mutationFn: ({ id, payload }) => financeApi.closeAccount(id, payload),
    onSuccess: (_response, { id }) => {
      queryClient.invalidateQueries({ queryKey: financeKeys.accountDetail(id) });
      queryClient.invalidateQueries({ queryKey: financeKeys.accountSummary(id) });
      queryClient.invalidateQueries({ queryKey: financeKeys.accountLists() });
      queryClient.invalidateQueries({ queryKey: overviewKeys.all });
    },
  });
}

export function useCreateFinanceCategory() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<FinanceCategory>,
    ApiError,
    { payload: CreateFinanceCategoryPayload; idempotencyKey?: string }
  >({
    mutationFn: ({ payload, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return financeApi.createCategory(payload, { idempotencyKey: key });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: financeKeys.categoryLists() });
    },
  });
}

export function useUpdateFinanceCategory() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<FinanceCategory>,
    ApiError,
    { id: string; payload: UpdateFinanceCategoryPayload }
  >({
    mutationFn: ({ id, payload }) => financeApi.updateCategory(id, payload),
    onSuccess: (_response, { id }) => {
      queryClient.invalidateQueries({ queryKey: financeKeys.categoryDetail(id) });
      queryClient.invalidateQueries({ queryKey: financeKeys.categoryLists() });
    },
  });
}

export function useRecordFinancialTransaction() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<FinancialTransaction>,
    ApiError,
    { payload: RecordTransactionPayload; idempotencyKey?: string }
  >({
    mutationFn: ({ payload, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return financeApi.recordTransaction(payload, { idempotencyKey: key });
    },
    onSuccess: (_response, { payload }) => {
      queryClient.invalidateQueries({ queryKey: financeKeys.transactionLists() });
      if (payload.cash_account_id) {
        queryClient.invalidateQueries({
          queryKey: financeKeys.accountSummary(payload.cash_account_id),
        });
        queryClient.invalidateQueries({
          queryKey: financeKeys.accountDetail(payload.cash_account_id),
        });
      }
      queryClient.invalidateQueries({ queryKey: financeKeys.accountLists() });
      queryClient.invalidateQueries({ queryKey: financeKeys.reports() });
      queryClient.invalidateQueries({ queryKey: overviewKeys.all });
    },
  });
}

export function useReverseFinancialTransaction() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<FinancialTransaction>,
    ApiError,
    { id: string; payload: ReverseTransactionPayload; cashAccountId?: string; idempotencyKey?: string }
  >({
    mutationFn: ({ id, payload, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return financeApi.reverseTransaction(id, payload, { idempotencyKey: key });
    },
    onSuccess: (_response, { id, cashAccountId }) => {
      queryClient.invalidateQueries({ queryKey: financeKeys.transactionLists() });
      queryClient.invalidateQueries({ queryKey: financeKeys.transactionDetail(id) });
      if (cashAccountId) {
        queryClient.invalidateQueries({ queryKey: financeKeys.accountSummary(cashAccountId) });
        queryClient.invalidateQueries({ queryKey: financeKeys.accountDetail(cashAccountId) });
      } else {
        queryClient.invalidateQueries({ queryKey: financeKeys.accountSummaries() });
      }
      queryClient.invalidateQueries({ queryKey: financeKeys.accountLists() });
      queryClient.invalidateQueries({ queryKey: financeKeys.reports() });
      queryClient.invalidateQueries({ queryKey: overviewKeys.all });
    },
  });
}

export function useTransactionEvidence(
  id: string,
  options?: { enabled?: boolean }
) {
  return useQuery<ApiSuccessResponse<import('../../../types/finance.ts').EvidenceFileSummary[]>, ApiError>({
    queryKey: financeKeys.transactionEvidence(id),
    queryFn: ({ signal }) => financeApi.listTransactionEvidence(id, { signal }),
    enabled: Boolean(id) && options?.enabled !== false,
  });
}

export function useAttachTransactionEvidence() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<FinancialTransaction>,
    ApiError,
    { id: string; payload: { file_id?: string; file?: File }; idempotencyKey?: string }
  >({
    mutationFn: ({ id, payload, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return financeApi.attachTransactionEvidence(id, payload, { idempotencyKey: key });
    },
    onSuccess: (_response, { id }) => {
      queryClient.invalidateQueries({ queryKey: financeKeys.transactionDetail(id) });
      queryClient.invalidateQueries({ queryKey: financeKeys.transactionEvidence(id) });
      queryClient.invalidateQueries({ queryKey: financeKeys.transactionLists() });
    },
  });
}

// -------------------------------------------------------------
// Report Queries
// -------------------------------------------------------------

export function useFinanceReportSummary(
  params?: ReportPeriodFilter,
  options?: { enabled?: boolean }
) {
  return useQuery<ApiSuccessResponse<ReportSummaryData>, ApiError>({
    queryKey: financeKeys.reportSummary(params as Record<string, unknown>),
    queryFn: ({ signal }) => financeApi.getReportSummary(params, { signal }),
    enabled: options?.enabled !== false,
  });
}

export function useFinanceReportCashFlow(
  params?: ReportPeriodFilter,
  options?: { enabled?: boolean }
) {
  return useQuery<ApiSuccessResponse<CashFlowTrendData>, ApiError>({
    queryKey: financeKeys.reportCashFlow(params as Record<string, unknown>),
    queryFn: ({ signal }) => financeApi.getReportCashFlow(params, { signal }),
    enabled: options?.enabled !== false,
  });
}

export function useFinanceReportCategories(
  params?: ReportPeriodFilter,
  options?: { enabled?: boolean }
) {
  return useQuery<ApiSuccessResponse<CategoryBreakdownData>, ApiError>({
    queryKey: financeKeys.reportCategories(params as Record<string, unknown>),
    queryFn: ({ signal }) => financeApi.getReportCategories(params, { signal }),
    enabled: options?.enabled !== false,
  });
}

export function useFinanceReportRtBreakdown(
  params?: ReportPeriodFilter,
  options?: { enabled?: boolean }
) {
  return useQuery<ApiSuccessResponse<RtBreakdownData>, ApiError>({
    queryKey: financeKeys.reportRtBreakdown(params as Record<string, unknown>),
    queryFn: ({ signal }) => financeApi.getReportRtBreakdown(params, { signal }),
    enabled: options?.enabled !== false,
  });
}

export function useFinanceReportAccounts(
  params?: ReportPeriodFilter,
  options?: { enabled?: boolean }
) {
  return useQuery<ApiSuccessResponse<AccountComparisonData>, ApiError>({
    queryKey: financeKeys.reportAccounts(params as Record<string, unknown>),
    queryFn: ({ signal }) => financeApi.getReportAccounts(params, { signal }),
    enabled: options?.enabled !== false,
  });
}

export function useExportFinanceReportCsv() {
  return useMutation<{ blob: Blob; filename: string }, ApiError, { params?: ReportPeriodFilter }>({
    mutationFn: ({ params }) => financeApi.exportReportCsv(params),
  });
}

// -------------------------------------------------------------
// Period Closures & Reconciliation (Phase 25)
// -------------------------------------------------------------

export function usePeriodClosures(
  params?: PeriodClosureQueryParams,
  options?: Omit<UseQueryOptions<ApiSuccessResponse<FinancialPeriodClosure[]>, ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<ApiSuccessResponse<FinancialPeriodClosure[]>, ApiError>({
    queryKey: financeKeys.periodClosureList(params),
    queryFn: ({ signal }) => financeApi.listPeriodClosures(params, { signal }),
    ...options,
  });
}

export function usePeriodClosure(
  id: string,
  options?: Omit<UseQueryOptions<ApiSuccessResponse<FinancialPeriodClosure>, ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<ApiSuccessResponse<FinancialPeriodClosure>, ApiError>({
    queryKey: financeKeys.periodClosureDetail(id),
    queryFn: ({ signal }) => financeApi.getPeriodClosureById(id, { signal }),
    enabled: Boolean(id) && (options?.enabled ?? true),
    ...options,
  });
}

export function usePeriodClosurePreview(
  accountId: string,
  year: number,
  month: number,
  options?: { enabled?: boolean }
) {
  return useQuery<ApiSuccessResponse<PeriodClosurePreview>, ApiError>({
    queryKey: financeKeys.periodClosurePreview(accountId, year, month),
    queryFn: ({ signal }) => financeApi.previewPeriodClosure(accountId, year, month, { signal }),
    enabled: Boolean(accountId) && year >= 2000 && month >= 1 && month <= 12 && (options?.enabled !== false),
  });
}

export function useClosePeriod() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<FinancialPeriodClosure>,
    ApiError,
    {
      accountId: string;
      year: number;
      month: number;
      payload: ClosePeriodPayload;
      idempotencyKey?: string;
    }
  >({
    mutationFn: ({ accountId, year, month, payload, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return financeApi.closePeriod(accountId, year, month, payload, { idempotencyKey: key });
    },
    onSuccess: (_, variables) => {
      // Invalidate relevant cache families
      queryClient.invalidateQueries({ queryKey: financeKeys.periodClosures() });
      queryClient.invalidateQueries({ queryKey: financeKeys.accounts() });
      queryClient.invalidateQueries({ queryKey: financeKeys.accountSummary(variables.accountId) });
      queryClient.invalidateQueries({ queryKey: financeKeys.reports() });
      queryClient.invalidateQueries({ queryKey: overviewKeys.all });
    },
  });
}

// -------------------------------------------------------------
// Internal Cash Transfers (Phase 26)
// -------------------------------------------------------------

export function useCashTransfers(
  params?: TransferQueryParams,
  options?: Omit<UseQueryOptions<ApiSuccessResponse<CashTransfer[]>, ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<ApiSuccessResponse<CashTransfer[]>, ApiError>({
    queryKey: financeKeys.transferList(params),
    queryFn: ({ signal }) => financeApi.listTransfers(params, { signal }),
    ...options,
  });
}

export function useCashTransfer(
  id: string,
  options?: Omit<UseQueryOptions<ApiSuccessResponse<CashTransfer>, ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<ApiSuccessResponse<CashTransfer>, ApiError>({
    queryKey: financeKeys.transferDetail(id),
    queryFn: ({ signal }) => financeApi.getTransferById(id, { signal }),
    enabled: Boolean(id) && (options?.enabled ?? true),
    ...options,
  });
}

export function useRecordCashTransfer() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<CashTransfer>,
    ApiError,
    { payload: RecordTransferPayload; idempotencyKey?: string }
  >({
    mutationFn: ({ payload, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return financeApi.recordTransfer(payload, { idempotencyKey: key });
    },
    onSuccess: (_response, { payload }) => {
      queryClient.invalidateQueries({ queryKey: financeKeys.transfers() });
      if (payload.source_cash_account_id) {
        queryClient.invalidateQueries({ queryKey: financeKeys.accountDetail(payload.source_cash_account_id) });
        queryClient.invalidateQueries({ queryKey: financeKeys.accountSummary(payload.source_cash_account_id) });
      }
      if (payload.destination_cash_account_id) {
        queryClient.invalidateQueries({ queryKey: financeKeys.accountDetail(payload.destination_cash_account_id) });
        queryClient.invalidateQueries({ queryKey: financeKeys.accountSummary(payload.destination_cash_account_id) });
      }
      queryClient.invalidateQueries({ queryKey: financeKeys.accountLists() });
      queryClient.invalidateQueries({ queryKey: financeKeys.periodClosurePreviews() });
      queryClient.invalidateQueries({ queryKey: financeKeys.reports() });
      queryClient.invalidateQueries({ queryKey: financeKeys.transactionLists() });
      queryClient.invalidateQueries({ queryKey: overviewKeys.all });
    },
  });
}

export function useReverseCashTransfer() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<CashTransfer>,
    ApiError,
    { id: string; payload: ReverseTransferPayload; sourceAccountId?: string; destinationAccountId?: string; idempotencyKey?: string }
  >({
    mutationFn: ({ id, payload, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return financeApi.reverseTransfer(id, payload, { idempotencyKey: key });
    },
    onSuccess: (_response, { id, sourceAccountId, destinationAccountId }) => {
      queryClient.invalidateQueries({ queryKey: financeKeys.transfers() });
      queryClient.invalidateQueries({ queryKey: financeKeys.transferDetail(id) });
      if (sourceAccountId) {
        queryClient.invalidateQueries({ queryKey: financeKeys.accountDetail(sourceAccountId) });
        queryClient.invalidateQueries({ queryKey: financeKeys.accountSummary(sourceAccountId) });
      }
      if (destinationAccountId) {
        queryClient.invalidateQueries({ queryKey: financeKeys.accountDetail(destinationAccountId) });
        queryClient.invalidateQueries({ queryKey: financeKeys.accountSummary(destinationAccountId) });
      }
      queryClient.invalidateQueries({ queryKey: financeKeys.accountLists() });
      queryClient.invalidateQueries({ queryKey: financeKeys.periodClosurePreviews() });
      queryClient.invalidateQueries({ queryKey: financeKeys.reports() });
      queryClient.invalidateQueries({ queryKey: financeKeys.transactionLists() });
      queryClient.invalidateQueries({ queryKey: overviewKeys.all });
    },
  });
}

// -------------------------------------------------------------
// Phase 27: Budget Planning & Approvals Hooks
// -------------------------------------------------------------

export function useBudgetPlans(
  params?: BudgetPlanFilterParams,
  options?: Omit<UseQueryOptions<ApiSuccessResponse<BudgetPlan[]>, ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<ApiSuccessResponse<BudgetPlan[]>, ApiError>({
    queryKey: financeKeys.budgetList(params),
    queryFn: ({ signal }) => financeApi.listBudgetPlans(params, { signal }),
    ...options,
  });
}

export function useBudgetPlan(
  id: string,
  options?: Omit<UseQueryOptions<ApiSuccessResponse<BudgetPlan>, ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<ApiSuccessResponse<BudgetPlan>, ApiError>({
    queryKey: financeKeys.budgetDetail(id),
    queryFn: ({ signal }) => financeApi.getBudgetPlan(id, { signal }),
    enabled: Boolean(id),
    ...options,
  });
}

export function useBudgetUtilizationSummary(
  params?: BudgetUtilizationFilterParams,
  options?: Omit<UseQueryOptions<ApiSuccessResponse<BudgetUtilizationSummaryResult>, ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<ApiSuccessResponse<BudgetUtilizationSummaryResult>, ApiError>({
    queryKey: financeKeys.budgetUtilization(params),
    queryFn: ({ signal }) => financeApi.getBudgetUtilizationSummary(params, { signal }),
    ...options,
  });
}

export function useCreateBudgetPlan() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<BudgetPlan>,
    ApiError,
    { payload: CreateBudgetPlanPayload; idempotencyKey?: string }
  >({
    mutationFn: ({ payload, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return financeApi.createBudgetPlan(payload, { idempotencyKey: key });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: financeKeys.budgets() });
    },
  });
}

export function useUpdateBudgetPlan() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<BudgetPlan>,
    ApiError,
    { id: string; payload: UpdateBudgetPlanPayload; idempotencyKey?: string }
  >({
    mutationFn: ({ id, payload, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return financeApi.updateBudgetPlan(id, payload, { idempotencyKey: key });
    },
    onSuccess: (_response, { id }) => {
      queryClient.invalidateQueries({ queryKey: financeKeys.budgets() });
      queryClient.invalidateQueries({ queryKey: financeKeys.budgetDetail(id) });
    },
  });
}

export function usePublishBudgetPlan() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<BudgetPlan>,
    ApiError,
    { id: string; payload?: PublishBudgetPlanPayload; idempotencyKey?: string }
  >({
    mutationFn: ({ id, payload, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return financeApi.publishBudgetPlan(id, payload, { idempotencyKey: key });
    },
    onSuccess: (_response, { id }) => {
      queryClient.invalidateQueries({ queryKey: financeKeys.budgets() });
      queryClient.invalidateQueries({ queryKey: financeKeys.budgetDetail(id) });
    },
  });
}

export function useAmendBudgetPlan() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<BudgetPlan>,
    ApiError,
    { id: string; payload?: AmendBudgetPlanPayload; idempotencyKey?: string }
  >({
    mutationFn: ({ id, payload, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return financeApi.amendBudgetPlan(id, payload, { idempotencyKey: key });
    },
    onSuccess: (_response, { id }) => {
      queryClient.invalidateQueries({ queryKey: financeKeys.budgets() });
      queryClient.invalidateQueries({ queryKey: financeKeys.budgetDetail(id) });
    },
  });
}

// -------------------------------------------------------------
// Phase 27: Expenditure Requests Hooks
// -------------------------------------------------------------

export function useExpenditureRequests(
  params?: ExpenditureRequestFilterParams,
  options?: Omit<UseQueryOptions<ApiSuccessResponse<ExpenditureRequest[]>, ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<ApiSuccessResponse<ExpenditureRequest[]>, ApiError>({
    queryKey: financeKeys.expenditureList(params),
    queryFn: ({ signal }) => financeApi.listExpenditureRequests(params, { signal }),
    ...options,
  });
}

export function useExpenditureRequest(
  id: string,
  options?: Omit<UseQueryOptions<ApiSuccessResponse<ExpenditureRequest>, ApiError>, 'queryKey' | 'queryFn'>
) {
  return useQuery<ApiSuccessResponse<ExpenditureRequest>, ApiError>({
    queryKey: financeKeys.expenditureDetail(id),
    queryFn: ({ signal }) => financeApi.getExpenditureRequest(id, { signal }),
    enabled: Boolean(id),
    ...options,
  });
}

export function useCreateExpenditureRequest() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<ExpenditureRequest>,
    ApiError,
    { payload: CreateExpenditureRequestPayload; idempotencyKey?: string }
  >({
    mutationFn: ({ payload, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return financeApi.createExpenditureRequest(payload, { idempotencyKey: key });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: financeKeys.expenditures() });
      queryClient.invalidateQueries({ queryKey: financeKeys.budgets() });
    },
  });
}

export function useSubmitExpenditureRequest() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<ExpenditureRequest>,
    ApiError,
    { id: string; idempotencyKey?: string }
  >({
    mutationFn: ({ id, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return financeApi.submitExpenditureRequest(id, { idempotencyKey: key });
    },
    onSuccess: (_response, { id }) => {
      queryClient.invalidateQueries({ queryKey: financeKeys.expenditures() });
      queryClient.invalidateQueries({ queryKey: financeKeys.expenditureDetail(id) });
      queryClient.invalidateQueries({ queryKey: financeKeys.budgets() });
    },
  });
}

export function useApproveExpenditureRequest() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<ExpenditureRequest>,
    ApiError,
    { id: string; payload?: ApproveExpenditurePayload; idempotencyKey?: string }
  >({
    mutationFn: ({ id, payload, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return financeApi.approveExpenditureRequest(id, payload, { idempotencyKey: key });
    },
    onSuccess: (_response, { id }) => {
      queryClient.invalidateQueries({ queryKey: financeKeys.expenditures() });
      queryClient.invalidateQueries({ queryKey: financeKeys.expenditureDetail(id) });
      queryClient.invalidateQueries({ queryKey: financeKeys.budgets() });
    },
  });
}

export function useRejectExpenditureRequest() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<ExpenditureRequest>,
    ApiError,
    { id: string; payload?: RejectExpenditurePayload; idempotencyKey?: string }
  >({
    mutationFn: ({ id, payload, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return financeApi.rejectExpenditureRequest(id, payload, { idempotencyKey: key });
    },
    onSuccess: (_response, { id }) => {
      queryClient.invalidateQueries({ queryKey: financeKeys.expenditures() });
      queryClient.invalidateQueries({ queryKey: financeKeys.expenditureDetail(id) });
      queryClient.invalidateQueries({ queryKey: financeKeys.budgets() });
    },
  });
}

export function useCancelExpenditureRequest() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<ExpenditureRequest>,
    ApiError,
    { id: string; payload?: CancelExpenditurePayload; idempotencyKey?: string }
  >({
    mutationFn: ({ id, payload, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return financeApi.cancelExpenditureRequest(id, payload, { idempotencyKey: key });
    },
    onSuccess: (_response, { id }) => {
      queryClient.invalidateQueries({ queryKey: financeKeys.expenditures() });
      queryClient.invalidateQueries({ queryKey: financeKeys.expenditureDetail(id) });
      queryClient.invalidateQueries({ queryKey: financeKeys.budgets() });
    },
  });
}

export function usePostExpenditureRequest() {
  const queryClient = useQueryClient();

  return useMutation<
    ApiSuccessResponse<ExpenditureRequest>,
    ApiError,
    { id: string; payload?: PostExpenditurePayload; idempotencyKey?: string }
  >({
    mutationFn: ({ id, payload, idempotencyKey }) => {
      const key = idempotencyKey || generateIdempotencyKey();
      return financeApi.postExpenditureRequest(id, payload, { idempotencyKey: key });
    },
    onSuccess: (_response, { id }) => {
      queryClient.invalidateQueries({ queryKey: financeKeys.expenditures() });
      queryClient.invalidateQueries({ queryKey: financeKeys.expenditureDetail(id) });
      queryClient.invalidateQueries({ queryKey: financeKeys.budgets() });
      queryClient.invalidateQueries({ queryKey: financeKeys.accounts() });
      queryClient.invalidateQueries({ queryKey: financeKeys.transactions() });
      queryClient.invalidateQueries({ queryKey: financeKeys.reports() });
      queryClient.invalidateQueries({ queryKey: overviewKeys.all });
    },
  });
}
