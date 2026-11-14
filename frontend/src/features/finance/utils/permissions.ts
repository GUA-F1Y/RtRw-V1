import type { User } from '../../../types/auth';

export const FINANCE_PERMISSIONS = {
  ACCOUNT_VIEW: 'finance.account.view',
  ACCOUNT_MANAGE: 'finance.account.manage',
  CATEGORY_MANAGE: 'finance.category.manage',
  TRANSACTION_VIEW: 'finance.transaction.view',
  TRANSACTION_RECORD: 'finance.transaction.record',
  TRANSACTION_REVERSE: 'finance.transaction.reverse',
  PERIOD_CLOSE: 'finance.period.close',
  TRANSFER_VIEW: 'finance.transfer.view',
  TRANSFER_CREATE: 'finance.transfer.create',
  TRANSFER_REVERSE: 'finance.transfer.reverse',

  // Phase 27: Budget Planning & Expenditure Approval
  BUDGET_VIEW: 'finance.budget.view',
  BUDGET_MANAGE: 'finance.budget.manage',
  BUDGET_PUBLISH: 'finance.budget.publish',
  EXPENDITURE_VIEW: 'finance.expenditure.view',
  EXPENDITURE_REQUEST: 'finance.expenditure.request',
  EXPENDITURE_APPROVE: 'finance.expenditure.approve',
  EXPENDITURE_POST: 'finance.expenditure.post',
} as const;

export type FinancePermissionKey =
  (typeof FINANCE_PERMISSIONS)[keyof typeof FINANCE_PERMISSIONS];

/**
 * Mapping of system role keys to finance permission capabilities,
 * directly reflecting the backend RoleKey permissions.
 */
