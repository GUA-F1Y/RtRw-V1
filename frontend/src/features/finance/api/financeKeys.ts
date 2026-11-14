import type {
  CashAccountQueryParams,
  FinanceCategoryQueryParams,
  FinancialTransactionQueryParams,
} from '../../../types/finance';

export const financeKeys = {
  all: ['finance'] as const,

  // Cash Accounts
  accounts: () => [...financeKeys.all, 'accounts'] as const,
  accountLists: () => [...financeKeys.accounts(), 'list'] as const,
  accountList: (params?: CashAccountQueryParams) =>
    [...financeKeys.accountLists(), params ? { ...params } : {}] as const,
  accountDetails: () => [...financeKeys.accounts(), 'detail'] as const,
  accountDetail: (id: string) => [...financeKeys.accountDetails(), id] as const,
  accountSummaries: () => [...financeKeys.accounts(), 'summary'] as const,
  accountSummary: (id: string) => [...financeKeys.accountSummaries(), id] as const,

  // Categories
  categories: () => [...financeKeys.all, 'categories'] as const,
  categoryLists: () => [...financeKeys.categories(), 'list'] as const,
  categoryList: (params?: FinanceCategoryQueryParams) =>
    [...financeKeys.categoryLists(), params ? { ...params } : {}] as const,
  categoryDetail: (id: string) => [...financeKeys.categories(), 'detail', id] as const,

  // Financial Transactions
  transactions: () => [...financeKeys.all, 'transactions'] as const,
  transactionLists: () => [...financeKeys.transactions(), 'list'] as const,
  transactionList: (params?: FinancialTransactionQueryParams) =>
    [...financeKeys.transactionLists(), params ? { ...params } : {}] as const,
  transactionDetails: () => [...financeKeys.transactions(), 'detail'] as const,
  transactionDetail: (id: string) => [...financeKeys.transactionDetails(), id] as const,
  transactionEvidence: (id: string) => [...financeKeys.transactionDetail(id), 'evidence'] as const,

  // Reports & Analytics
  reports: () => [...financeKeys.all, 'reports'] as const,
  reportSummary: (params?: Record<string, unknown>) =>
    [...financeKeys.reports(), 'summary', params ? { ...params } : {}] as const,
  reportCashFlow: (params?: Record<string, unknown>) =>
    [...financeKeys.reports(), 'cash-flow', params ? { ...params } : {}] as const,
  reportCategories: (params?: Record<string, unknown>) =>
    [...financeKeys.reports(), 'categories', params ? { ...params } : {}] as const,
  reportRtBreakdown: (params?: Record<string, unknown>) =>
    [...financeKeys.reports(), 'rt-breakdown', params ? { ...params } : {}] as const,
  reportAccounts: (params?: Record<string, unknown>) =>
    [...financeKeys.reports(), 'accounts', params ? { ...params } : {}] as const,

  // Period Closures & Reconciliation (Phase 25)
  periodClosures: () => [...financeKeys.all, 'period-closures'] as const,
  periodClosureLists: () => [...financeKeys.periodClosures(), 'list'] as const,
  periodClosureList: (params?: import('../../../types/finance').PeriodClosureQueryParams) =>
    [...financeKeys.periodClosureLists(), params ? { ...params } : {}] as const,
  periodClosureDetails: () => [...financeKeys.periodClosures(), 'detail'] as const,
  periodClosureDetail: (id: string) => [...financeKeys.periodClosureDetails(), id] as const,
  periodClosurePreviews: () => [...financeKeys.periodClosures(), 'preview'] as const,
  periodClosurePreview: (accountId: string, year: number, month: number) =>
    [...financeKeys.periodClosurePreviews(), accountId, year, month] as const,

  // Internal Cash Transfers (Phase 26)
  transfers: () => [...financeKeys.all, 'transfers'] as const,
  transferLists: () => [...financeKeys.transfers(), 'list'] as const,
  transferList: (params?: import('../../../types/finance').TransferQueryParams) =>
    [...financeKeys.transferLists(), params ? { ...params } : {}] as const,
  transferDetails: () => [...financeKeys.transfers(), 'detail'] as const,
  transferDetail: (id: string) => [...financeKeys.transferDetails(), id] as const,

  // Budget Planning & Approvals (Phase 27)
  budgets: () => [...financeKeys.all, 'budgets'] as const,
  budgetLists: () => [...financeKeys.budgets(), 'list'] as const,
  budgetList: (params?: import('../../../types/finance').BudgetPlanFilterParams) =>
    [...financeKeys.budgetLists(), params ? { ...params } : {}] as const,
  budgetDetails: () => [...financeKeys.budgets(), 'detail'] as const,
  budgetDetail: (id: string) => [...financeKeys.budgetDetails(), id] as const,
  budgetUtilization: (params?: import('../../../types/finance').BudgetUtilizationFilterParams) =>
    [...financeKeys.budgets(), 'utilization', params ? { ...params } : {}] as const,

  expenditures: () => [...financeKeys.all, 'expenditures'] as const,
  expenditureLists: () => [...financeKeys.expenditures(), 'list'] as const,
  expenditureList: (params?: import('../../../types/finance').ExpenditureRequestFilterParams) =>
    [...financeKeys.expenditureLists(), params ? { ...params } : {}] as const,
  expenditureDetails: () => [...financeKeys.expenditures(), 'detail'] as const,
  expenditureDetail: (id: string) => [...financeKeys.expenditureDetails(), id] as const,
};

