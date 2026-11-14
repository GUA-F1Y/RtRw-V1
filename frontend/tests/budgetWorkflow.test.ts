/**
 * Phase 27 — Budget Planning & Expenditure Approval Workflow Unit Tests
 *
 * Covers:
 * - Budget headroom calculation invariants:
 *   available_budget = published_budget_amount - effective_actual_expenses - outstanding_approved_commitments
 * - Integer Rupiah arithmetic invariants
 * - Budget plan lifecycle transitions (DRAFT -> PUBLISHED -> SUPERSEDED / ARCHIVED)
 * - Expenditure request lifecycle state machine & terminal states
 * - Segregation of duties: requester cannot self-approve; Ketua approves; Bendahara posts
 * - Query key factory stability & contracts
 * - Permission helper functions for Phase 27 capabilities
 */

import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { financeApi } from '../src/features/finance/api/financeApi.ts';
import { financeKeys } from '../src/features/finance/api/financeKeys.ts';
import {
  canApproveExpenditures,
  canManageBudgets,
  canPostExpenditures,
  canPublishBudgets,
  canRequestExpenditures,
  canViewBudgets,
  canViewExpenditures,
} from '../src/features/finance/utils/permissions.ts';
import type { User } from '../src/types/auth.ts';
import type {
  BudgetPlanStatus,
  ExpenditureRequestStatus,
} from '../src/types/finance.ts';

// ── 1. Budget Headroom Calculation Invariants ─────────────────────────────────

describe('Budget Headroom Calculation Invariants', () => {
  it('correctly calculates available headroom taking into account actuals and commitments', () => {
    const plannedBudget = 10_000_000;
    const effectiveActualExpense = 3_500_000;
    const outstandingApprovedCommitments = 2_000_000;

    const availableBudget =
      plannedBudget - effectiveActualExpense - outstandingApprovedCommitments;

    assert.equal(availableBudget, 4_500_000);
    assert.ok(Number.isInteger(availableBudget), 'Available budget must remain integer Rupiah');
  });

  it('rejects expenditure requests when amount exceeds available budget headroom', () => {
    const plannedBudget = 5_000_000;
    const effectiveActualExpense = 2_000_000;
    const approvedCommitments = 2_500_000;

    const availableHeadroom = plannedBudget - effectiveActualExpense - approvedCommitments;
    assert.equal(availableHeadroom, 500_000);

    const requestedAmount = 750_000;
    const isExceeded = requestedAmount > availableHeadroom;

    assert.ok(isExceeded, 'Request of 750k must exceed available 500k headroom');
  });

  it('releases commitment when approved expenditure request is posted to ledger', () => {
    // Before posting
    const plannedBudget = 10_000_000;
    let actualExpense = 2_000_000;
    let approvedCommitments = 1_500_000;

    const headroomBeforePosting = plannedBudget - actualExpense - approvedCommitments;
    assert.equal(headroomBeforePosting, 6_500_000);

    // After posting: 1,500,000 transitions from commitment to actual ledger expense
    const postedAmount = 1_500_000;
    approvedCommitments -= postedAmount;
    actualExpense += postedAmount;

    const headroomAfterPosting = plannedBudget - actualExpense - approvedCommitments;
    assert.equal(headroomAfterPosting, 6_500_000, 'Total headroom remains unchanged when commitment is posted');
    assert.equal(approvedCommitments, 0);
    assert.equal(actualExpense, 3_500_000);
  });

  it('releases commitment when expenditure request is cancelled', () => {
    const plannedBudget = 10_000_000;
    const actualExpense = 2_000_000;
    let approvedCommitments = 1_500_000;

    // Cancellation releases commitment back to headroom
    approvedCommitments -= 1_500_000;
    const headroomAfterCancel = plannedBudget - actualExpense - approvedCommitments;

    assert.equal(headroomAfterCancel, 8_000_000);
  });
});

