import { createBrowserRouter, Navigate } from 'react-router-dom';
import { AccountInactivePage } from '../../features/auth/pages/AccountInactivePage.tsx';
import { LoginPage } from '../../features/auth/pages/LoginPage.tsx';
import { HouseholdDetailPage } from '../../features/households/pages/HouseholdDetailPage.tsx';
import { HouseholdsPage } from '../../features/households/pages/HouseholdsPage.tsx';
import { HierarchyPage } from '../../features/hierarchy/pages/HierarchyPage.tsx';
import { OverviewDashboard } from '../../features/overview/pages/OverviewDashboard.tsx';
import { ResidentDetailPage } from '../../features/residents/pages/ResidentDetailPage.tsx';
import { ResidentsPage } from '../../features/residents/pages/ResidentsPage.tsx';
import {
  BudgetDetailPage,
  BudgetPlansPage,
  CashAccountDetailPage,
  CashTransferPage,
  ExpenditureDetailPage,
  ExpenditureRequestsPage,
  FinanceOverviewPage,
  FinanceReportsPage,
  PeriodClosurePage,
  TransactionDetailPage,
} from '../../features/finance/pages/index.ts';
import { AppShell } from '../layouts/AppShell.tsx';
import { ForbiddenPage } from './ForbiddenPage.tsx';
import { NotFoundPage } from './NotFoundPage.tsx';
import { ProtectedRoute } from './ProtectedRoute.tsx';
import { PublicRoute } from './PublicRoute.tsx';

export const router = createBrowserRouter([
  // Public auth route (redirects to /app if already authenticated)
  {
    element: <PublicRoute />,
    children: [
      {
        path: '/login',
        element: <LoginPage />,
      },
    ],
  },

  // Dedicated boundary routes
  {
    path: '/inactive',
    element: <AccountInactivePage />,
  },
  {
    path: '/unauthorized',
    element: <ForbiddenPage />,
  },

  // Root redirect to /app
  {
    path: '/',
    element: <Navigate to="/app" replace />,
  },

  // Protected application routes
  {
    path: '/app',
    element: <ProtectedRoute />,
    children: [
      {
        element: <AppShell />,
        children: [
          {
            index: true,
            element: <OverviewDashboard />,
          },
          {
            path: 'hierarchy',
            element: <HierarchyPage />,
          },
          {
            path: 'residents',
            element: <ResidentsPage />,
          },
          {
            path: 'residents/:residentId',
            element: <ResidentDetailPage />,
          },
          {
            path: 'households',
            element: <HouseholdsPage />,
          },
          {
            path: 'households/:householdId',
            element: <HouseholdDetailPage />,
          },
          {
            path: 'finance',
            element: <FinanceOverviewPage />,
          },
          {
            path: 'finance/reports',
            element: <FinanceReportsPage />,
          },
          {
            path: 'finance/periods',
            element: <PeriodClosurePage />,
          },
          {
            path: 'finance/transfers',
            element: <CashTransferPage />,
          },
          {
            path: 'finance/budgets',
            element: <BudgetPlansPage />,
          },
          {
            path: 'finance/budgets/:budgetId',
            element: <BudgetDetailPage />,
          },
          {
            path: 'finance/expenditures',
            element: <ExpenditureRequestsPage />,
          },
          {
            path: 'finance/expenditures/:requestId',
            element: <ExpenditureDetailPage />,
          },
          {
            path: 'finance/accounts/:accountId',
            element: <CashAccountDetailPage />,
          },
          {
            path: 'finance/transactions/:transactionId',
            element: <TransactionDetailPage />,
          },
          {
            path: '*',
            element: <NotFoundPage />,
          },
        ],
      },
    ],
  },

  // Catch-all 404
  {
    path: '*',
    element: <NotFoundPage />,
  },
]);
