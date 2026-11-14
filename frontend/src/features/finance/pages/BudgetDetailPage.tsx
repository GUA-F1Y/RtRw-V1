import React, { useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useAuth } from '../../../app/providers/useAuth.ts';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Badge } from '../../../components/ui/Badge.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { useBudgetPlan, useExpenditureRequests } from '../api/financeQueries.ts';
import {
  AmendBudgetDialog,
  BudgetLinesTable,
  BudgetStatusBadge,
  CreateExpenditureRequestDialog,
  EditBudgetPlanDialog,
  ExpenditureStatusBadge,
  PublishBudgetDialog,
} from '../components/index.ts';
import { formatRupiah } from '../utils/currency.ts';
import {
  canManageBudgets,
  canPublishBudgets,
  canRequestExpenditures,
} from '../utils/permissions.ts';

export const BudgetDetailPage: React.FC = () => {
  const { budgetId } = useParams<{ budgetId: string }>();
  const { user } = useAuth();

  const canManage = canManageBudgets(user);
  const canPublish = canPublishBudgets(user);
  const canRequest = canRequestExpenditures(user);

  // Modals state
  const [editOpen, setEditOpen] = useState(false);
  const [publishOpen, setPublishOpen] = useState(false);
  const [amendOpen, setAmendOpen] = useState(false);
  const [expenditureOpen, setExpenditureOpen] = useState(false);
  const [selectedLine, setSelectedLine] = useState<import('../../../types/finance.ts').BudgetLine | null>(null);

  const {
    data: planResponse,
    isLoading,
    error,
    refetch,
  } = useBudgetPlan(budgetId || '');

  const plan = planResponse?.data;

  // Fetch expenditure requests for this budget period
  const { data: expendituresResponse } = useExpenditureRequests(
    plan ? { start_date: `${plan.year}-${String(plan.month).padStart(2, '0')}-01` } : undefined,
    { enabled: Boolean(plan) }
  );

  const expendituresList = expendituresResponse?.data;
  const planLines = plan?.lines;

  const relatedExpenditures = useMemo(() => {
    if (!expendituresList || !planLines) return [];
    const lineIds = new Set(planLines.map((l) => l.id));
    return expendituresList.filter((req) => lineIds.has(req.budget_line_id));
  }, [expendituresList, planLines]);

  if (isLoading) {
    return (
      <div className="p-12 text-center text-xs text-slate-400">
        Memuat detail rencana anggaran...
      </div>
    );
  }

  if (error || !plan) {
    return (
      <div className="space-y-4">
        <Link
          to="/app/finance/budgets"
          className="inline-flex items-center gap-1.5 text-xs text-indigo-400 hover:text-indigo-300 transition-colors"
        >
          ← Kembali ke Daftar Anggaran
        </Link>
        <Alert type="error" message={error?.message || 'Rencana anggaran tidak ditemukan.'} />
      </div>
    );
  }

  const scopeLabel = plan.rt
    ? `RT ${plan.rt.number}`
    : `RW ${plan.rw?.number || ''}`;

  const calc = plan.calculation;
  const totalPlanned = plan.total_planned_amount ?? 0;
  const totalActual = calc?.total_actual_expense ?? 0;
  const totalCommitted = calc?.total_approved_commitment ?? 0;
  const totalAvailable = calc?.total_available_budget ?? totalPlanned;

  return (
    <div className="space-y-6">
      {/* Back button & Header */}
      <div>
        <Link
          to="/app/finance/budgets"
          className="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-slate-200 transition-colors mb-3"
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          Kembali ke Daftar Anggaran
        </Link>

        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
          <div className="space-y-1">
            <div className="flex items-center gap-2.5">
              <h1 className="text-xl font-bold text-white tracking-tight">
                RAPB Periode {String(plan.month).padStart(2, '0')}/{plan.year}
              </h1>
              <Badge variant="indigo" size="sm">
                {scopeLabel}
              </Badge>
              <span className="px-2 py-0.5 rounded bg-slate-800 text-slate-300 font-mono text-xs">
                Versi {plan.version}
              </span>
              <BudgetStatusBadge status={plan.status} />
            </div>
            <p className="text-xs text-slate-400">
              Dokumen pagu anggaran belanja operasional bulanan dengan kontrol komitmen dan realisasi buku kas.
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            {plan.status === 'DRAFT' && canManage && (
              <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={() => setEditOpen(true)}
              >
                Ubah Draft
              </Button>
            )}

            {plan.status === 'DRAFT' && canPublish && (
              <Button
                type="button"
                variant="primary"
                size="sm"
                onClick={() => setPublishOpen(true)}
              >
                Publikasikan Pagu
              </Button>
            )}

            {plan.status === 'PUBLISHED' && canManage && (
              <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={() => setAmendOpen(true)}
              >
                Buat Perubahan (RAPB-P)
              </Button>
            )}

            {plan.status === 'PUBLISHED' && canRequest && (
              <Button
                type="button"
                variant="primary"
                size="sm"
                onClick={() => {
                  setSelectedLine(null);
                  setExpenditureOpen(true);
                }}
              >
                + Ajukan Belanja
              </Button>
            )}
          </div>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm">
          <div className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
            Total Pagu Anggaran
          </div>
          <div className="mt-2 text-xl font-mono font-bold text-white">
            {formatRupiah(totalPlanned)}
          </div>
          <p className="mt-1 text-[11px] text-slate-500">
            {plan.lines?.length || 0} pos alokasi belanja disetujui
          </p>
        </div>

        <div className="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm">
          <div className="text-[11px] font-semibold text-rose-400 uppercase tracking-wider">
            Realisasi Aktual
          </div>
          <div className="mt-2 text-xl font-mono font-bold text-rose-300">
            {formatRupiah(totalActual)}
          </div>
          <p className="mt-1 text-[11px] text-slate-500">
            Transaksi belanja operasional buku kas
          </p>
        </div>

        <div className="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm">
          <div className="text-[11px] font-semibold text-amber-400 uppercase tracking-wider">
            Komitmen Disetujui
          </div>
          <div className="mt-2 text-xl font-mono font-bold text-amber-300">
            {formatRupiah(totalCommitted)}
          </div>
          <p className="mt-1 text-[11px] text-slate-500">
            Pengajuan belanja disetujui (belum posting)
          </p>
        </div>

        <div className="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm">
          <div className="text-[11px] font-semibold text-emerald-400 uppercase tracking-wider">
            Sisa Pagu Tersedia
          </div>
          <div className="mt-2 text-xl font-mono font-bold text-emerald-400">
            {formatRupiah(totalAvailable)}
          </div>
          <p className="mt-1 text-[11px] text-slate-500">
            Headroom untuk pengajuan belanja baru
          </p>
        </div>
      </div>

      {/* Meta Information Card */}
      <div className="bg-slate-900/60 border border-slate-800 rounded-xl p-4 text-xs space-y-3">
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
          <div>
            <span className="text-slate-400 block text-[11px]">Dibuat Oleh</span>
            <span className="text-slate-200 font-medium">{plan.creator?.name || '-'}</span>
            <span className="text-[10px] text-slate-500 block">
              {new Date(plan.created_at).toLocaleString('id-ID')}
            </span>
          </div>
          <div>
            <span className="text-slate-400 block text-[11px]">Dipublikasikan Oleh</span>
            <span className="text-slate-200 font-medium">{plan.publisher?.name || '-'}</span>
            <span className="text-[10px] text-slate-500 block">
              {plan.published_at ? new Date(plan.published_at).toLocaleString('id-ID') : 'Belum dipublikasikan'}
            </span>
          </div>
          <div>
            <span className="text-slate-400 block text-[11px]">Cakupan Entitas</span>
            <span className="text-slate-200 font-medium">{scopeLabel}</span>
          </div>
          <div>
            <span className="text-slate-400 block text-[11px]">ID Dokumen</span>
            <span className="text-slate-400 font-mono text-[10px] truncate block">
              {plan.id}
            </span>
          </div>
        </div>

        {plan.notes && (
          <div className="pt-2 border-t border-slate-800/80">
            <span className="text-slate-400 block text-[11px] mb-0.5">Catatan / Justifikasi:</span>
            <p className="text-slate-300 leading-relaxed italic bg-slate-950/40 p-2.5 rounded-lg border border-slate-800/60">
              &quot;{plan.notes}&quot;
            </p>
          </div>
        )}
      </div>

      {/* Pos Anggaran Table */}
      <div className="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div className="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
          <div>
            <h2 className="text-sm font-semibold text-white">
              Rincian Pagu Anggaran per Kategori Belanja
            </h2>
            <p className="text-xs text-slate-400 mt-0.5">
              Alokasi batas belanja, beban kas terealisasi, dan sisa pagu tersedia
            </p>
          </div>
          {plan.status === 'PUBLISHED' && canRequest && (
            <span className="text-[11px] text-indigo-400">
              Klik &quot;Ajukan Belanja&quot; pada baris untuk membuat pengajuan
            </span>
          )}
        </div>

        <BudgetLinesTable
          lines={plan.lines || []}
          canRequest={plan.status === 'PUBLISHED' && canRequest}
          isPublished={plan.status === 'PUBLISHED'}
          onAddExpenditure={(line) => {
            setSelectedLine(line);
            setExpenditureOpen(true);
          }}
        />
      </div>

      {/* Related Expenditure Requests Table */}
      <div className="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div className="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <h2 className="text-sm font-semibold text-white">
              Pengajuan Belanja Terkait ({relatedExpenditures.length})
            </h2>
          </div>
          <Link
            to="/app/finance/expenditures"
            className="text-xs text-indigo-400 hover:text-indigo-300 transition-colors"
          >
            Lihat Semua Pengajuan Belanja →
          </Link>
        </div>

        {relatedExpenditures.length === 0 ? (
          <div className="p-8 text-center text-xs text-slate-400">
            Belum ada pengajuan belanja yang diterbitkan untuk periode anggaran ini.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-slate-300">
              <thead className="bg-slate-950/60 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800">
                <tr>
                  <th className="py-3 px-4">Judul Pengajuan</th>
                  <th className="py-3 px-4">Kategori Belanja</th>
                  <th className="py-3 px-4">Pemohon</th>
                  <th className="py-3 px-4">Status</th>
                  <th className="py-3 px-4 text-right">Nominal</th>
                  <th className="py-3 px-4 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60">
                {relatedExpenditures.map((req) => (
                  <tr key={req.id} className="hover:bg-slate-800/40 transition-colors font-mono">
                    <td className="py-3 px-4 font-sans font-medium text-white">
                      <Link
                        to={`/app/finance/expenditures/${req.id}`}
                        className="hover:text-indigo-400 transition-colors"
                      >
                        {req.title}
                      </Link>
                    </td>
                    <td className="py-3 px-4 font-sans text-slate-300">
                      {req.budget_line?.category?.code} - {req.budget_line?.category?.name}
                    </td>
                    <td className="py-3 px-4 font-sans text-slate-400">
                      {req.requester?.name || '-'}
                    </td>
                    <td className="py-3 px-4 font-sans">
                      <ExpenditureStatusBadge status={req.status} />
                    </td>
                    <td className="py-3 px-4 text-right font-semibold text-white">
                      {formatRupiah(req.amount ?? req.proposed_amount)}
                    </td>
                    <td className="py-3 px-4 text-center font-sans">
                      <Link
                        to={`/app/finance/expenditures/${req.id}`}
                        className="px-2 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs transition-colors"
                      >
                        Lihat
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Dialogs */}
      <EditBudgetPlanDialog
        open={editOpen}
        plan={plan}
        onClose={() => setEditOpen(false)}
        onSuccess={() => refetch()}
      />

      <PublishBudgetDialog
        open={publishOpen}
        plan={plan}
        onClose={() => setPublishOpen(false)}
        onSuccess={() => refetch()}
      />

      <AmendBudgetDialog
        open={amendOpen}
        plan={plan}
        onClose={() => setAmendOpen(false)}
        onSuccess={() => refetch()}
      />

      <CreateExpenditureRequestDialog
        open={expenditureOpen}
        plan={plan}
        initialLine={selectedLine}
        onClose={() => setExpenditureOpen(false)}
        onSuccess={() => refetch()}
      />
    </div>
  );
};