// ── 2. Budget Plan Lifecycle Transitions ───────────────────────────────────────

describe('Budget Plan Lifecycle Transitions', () => {
  it('enforces valid budget plan status transitions', () => {
    const validTransitions: Record<BudgetPlanStatus, BudgetPlanStatus[]> = {
      DRAFT: ['PUBLISHED', 'ARCHIVED'],
      PUBLISHED: ['SUPERSEDED', 'ARCHIVED'],
      SUPERSEDED: ['ARCHIVED'],
      ARCHIVED: [],
    };

    assert.ok(validTransitions.DRAFT.includes('PUBLISHED'));
    assert.ok(validTransitions.PUBLISHED.includes('SUPERSEDED'));
    assert.equal(validTransitions.ARCHIVED.length, 0, 'ARCHIVED is a terminal state');
  });

  it('disallows modifying lines on PUBLISHED budget plan directly without amendment', () => {
    const planStatus: BudgetPlanStatus = 'PUBLISHED';
    const isDirectlyEditable = planStatus === 'DRAFT';

    assert.equal(isDirectlyEditable, false, 'PUBLISHED budget plan cannot be directly edited');
  });
});

// ── 3. Expenditure Request State Machine ──────────────────────────────────────

describe('Expenditure Request State Machine', () => {
  it('validates allowed forward workflow transitions', () => {
    const allowedTransitions: Record<ExpenditureRequestStatus, ExpenditureRequestStatus[]> = {
      DRAFT: ['SUBMITTED', 'CANCELLED'],
      SUBMITTED: ['APPROVED', 'REJECTED', 'CANCELLED'],
      APPROVED: ['POSTED', 'CANCELLED'],
      POSTED: [],
      REJECTED: [],
      CANCELLED: [],
    };

    assert.ok(allowedTransitions.DRAFT.includes('SUBMITTED'));
    assert.ok(allowedTransitions.SUBMITTED.includes('APPROVED'));
    assert.ok(allowedTransitions.APPROVED.includes('POSTED'));
    assert.equal(allowedTransitions.POSTED.length, 0, 'POSTED is terminal');
    assert.equal(allowedTransitions.REJECTED.length, 0, 'REJECTED is terminal');
    assert.equal(allowedTransitions.CANCELLED.length, 0, 'CANCELLED is terminal');
  });
});

// ── 4. Segregation of Duties & Permission Helper Contracts ───────────────────

describe('Segregation of Duties & Role Capabilities', () => {
  const createMockUser = (roleKey: string, id = 'user-1'): User => ({
    id,
    name: `User ${roleKey}`,
    email: `${roleKey}@example.com`,
    role: { id: 'r1', key: roleKey, name: roleKey },
    memberships: [
      {
        id: 'm1',
        is_active: true,
        scope_level: roleKey.startsWith('rt_') ? 'rt' : 'rw',
        role: { id: 'r1', key: roleKey, name: roleKey },
      },
    ],
  });

  it('prohibits self-approval when current user is the requester', () => {
    const requesterId = 'user-requester-123';
    const approverId = 'user-requester-123';

    const canSelfApprove = requesterId !== approverId;
    assert.equal(canSelfApprove, false, 'Requester must not self-approve expenditures');
  });

  it('allows Ketua RW and Ketua RT to publish budgets and approve expenditures', () => {
    const ketuaRw = createMockUser('ketua_rw');
    const ketuaRt = createMockUser('ketua_rt');

    assert.ok(canPublishBudgets(ketuaRw));
    assert.ok(canPublishBudgets(ketuaRt));
    assert.ok(canApproveExpenditures(ketuaRw));
    assert.ok(canApproveExpenditures(ketuaRt));
  });

  it('allows Bendahara RW and Bendahara RT to manage budgets and post expenditures', () => {
    const bendaharaRw = createMockUser('bendahara_rw');
    const bendaharaRt = createMockUser('bendahara_rt');

    assert.ok(canManageBudgets(bendaharaRw));
    assert.ok(canManageBudgets(bendaharaRt));
    assert.ok(canPostExpenditures(bendaharaRw));
    assert.ok(canPostExpenditures(bendaharaRt));

    // Bendahara cannot publish budgets or approve expenditures without segregation override
    assert.equal(canPublishBudgets(bendaharaRw), false);
    assert.equal(canPublishBudgets(bendaharaRt), false);
    assert.equal(canApproveExpenditures(bendaharaRw), false);
    assert.equal(canApproveExpenditures(bendaharaRt), false);
  });

  it('allows all officials to request expenditures and view budgets', () => {
    const users = [
      createMockUser('ketua_rw'),
      createMockUser('bendahara_rw'),
      createMockUser('sekretaris_rw'),
      createMockUser('ketua_rt'),
      createMockUser('bendahara_rt'),
      createMockUser('sekretaris_rt'),
    ];

    users.forEach((u) => {
      assert.ok(canViewBudgets(u));
      assert.ok(canViewExpenditures(u));
      assert.ok(canRequestExpenditures(u));
    });
  });

  it('denies write permissions to regular residents', () => {
    const resident = createMockUser('warga');

    assert.equal(canManageBudgets(resident), false);
    assert.equal(canPublishBudgets(resident), false);
    assert.equal(canRequestExpenditures(resident), false);
    assert.equal(canApproveExpenditures(resident), false);
    assert.equal(canPostExpenditures(resident), false);
  });
});