const ROLE_FINANCE_PERMISSIONS: Record<string, FinancePermissionKey[]> = {
  // RW executive leaders have full finance authority with strict segregation of duties
  ketua_rw: [
    FINANCE_PERMISSIONS.ACCOUNT_VIEW,
    FINANCE_PERMISSIONS.ACCOUNT_MANAGE,
    FINANCE_PERMISSIONS.CATEGORY_MANAGE,
    FINANCE_PERMISSIONS.TRANSACTION_VIEW,
    FINANCE_PERMISSIONS.TRANSACTION_RECORD,
    FINANCE_PERMISSIONS.TRANSACTION_REVERSE,
    FINANCE_PERMISSIONS.PERIOD_CLOSE,
    FINANCE_PERMISSIONS.TRANSFER_VIEW,
    FINANCE_PERMISSIONS.TRANSFER_CREATE,
    FINANCE_PERMISSIONS.TRANSFER_REVERSE,
    FINANCE_PERMISSIONS.BUDGET_VIEW,
    FINANCE_PERMISSIONS.BUDGET_MANAGE,
    FINANCE_PERMISSIONS.BUDGET_PUBLISH,
    FINANCE_PERMISSIONS.EXPENDITURE_VIEW,
    FINANCE_PERMISSIONS.EXPENDITURE_REQUEST,
    FINANCE_PERMISSIONS.EXPENDITURE_APPROVE, // Ketua approves
  ],
  bendahara_rw: [
    FINANCE_PERMISSIONS.ACCOUNT_VIEW,
    FINANCE_PERMISSIONS.ACCOUNT_MANAGE,
    FINANCE_PERMISSIONS.CATEGORY_MANAGE,
    FINANCE_PERMISSIONS.TRANSACTION_VIEW,
    FINANCE_PERMISSIONS.TRANSACTION_RECORD,
    FINANCE_PERMISSIONS.TRANSACTION_REVERSE,
    FINANCE_PERMISSIONS.PERIOD_CLOSE,
    FINANCE_PERMISSIONS.TRANSFER_VIEW,
    FINANCE_PERMISSIONS.TRANSFER_CREATE,
    FINANCE_PERMISSIONS.TRANSFER_REVERSE,
    FINANCE_PERMISSIONS.BUDGET_VIEW,
    FINANCE_PERMISSIONS.BUDGET_MANAGE,
    FINANCE_PERMISSIONS.EXPENDITURE_VIEW,
    FINANCE_PERMISSIONS.EXPENDITURE_REQUEST,
    FINANCE_PERMISSIONS.EXPENDITURE_POST, // Bendahara posts
  ],
  // Secretaries have read-only audit visibility and can request expenditures
  sekretaris_rw: [
    FINANCE_PERMISSIONS.ACCOUNT_VIEW,
    FINANCE_PERMISSIONS.TRANSACTION_VIEW,
    FINANCE_PERMISSIONS.TRANSFER_VIEW,
    FINANCE_PERMISSIONS.BUDGET_VIEW,
    FINANCE_PERMISSIONS.EXPENDITURE_VIEW,
    FINANCE_PERMISSIONS.EXPENDITURE_REQUEST,
  ],

  // RT executive leaders have full finance authority within their RT scope
  ketua_rt: [
    FINANCE_PERMISSIONS.ACCOUNT_VIEW,
    FINANCE_PERMISSIONS.ACCOUNT_MANAGE,
    FINANCE_PERMISSIONS.CATEGORY_MANAGE,
    FINANCE_PERMISSIONS.TRANSACTION_VIEW,
    FINANCE_PERMISSIONS.TRANSACTION_RECORD,
    FINANCE_PERMISSIONS.TRANSACTION_REVERSE,
    FINANCE_PERMISSIONS.PERIOD_CLOSE,
    FINANCE_PERMISSIONS.TRANSFER_VIEW,
    FINANCE_PERMISSIONS.TRANSFER_CREATE,
    FINANCE_PERMISSIONS.TRANSFER_REVERSE,
    FINANCE_PERMISSIONS.BUDGET_VIEW,
    FINANCE_PERMISSIONS.BUDGET_MANAGE,
    FINANCE_PERMISSIONS.BUDGET_PUBLISH,
    FINANCE_PERMISSIONS.EXPENDITURE_VIEW,
    FINANCE_PERMISSIONS.EXPENDITURE_REQUEST,
    FINANCE_PERMISSIONS.EXPENDITURE_APPROVE,
  ],
  bendahara_rt: [
    FINANCE_PERMISSIONS.ACCOUNT_VIEW,
    FINANCE_PERMISSIONS.ACCOUNT_MANAGE,
    FINANCE_PERMISSIONS.CATEGORY_MANAGE,
    FINANCE_PERMISSIONS.TRANSACTION_VIEW,
    FINANCE_PERMISSIONS.TRANSACTION_RECORD,
    FINANCE_PERMISSIONS.TRANSACTION_REVERSE,
    FINANCE_PERMISSIONS.PERIOD_CLOSE,
    FINANCE_PERMISSIONS.TRANSFER_VIEW,
    FINANCE_PERMISSIONS.TRANSFER_CREATE,
    FINANCE_PERMISSIONS.TRANSFER_REVERSE,
    FINANCE_PERMISSIONS.BUDGET_VIEW,
    FINANCE_PERMISSIONS.BUDGET_MANAGE,
    FINANCE_PERMISSIONS.EXPENDITURE_VIEW,
    FINANCE_PERMISSIONS.EXPENDITURE_REQUEST,
    FINANCE_PERMISSIONS.EXPENDITURE_POST,
  ],
  sekretaris_rt: [
    FINANCE_PERMISSIONS.ACCOUNT_VIEW,
    FINANCE_PERMISSIONS.TRANSACTION_VIEW,
    FINANCE_PERMISSIONS.TRANSFER_VIEW,
    FINANCE_PERMISSIONS.BUDGET_VIEW,
    FINANCE_PERMISSIONS.EXPENDITURE_VIEW,
    FINANCE_PERMISSIONS.EXPENDITURE_REQUEST,
  ],

  // Organization administrators have system-wide authority
  organization_admin: [
    FINANCE_PERMISSIONS.ACCOUNT_VIEW,
    FINANCE_PERMISSIONS.ACCOUNT_MANAGE,
    FINANCE_PERMISSIONS.CATEGORY_MANAGE,
    FINANCE_PERMISSIONS.TRANSACTION_VIEW,
    FINANCE_PERMISSIONS.TRANSACTION_RECORD,
    FINANCE_PERMISSIONS.TRANSACTION_REVERSE,
    FINANCE_PERMISSIONS.PERIOD_CLOSE,
    FINANCE_PERMISSIONS.TRANSFER_VIEW,
    FINANCE_PERMISSIONS.TRANSFER_CREATE,
    FINANCE_PERMISSIONS.TRANSFER_REVERSE,
    FINANCE_PERMISSIONS.BUDGET_VIEW,
    FINANCE_PERMISSIONS.BUDGET_MANAGE,
    FINANCE_PERMISSIONS.BUDGET_PUBLISH,
    FINANCE_PERMISSIONS.EXPENDITURE_VIEW,
    FINANCE_PERMISSIONS.EXPENDITURE_REQUEST,
    FINANCE_PERMISSIONS.EXPENDITURE_APPROVE,
    FINANCE_PERMISSIONS.EXPENDITURE_POST,
  ],
};

