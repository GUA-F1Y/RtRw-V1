import React, { useId, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../../app/providers/useAuth.ts';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Badge } from '../../../components/ui/Badge.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import type { BudgetPlan, BudgetPlanFilterParams, BudgetPlanStatus } from '../../../types/finance.ts';
import { useRwWithRts } from '../../hierarchy/api/hierarchyQueries.ts';
import { useBudgetPlans, useBudgetUtilizationSummary } from '../api/financeQueries.ts';
import {
  AmendBudgetDialog,
  BudgetStatusBadge,
  BudgetUtilizationSummaryCard,
  CreateBudgetPlanDialog,
  PublishBudgetDialog,
} from '../components/index.ts';
import { formatRupiah } from '../utils/currency.ts';
import {
  canManageBudgets,
  canPublishBudgets,
} from '../utils/permissions.ts';

const CURRENT_YEAR = new Date().getFullYear();

export const BudgetPlansPage: React.FC = () => {
  const navigate = useNavigate();
  const { user, activeScope } = useAuth();

  const isRtScope = activeScope?.scopeLevel === 'rt';
  const rwId = activeScope?.rw?.id;
  const { data: rwData } = useRwWithRts(rwId || null);
  const rtUnits = rwData?.rts || [];

  const canManage = canManageBudgets(user);
  const canPublish = canPublishBudgets(user);

  // Filters
  const [year, setYear] = useState<number>(CURRENT_YEAR);
  const [month, setMonth] = useState<string>('');
  const [status, setStatus] = useState<string>('');
  const [scopeType, setScopeType] = useState<string>('');
  const [rtId, setRtId] = useState<string>('');

  const yearId = useId();
  const monthId = useId();
  const statusId = useId();
  const scopeTypeId = useId();
  const rtFilterId = useId();

  // Modals
  const [createOpen, setCreateOpen] = useState(false);
  const [publishingPlan, setPublishingPlan] = useState<BudgetPlan | null>(null);
  const [amendingPlan, setAmendingPlan] = useState<BudgetPlan | null>(null);

  const queryParams: BudgetPlanFilterParams = useMemo(() => {
    const params: BudgetPlanFilterParams = { year };
    if (month) params.month = Number(month);
    if (status) params.status = status as BudgetPlanStatus;
    if (scopeType === 'RW' || scopeType === 'RT') params.scope_type = scopeType;
    if (rtId) params.rt_id = rtId;
    return params;
  }, [year, month, status, scopeType, rtId]);

  const {
    data: budgetsResponse,
    isLoading: budgetsLoading,
    error: budgetsError,
    refetch,
  } = useBudgetPlans(queryParams);

  const {
    data: utilizationResponse,
    isLoading: utilizationLoading,
  } = useBudgetUtilizationSummary({
    year,
    month: month ? Number(month) : undefined,
    rt_id: rtId || undefined,
  });

  const budgetPlans = useMemo(() => budgetsResponse?.data || [], [budgetsResponse?.data]);
  const utilization = utilizationResponse?.data;

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
        <div>
          <div className="flex items-center gap-2.5">
            <h1 className="text-xl font-bold text-white tracking-tight">Rencana Anggaran (RAPB)</h1>
            <Badge variant="indigo" size="sm">
              {isRtScope ? 'Cakupan RT' : 'Cakupan RW'}
            </Badge>
          </div>
          <p className="text-xs text-slate-400 mt-1">
            Pengelolaan alokasi pagu anggaran belanja bulanan, versi perbaikan, dan pengawasan realisasi kas operasional.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Link
            to="/app/finance/expenditures"
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white border border-slate-700/80 transition-colors"
          >
            <svg className="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Pengajuan Belanja
          </Link>
          <Link
            to="/app/finance"
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white border border-slate-700/80 transition-colors"
          >
            Ringkasan Keuangan
          </Link>
          {canManage && (
            <Button
              type="button"
              variant="primary"
              size="sm"
              onClick={() => setCreateOpen(true)}
              className="gap-1.5"
            >
              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
              </svg>
              Buat RAPB Baru
            </Button>
          )}
        </div>
      </div>

      {/* Utilization Summary Card */}
      <BudgetUtilizationSummaryCard
        summary={utilization?.summary}
        isLoading={utilizationLoading}
      />

      {/* Filters Bar */}
      <div className="bg-slate-900/60 p-4 rounded-xl border border-slate-800 flex flex-wrap items-center gap-3">
        {/* Year */}
        <div className="w-28">
          <label htmlFor={yearId} className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
            Tahun
          </label>
          <input
            id={yearId}
            type="number"
            value={year}
            onChange={(e) => setYear(Number(e.target.value) || CURRENT_YEAR)}
            className="w-full bg-slate-800 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono"
          />
        </div>

        {/* Month */}
        <div className="w-36">
          <label htmlFor={monthId} className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
            Bulan
          </label>
          <select
            id={monthId}
            value={month}
            onChange={(e) => setMonth(e.target.value)}
            className="w-full bg-slate-800 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500"
          >
            <option value="">Semua Bulan</option>
            {Array.from({ length: 12 }, (_, i) => i + 1).map((m) => (
              <option key={m} value={m}>
                Bulan {m}
              </option>
            ))}
          </select>
        </div>

        {/* Status */}
        <div className="w-36">
          <label htmlFor={statusId} className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
            Status
          </label>
          <select
            id={statusId}
            value={status}
            onChange={(e) => setStatus(e.target.value)}
            className="w-full bg-slate-800 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500"
          >
            <option value="">Semua Status</option>
            <option value="DRAFT">DRAFT</option>
            <option value="PUBLISHED">PUBLISHED</option>
            <option value="SUPERSEDED">SUPERSEDED</option>
            <option value="ARCHIVED">ARCHIVED</option>
          </select>
        </div>

        {/* Scope Type */}
        {!isRtScope && (
          <div className="w-36">
            <label htmlFor={scopeTypeId} className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
              Tingkat
            </label>
            <select
              id={scopeTypeId}
              value={scopeType}
              onChange={(e) => {
                setScopeType(e.target.value);
                if (e.target.value !== 'RT') setRtId('');
              }}
              className="w-full bg-slate-800 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500"
            >
              <option value="">Semua Tingkat</option>
              <option value="RW">Hanya RW</option>
              <option value="RT">Hanya RT</option>
            </select>
          </div>
        )}

        {/* RT unit filter */}
        {!isRtScope && scopeType === 'RT' && (
          <div className="w-40">
            <label htmlFor={rtFilterId} className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
              Unit RT
            </label>
            <select
              id={rtFilterId}
              value={rtId}
              onChange={(e) => setRtId(e.target.value)}
              className="w-full bg-slate-800 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500"
            >
              <option value="">Semua RT</option>
              {rtUnits.map((rt) => (
                <option key={rt.id} value={rt.id}>
                  RT {rt.number}
                </option>
              ))}
            </select>
          </div>
        )}

        <div className="ml-auto pt-4">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={() => refetch()}
            className="text-xs"
          >
            Segarkan
          </Button>
        </div>
      </div>

      {/* Error state */}
      {budgetsError && (
        <Alert type="error" message={budgetsError.message || 'Gagal memuat daftar rencana anggaran.'} />
      )}

      {/* Plans List / Table */}
      <div className="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div className="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
          <h2 className="text-sm font-semibold text-white">
            Daftar Dokumen Anggaran ({budgetPlans.length})
          </h2>
          <span className="text-xs text-slate-400">
            Pagu aktif berlaku untuk pengajuan belanja bulanan
          </span>
        </div>

        {budgetsLoading ? (
          <div className="p-8 text-center text-xs text-slate-400">
            Memuat daftar anggaran...
          </div>
        ) : budgetPlans.length === 0 ? (
          <div className="p-12 text-center space-y-3">
            <div className="w-12 h-12 mx-auto rounded-full bg-slate-800/80 flex items-center justify-center text-slate-500">
              <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <p className="text-xs text-slate-400">
              Belum ada rencana anggaran untuk kriteria pencarian ini.
            </p>
            {canManage && (
              <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={() => setCreateOpen(true)}
              >
                Buat RAPB Pertama
              </Button>
            )}
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-slate-300">
              <thead className="bg-slate-950/60 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800">
                <tr>
                  <th className="py-3 px-4">Periode</th>
                  <th className="py-3 px-4">Tingkat</th>
                  <th className="py-3 px-4 text-center">Versi</th>
                  <th className="py-3 px-4">Status</th>
                  <th className="py-3 px-4 text-right">Total Pagu</th>
                  <th className="py-3 px-4 text-right">Realisasi Aktual</th>
                  <th className="py-3 px-4 text-right">Sisa Pagu</th>
                  <th className="py-3 px-4 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60 font-mono">
                {budgetPlans.map((plan) => {
                  const scopeLabel = plan.rt
                    ? `RT ${plan.rt.number}`
                    : `RW ${plan.rw?.number || ''}`;

                  const totalPlanned = plan.total_planned_amount ?? 0;
                  const totalActual = plan.calculation?.total_actual_expense ?? 0;
                  const available = plan.calculation?.total_available_budget ?? totalPlanned;

                  return (
                    <tr
                      key={plan.id}
                      className="hover:bg-slate-800/40 transition-colors"
                    >
                      <td className="py-3.5 px-4 font-sans font-medium text-white">
                        <Link
                          to={`/app/finance/budgets/${plan.id}`}
                          className="hover:text-indigo-400 transition-colors"
                        >
                          {String(plan.month).padStart(2, '0')}/{plan.year}
                        </Link>
                        {plan.notes && (
                          <p className="text-[11px] text-slate-400 font-normal line-clamp-1">
                            {plan.notes}
                          </p>
                        )}
                      </td>
                      <td className="py-3.5 px-4 font-sans">
                        <span className="text-slate-200">{scopeLabel}</span>
                      </td>
                      <td className="py-3.5 px-4 text-center">
                        <span className="px-1.5 py-0.5 rounded bg-slate-800 text-slate-300 text-[11px]">
                          v{plan.version}
                        </span>
                      </td>
                      <td className="py-3.5 px-4 font-sans">
                        <BudgetStatusBadge status={plan.status} />
                      </td>
                      <td className="py-3.5 px-4 text-right font-semibold text-white">
                        {formatRupiah(totalPlanned)}
                      </td>
                      <td className="py-3.5 px-4 text-right text-rose-300">
                        {formatRupiah(totalActual)}
                      </td>
                      <td className="py-3.5 px-4 text-right font-semibold text-emerald-400">
                        {formatRupiah(available)}
                      </td>
                      <td className="py-3.5 px-4 text-center font-sans">
                        <div className="flex items-center justify-center gap-1.5">
                          <Link
                            to={`/app/finance/budgets/${plan.id}`}
                            className="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium transition-colors"
                          >
                            Detail
                          </Link>

                          {plan.status === 'DRAFT' && canPublish && (
                            <Button
                              type="button"
                              variant="primary"
                              size="sm"
                              onClick={() => setPublishingPlan(plan)}
                              className="text-[11px] py-1 px-2.5"
                            >
                              Publikasikan
                            </Button>
                          )}

                          {plan.status === 'PUBLISHED' && canManage && (
                            <Button
                              type="button"
                              variant="secondary"
                              size="sm"
                              onClick={() => setAmendingPlan(plan)}
                              className="text-[11px] py-1 px-2.5"
                            >
                              Perubahan (RAPB-P)
                            </Button>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Dialogs */}
      <CreateBudgetPlanDialog
        open={createOpen}
        onClose={() => setCreateOpen(false)}
        onSuccess={(id) => navigate(`/app/finance/budgets/${id}`)}
      />

      <PublishBudgetDialog
        open={Boolean(publishingPlan)}
        plan={publishingPlan}
        onClose={() => setPublishingPlan(null)}
        onSuccess={() => refetch()}
      />

      <AmendBudgetDialog
        open={Boolean(amendingPlan)}
        plan={amendingPlan}
        onClose={() => setAmendingPlan(null)}
        onSuccess={(newPlanId) => navigate(`/app/finance/budgets/${newPlanId}`)}
      />
    </div>
  );
};
