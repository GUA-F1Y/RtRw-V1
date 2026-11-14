import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../../app/providers/useAuth.ts';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { useRwWithRts } from '../../hierarchy/api/hierarchyQueries.ts';
import {
  useCashAccounts,
  useExportFinanceReportCsv,
  useFinanceReportAccounts,
  useFinanceReportCashFlow,
  useFinanceReportCategories,
  useFinanceReportRtBreakdown,
  useFinanceReportSummary,
} from '../api/financeQueries.ts';
import { AccountComparisonCard } from '../components/AccountComparisonCard.tsx';
import { CashFlowTrendChart } from '../components/CashFlowTrendChart.tsx';
import { CategoryBreakdownCard } from '../components/CategoryBreakdownCard.tsx';
import { ReportPeriodFilters } from '../components/ReportPeriodFilters.tsx';
import { ReportSummaryCards } from '../components/ReportSummaryCards.tsx';
import { RtBreakdownCard } from '../components/RtBreakdownCard.tsx';
import { triggerBlobDownload } from '../utils/download.ts';
import type { ReportPeriodFilter } from '../../../types/financeReports.ts';

export const FinanceReportsPage: React.FC = () => {
  const { activeScope } = useAuth();
  const isRtUser = activeScope?.scopeLevel === 'rt';
  const rwId = activeScope?.rw?.id;
  const canFilterRt = !isRtUser;

  // Initialize with current month
  const [filters, setFilters] = useState<ReportPeriodFilter>(() => {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);

    return {
      date_from: firstDay.toISOString().split('T')[0],
      date_to: lastDay.toISOString().split('T')[0],
      interval: 'daily',
      cash_account_id: undefined,
      rt_id: isRtUser && activeScope?.rt?.id ? activeScope.rt.id : undefined,
    };
  });

  const [exportError, setExportError] = useState<string | null>(null);

  // Load auxiliary data for selectors
  const { data: accountsResponse } = useCashAccounts({ per_page: 100 });
  const accounts = accountsResponse?.data ?? [];

  const { data: rwData } = useRwWithRts(rwId || null);
  const rts = rwData?.rts ?? [];

  // Report Queries
  const summaryQuery = useFinanceReportSummary(filters);
  const cashFlowQuery = useFinanceReportCashFlow(filters);
  const categoriesQuery = useFinanceReportCategories(filters);
  const rtBreakdownQuery = useFinanceReportRtBreakdown(filters, { enabled: canFilterRt });
  const accountsQuery = useFinanceReportAccounts(filters);

  // CSV Export Mutation
  const exportMutation = useExportFinanceReportCsv();

  const handleExportCsv = async () => {
    setExportError(null);
    try {
      const res = await exportMutation.mutateAsync({ params: filters });
      triggerBlobDownload(res.blob, res.filename);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal mengekspor laporan CSV.';
      setExportError(msg);
    }
  };

  const hasAnyError =
    summaryQuery.isError ||
    cashFlowQuery.isError ||
    categoriesQuery.isError ||
    accountsQuery.isError;

  const errorMessage =
    summaryQuery.error?.message ||
    cashFlowQuery.error?.message ||
    categoriesQuery.error?.message ||
    accountsQuery.error?.message;

  return (
    <div className="space-y-6">
      {/* Page Header with Sub-navigation */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 text-xs text-slate-400 mb-1">
            <Link to="/app/finance" className="hover:text-emerald-400 transition-colors">
              Kas & Keuangan
            </Link>
            <span>/</span>
            <span className="text-slate-200">Laporan & Rekap Berkala</span>
          </div>
          <h1 className="text-2xl font-bold text-white tracking-tight">
            Laporan Keuangan & Rekapitulasi Kas
          </h1>
          <p className="text-xs text-slate-400 mt-1">
            Audit saldo, arus kas berkala, realisasi pos anggaran, dan ekspor CSV resmi tingkat{' '}
            {isRtUser ? 'RT' : 'RW'}
          </p>
        </div>

        {/* Navigation Tabs */}
        <div className="flex items-center gap-1.5 p-1 bg-slate-900 border border-slate-800 rounded-xl text-xs">
          <Link
            to="/app/finance"
            className="px-3 py-1.5 rounded-lg text-slate-400 hover:text-slate-200 transition-colors"
          >
            Buku Kas & Akun
          </Link>
          <span className="px-3 py-1.5 rounded-lg bg-slate-800 text-white font-medium shadow-sm">
            Laporan & Rekap
          </span>
          <Link
            to="/app/finance/periods"
            className="px-3 py-1.5 rounded-lg text-slate-400 hover:text-slate-200 transition-colors"
          >
            Tutup Periode
          </Link>
          <Link
            to="/app/finance/transfers"
            className="px-3 py-1.5 rounded-lg text-slate-400 hover:text-slate-200 transition-colors"
          >
            Transfer Kas
          </Link>
        </div>
      </div>

      {exportError && (
        <Alert
          type="error"
          title="Gagal Mengunduh CSV"
          message={exportError}
        />
      )}

      {hasAnyError && errorMessage && (
        <Alert
          type="error"
          title="Gagal Memuat Data Laporan"
          message={errorMessage}
        />
      )}

      {/* Filter Controls Bar */}
      <ReportPeriodFilters
        filters={filters}
        onChange={setFilters}
        onExportCsv={handleExportCsv}
        isExporting={exportMutation.isPending}
        accounts={accounts}
        rts={rts}
        canFilterRt={canFilterRt}
      />

      {/* 5-KPI Authoritative Summary Cards */}
      <ReportSummaryCards
        summary={summaryQuery.data?.data}
        isLoading={summaryQuery.isLoading}
      />

      {/* Cash Flow Trend Chart */}
      <CashFlowTrendChart
        buckets={cashFlowQuery.data?.data?.buckets ?? []}
        interval={filters.interval || 'daily'}
        isLoading={cashFlowQuery.isLoading}
      />

      {/* Dual Column: Categories Distribution & RT Breakdown */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <CategoryBreakdownCard
          data={categoriesQuery.data?.data}
          isLoading={categoriesQuery.isLoading}
        />

        {canFilterRt ? (
          <RtBreakdownCard
            data={rtBreakdownQuery.data?.data}
            isLoading={rtBreakdownQuery.isLoading}
          />
        ) : (
          <AccountComparisonCard
            data={accountsQuery.data?.data}
            isLoading={accountsQuery.isLoading}
          />
        )}
      </div>

      {/* Full Width Account Comparison if RT Breakdown was shown above */}
      {canFilterRt && (
        <AccountComparisonCard
          data={accountsQuery.data?.data}
          isLoading={accountsQuery.isLoading}
        />
      )}
    </div>
  );
};