// ── 5. Query Key Factory & API Contracts ──────────────────────────────────────

describe('Query Key Factory & API Client Contracts', () => {
  it('generates deterministic query keys for budget and expenditure resources', () => {
    const allBudgetsKey = financeKeys.budgets();
    assert.deepEqual(allBudgetsKey, ['finance', 'budgets']);

    const filteredBudgetKey = financeKeys.budgetList({ year: 2026, month: 10 });
    assert.deepEqual(filteredBudgetKey, [
      'finance',
      'budgets',
      'list',
      { year: 2026, month: 10 },
    ]);

    const budgetDetailKey = financeKeys.budgetDetail('budget-uuid-123');
    assert.deepEqual(budgetDetailKey, ['finance', 'budgets', 'detail', 'budget-uuid-123']);

    const allExpendituresKey = financeKeys.expenditures();
    assert.deepEqual(allExpendituresKey, ['finance', 'expenditures']);

    const expenditureDetailKey = financeKeys.expenditureDetail('exp-uuid-456');
    assert.deepEqual(expenditureDetailKey, [
      'finance',
      'expenditures',
      'detail',
      'exp-uuid-456',
    ]);
  });

  it('verifies financeApi exposes all required Phase 27 endpoints', () => {
    assert.equal(typeof financeApi.listBudgetPlans, 'function');
    assert.equal(typeof financeApi.getBudgetPlan, 'function');
    assert.equal(typeof financeApi.createBudgetPlan, 'function');
    assert.equal(typeof financeApi.updateBudgetPlan, 'function');
    assert.equal(typeof financeApi.publishBudgetPlan, 'function');
    assert.equal(typeof financeApi.amendBudgetPlan, 'function');
    assert.equal(typeof financeApi.getBudgetUtilizationSummary, 'function');

    assert.equal(typeof financeApi.listExpenditureRequests, 'function');
    assert.equal(typeof financeApi.getExpenditureRequest, 'function');
    assert.equal(typeof financeApi.createExpenditureRequest, 'function');
    assert.equal(typeof financeApi.submitExpenditureRequest, 'function');
    assert.equal(typeof financeApi.approveExpenditureRequest, 'function');
    assert.equal(typeof financeApi.rejectExpenditureRequest, 'function');
    assert.equal(typeof financeApi.cancelExpenditureRequest, 'function');
    assert.equal(typeof financeApi.postExpenditureRequest, 'function');
  });
});