/**
 * Check if the user holds a specific finance permission across any of their active memberships.
 * Note: Client-side permission checks are for UX affordance (hiding/disabling buttons);
 * authoritative access is strictly validated server-side by Laravel policies.
 */
export function hasFinancePermission(
  user: User | null | undefined,
  permission: FinancePermissionKey
): boolean {
  if (!user) {
    return false;
  }

  // Check explicit permissions array if present on user object
  const userPerms = (user as unknown as { permissions?: string[] }).permissions;
  if (Array.isArray(userPerms) && userPerms.includes(permission)) {
    return true;
  }

  if (!user.memberships) {
    return false;
  }

  const activeMemberships = user.memberships.filter((m) => m.is_active);

  for (const m of activeMemberships) {
    const roleKey = m.role?.key;
    if (roleKey && ROLE_FINANCE_PERMISSIONS[roleKey]?.includes(permission)) {
      return true;
    }
  }

  return false;
}

export function canManageFinanceAccounts(user: User | null | undefined): boolean {
  return hasFinancePermission(user, FINANCE_PERMISSIONS.ACCOUNT_MANAGE);
}

export function canRecordFinanceTransactions(user: User | null | undefined): boolean {
  return hasFinancePermission(user, FINANCE_PERMISSIONS.TRANSACTION_RECORD);
}

export function canReverseFinanceTransactions(user: User | null | undefined): boolean {
  return hasFinancePermission(user, FINANCE_PERMISSIONS.TRANSACTION_REVERSE);
}

export function canManageFinanceCategories(user: User | null | undefined): boolean {
  return hasFinancePermission(user, FINANCE_PERMISSIONS.CATEGORY_MANAGE);
}

export function canCloseFinancePeriod(user: User | null | undefined): boolean {
  return hasFinancePermission(user, FINANCE_PERMISSIONS.PERIOD_CLOSE);
}

export function canViewFinanceTransfers(user: User | null | undefined): boolean {
  return hasFinancePermission(user, FINANCE_PERMISSIONS.TRANSFER_VIEW);
}

export function canCreateFinanceTransfer(user: User | null | undefined): boolean {
  return hasFinancePermission(user, FINANCE_PERMISSIONS.TRANSFER_CREATE);
}

export function canReverseFinanceTransfer(user: User | null | undefined): boolean {
  return hasFinancePermission(user, FINANCE_PERMISSIONS.TRANSFER_REVERSE);
}

export function canViewBudgets(user: User | null | undefined): boolean {
  return hasFinancePermission(user, FINANCE_PERMISSIONS.BUDGET_VIEW);
}

export function canManageBudgets(user: User | null | undefined): boolean {
  return hasFinancePermission(user, FINANCE_PERMISSIONS.BUDGET_MANAGE);
}

export function canPublishBudgets(user: User | null | undefined): boolean {
  return hasFinancePermission(user, FINANCE_PERMISSIONS.BUDGET_PUBLISH);
}

export function canViewExpenditures(user: User | null | undefined): boolean {
  return hasFinancePermission(user, FINANCE_PERMISSIONS.EXPENDITURE_VIEW);
}

export function canRequestExpenditures(user: User | null | undefined): boolean {
  return hasFinancePermission(user, FINANCE_PERMISSIONS.EXPENDITURE_REQUEST);
}

export function canApproveExpenditures(user: User | null | undefined): boolean {
  return hasFinancePermission(user, FINANCE_PERMISSIONS.EXPENDITURE_APPROVE);
}

export function canPostExpenditures(user: User | null | undefined): boolean {
  return hasFinancePermission(user, FINANCE_PERMISSIONS.EXPENDITURE_POST);
}

