import React, { useId, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../../app/providers/useAuth.ts';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Badge } from '../../../components/ui/Badge.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type { ExpenditureRequest, ExpenditureRequestFilterParams, ExpenditureRequestStatus } from '../../../types/finance.ts';
import {
  useExpenditureRequests,
  useSubmitExpenditureRequest,
} from '../api/financeQueries.ts';
import {
  ApproveExpenditureDialog,
  CancelExpenditureDialog,
  CreateExpenditureRequestDialog,
  ExpenditureStatusBadge,
  PostExpenditureDialog,
  RejectExpenditureDialog,
} from '../components/index.ts';
import { formatRupiah } from '../utils/currency.ts';
import {
  canApproveExpenditures,
  canPostExpenditures,
  canRequestExpenditures,
} from '../utils/permissions.ts';

const STATUS_TABS: Array<{ label: string; value: ExpenditureRequestStatus | '' }> = [
  { label: 'Semua Status', value: '' },
  { label: 'Draft', value: 'DRAFT' },
  { label: 'Menunggu Persetujuan', value: 'SUBMITTED' },
  { label: 'Disetujui (Approved)', value: 'APPROVED' },
  { label: 'Diposting (Posted)', value: 'POSTED' },
  { label: 'Ditolak', value: 'REJECTED' },
  { label: 'Dibatalkan', value: 'CANCELLED' },
];

export const ExpenditureRequestsPage: React.FC = () => {
  const { user, activeScope } = useAuth();

  const canRequest = canRequestExpenditures(user);
  const canApprove = canApproveExpenditures(user);
  const canPost = canPostExpenditures(user);

  // Filters state
  const [selectedStatus, setSelectedStatus] = useState<ExpenditureRequestStatus | ''>('');
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [startDate, setStartDate] = useState<string>('');
  const [endDate, setEndDate] = useState<string>('');

  const searchInputId = useId();
  const startDateId = useId();
  const endDateId = useId();

  // Modals state
  const [createOpen, setCreateOpen] = useState(false);
  const [approvingReq, setApprovingReq] = useState<ExpenditureRequest | null>(null);
  const [rejectingReq, setRejectingReq] = useState<ExpenditureRequest | null>(null);
  const [cancellingReq, setCancellingReq] = useState<ExpenditureRequest | null>(null);
  const [postingReq, setPostingReq] = useState<ExpenditureRequest | null>(null);

  const submitMutation = useSubmitExpenditureRequest();

  const queryParams: ExpenditureRequestFilterParams = useMemo(() => {
    const params: ExpenditureRequestFilterParams = {};
    if (selectedStatus) params.status = selectedStatus;
    if (searchQuery.trim()) params.q = searchQuery.trim();
    if (startDate) params.start_date = startDate;
    if (endDate) params.end_date = endDate;
    return params;
  }, [selectedStatus, searchQuery, startDate, endDate]);

  const {
    data: requestsResponse,
    isLoading,
    error,
    refetch,
  } = useExpenditureRequests(queryParams);

  const requests = useMemo(() => requestsResponse?.data || [], [requestsResponse?.data]);

  // Summary counts
  const summaryStats = useMemo(() => {
    let pendingApprovalCount = 0;
    let approvedPendingPostCount = 0;
    let totalCommittedAmount = 0;

    requests.forEach((req) => {
      if (req.status === 'SUBMITTED') pendingApprovalCount++;
      if (req.status === 'APPROVED') {
        approvedPendingPostCount++;
        totalCommittedAmount += (req.amount ?? req.proposed_amount);
      }
    });

    return {
      total: requests.length,
      pendingApprovalCount,
      approvedPendingPostCount,
      totalCommittedAmount,
    };
  }, [requests]);

  const handleSubmitDraft = async (req: ExpenditureRequest) => {
    if (!window.confirm(`Kirim pengajuan "${req.title}" untuk persetujuan ketua?`)) return;
    try {
      await submitMutation.mutateAsync({
        id: req.id,
        idempotencyKey: generateIdempotencyKey(),
      });
      refetch();
    } catch {
      // Handled by query error or alert
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
        <div>
          <div className="flex items-center gap-2.5">
            <h1 className="text-xl font-bold text-white tracking-tight">Pengajuan Belanja</h1>
            <Badge variant="indigo" size="sm">
              {activeScope?.scopeLevel === 'rt' ? 'Cakupan RT' : 'Cakupan RW'}
            </Badge>
          </div>
          <p className="text-xs text-slate-400 mt-1">
            Alur kerja persetujuan belanja berjenjang dengan pembatasan pagu anggaran dan pemisahan kewenangan (segregation of duties).
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Link
            to="/app/finance/budgets"
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white border border-slate-700/80 transition-colors"
          >
            <svg className="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            Rencana Anggaran (RAPB)
          </Link>
          <Link
            to="/app/finance"
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white border border-slate-700/80 transition-colors"
          >
            Buku Kas
          </Link>
          {canRequest && (
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
              Ajukan Belanja Baru
            </Button>
          )}
        </div>
      </div>

      {/* KPI Summary Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm">
          <div className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
            Total Pengajuan Terdaftar
          </div>
          <div className="mt-2 text-2xl font-mono font-bold text-white">
            {summaryStats.total}
          </div>
          <p className="mt-1 text-[11px] text-slate-500">
            Seluruh riwayat pengajuan dalam cakupan
          </p>
        </div>

        <div className="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm">
          <div className="text-[11px] font-semibold text-amber-400 uppercase tracking-wider">
            Menunggu Persetujuan
          </div>
          <div className="mt-2 text-2xl font-mono font-bold text-amber-300">
            {summaryStats.pendingApprovalCount}
          </div>
          <p className="mt-1 text-[11px] text-slate-500">
            Perlu ditinjau oleh Ketua RW / Ketua RT
          </p>
        </div>

        <div className="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-sm">
          <div className="text-[11px] font-semibold text-emerald-400 uppercase tracking-wider">
            Siap Diposting (Disetujui)
          </div>
          <div className="mt-2 text-2xl font-mono font-bold text-emerald-400">
            {summaryStats.approvedPendingPostCount}
          </div>
          <p className="mt-1 text-[11px] text-slate-500 font-mono">
            Komitmen: {formatRupiah(summaryStats.totalCommittedAmount)}
          </p>
        </div>
      </div>

      {/* Status Filter Tabs */}
      <div className="flex items-center gap-1.5 overflow-x-auto pb-1 border-b border-slate-800 text-xs">
        {STATUS_TABS.map((tab) => {
          const isActive = selectedStatus === tab.value;
          return (
            <button
              key={tab.value}
              type="button"
              onClick={() => setSelectedStatus(tab.value)}
              className={`px-3 py-1.5 rounded-lg font-medium whitespace-nowrap transition-colors ${
                isActive
                  ? 'bg-indigo-600 text-white shadow-sm'
                  : 'bg-slate-900/60 text-slate-400 hover:text-white hover:bg-slate-800'
              }`}
            >
              {tab.label}
            </button>
          );
        })}
      </div>

      {/* Search & Date Filter Bar */}
      <div className="bg-slate-900/60 p-4 rounded-xl border border-slate-800 flex flex-wrap items-center gap-3">
        <div className="flex-1 min-w-[200px]">
          <label htmlFor={searchInputId} className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
            Cari Pengajuan
          </label>
          <input
            id={searchInputId}
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Cari nomor pengajuan, judul keperluan, nama vendor..."
            className="w-full bg-slate-800 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500"
          />
        </div>

        <div className="w-36">
          <label htmlFor={startDateId} className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
            Dari Tanggal
          </label>
          <input
            id={startDateId}
            type="date"
            value={startDate}
            onChange={(e) => setStartDate(e.target.value)}
            className="w-full bg-slate-800 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500"
          />
        </div>

        <div className="w-36">
          <label htmlFor={endDateId} className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
            Sampai Tanggal
          </label>
          <input
            id={endDateId}
            type="date"
            value={endDate}
            onChange={(e) => setEndDate(e.target.value)}
            className="w-full bg-slate-800 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500"
          />
        </div>

        <div className="pt-4">
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

      {error && (
        <Alert type="error" message={error.message || 'Gagal memuat daftar pengajuan belanja.'} />
      )}

      {/* Requests Table */}
      <div className="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div className="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
          <h2 className="text-sm font-semibold text-white">
            Daftar Pengajuan ({requests.length})
          </h2>
          <span className="text-xs text-slate-400">
            Klik nomor pengajuan untuk melihat audit trail dan riwayat persetujuan
          </span>
        </div>

        {isLoading ? (
          <div className="p-8 text-center text-xs text-slate-400">
            Memuat pengajuan belanja...
          </div>
        ) : requests.length === 0 ? (
          <div className="p-12 text-center space-y-3">
            <div className="w-12 h-12 mx-auto rounded-full bg-slate-800/80 flex items-center justify-center text-slate-500">
              <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
            </div>
            <p className="text-xs text-slate-400">
              Tidak ada pengajuan belanja yang sesuai filter.
            </p>
            {canRequest && (
              <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={() => setCreateOpen(true)}
              >
                Ajukan Belanja Baru
              </Button>
            )}
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-slate-300">
              <thead className="bg-slate-950/60 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800">
                <tr>
                  <th className="py-3 px-4">Nomor & Tanggal</th>
                  <th className="py-3 px-4">Judul Keperluan</th>
                  <th className="py-3 px-4">Pos Anggaran</th>
                  <th className="py-3 px-4">Pemohon</th>
                  <th className="py-3 px-4">Status</th>
                  <th className="py-3 px-4 text-right">Nominal</th>
                  <th className="py-3 px-4 text-center">Aksi Alur Kerja</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60">
                {requests.map((req) => {
                  const isRequester = user?.id === (req.requester_user_id || req.requested_by);

                  return (
                    <tr key={req.id} className="hover:bg-slate-800/40 transition-colors">
                      <td className="py-3.5 px-4 font-mono">
                        <Link
                          to={`/app/finance/expenditures/${req.id}`}
                          className="font-medium text-white hover:text-indigo-400 transition-colors block"
                        >
                          {req.request_number || req.reference_number || 'DRAFT'}
                        </Link>
                        <span className="text-[10px] text-slate-500 block">
                          {new Date(req.created_at).toLocaleDateString('id-ID')}
                        </span>
                      </td>

                      <td className="py-3.5 px-4">
                        <span className="font-medium text-white block">
                          {req.title}
                        </span>
                        {req.vendor_name && (
                          <span className="text-[10px] text-slate-400 block">
                            Penyedia: {req.vendor_name}
                          </span>
                        )}
                      </td>

                      <td className="py-3.5 px-4">
                        <span className="text-slate-300 block">
                          {req.budget_line?.category?.name || 'Kategori Belanja'}
                        </span>
                        <span className="text-[10px] text-indigo-400 font-mono block">
                          {req.budget_line?.category?.code}
                        </span>
                      </td>

                      <td className="py-3.5 px-4">
                        <span className="text-slate-200 block">
                          {req.requester?.name || '-'}
                        </span>
                        {isRequester && (
                          <span className="text-[10px] text-indigo-400 italic">
                            (Anda)
                          </span>
                        )}
                      </td>

                      <td className="py-3.5 px-4">
                        <ExpenditureStatusBadge status={req.status} />
                      </td>

                      <td className="py-3.5 px-4 text-right font-mono font-bold text-white">
                        {formatRupiah(req.amount ?? req.proposed_amount)}
                      </td>

                      <td className="py-3.5 px-4 text-center">
                        <div className="flex items-center justify-center gap-1.5">
                          <Link
                            to={`/app/finance/expenditures/${req.id}`}
                            className="px-2 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium transition-colors"
                          >
                            Detail
                          </Link>

                          {/* DRAFT Actions */}
                          {req.status === 'DRAFT' && isRequester && (
                            <>
                              <Button
                                type="button"
                                variant="primary"
                                size="sm"
                                onClick={() => handleSubmitDraft(req)}
                                disabled={submitMutation.isPending}
                                className="text-[11px] py-1 px-2"
                              >
                                Kirim
                              </Button>
                              <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                onClick={() => setCancellingReq(req)}
                                className="text-[11px] py-1 px-2 text-rose-400 hover:text-rose-300"
                              >
                                Batal
                              </Button>
                            </>
                          )}

                          {/* SUBMITTED Actions (Approve / Reject / Cancel) */}
                          {req.status === 'SUBMITTED' && (
                            <>
                              {canApprove && (
                                <Button
                                  type="button"
                                  variant="primary"
                                  size="sm"
                                  onClick={() => setApprovingReq(req)}
                                  disabled={isRequester}
                                  title={
                                    isRequester
                                      ? 'Pemisahan kewenangan: Pemohon tidak dapat menyetujui pengajuannya sendiri'
                                      : 'Setujui pengajuan belanja'
                                  }
                                  className="text-[11px] py-1 px-2 disabled:opacity-40"
                                >
                                  Setujui
                                </Button>
                              )}
                              {canApprove && (
                                <Button
                                  type="button"
                                  variant="danger"
                                  size="sm"
                                  onClick={() => setRejectingReq(req)}
                                  className="text-[11px] py-1 px-2"
                                >
                                  Tolak
                                </Button>
                              )}
                              {isRequester && (
                                <Button
                                  type="button"
                                  variant="secondary"
                                  size="sm"
                                  onClick={() => setCancellingReq(req)}
                                  className="text-[11px] py-1 px-2 text-rose-400 hover:text-rose-300"
                                >
                                  Batal
                                </Button>
                              )}
                            </>
                          )}

                          {/* APPROVED Actions (Post to Ledger) */}
                          {req.status === 'APPROVED' && canPost && (
                            <Button
                              type="button"
                              variant="primary"
                              size="sm"
                              onClick={() => setPostingReq(req)}
                              className="text-[11px] py-1 px-2 bg-emerald-600 hover:bg-emerald-500"
                            >
                              Posting Kas
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
      <CreateExpenditureRequestDialog
        open={createOpen}
        onClose={() => setCreateOpen(false)}
        onSuccess={() => refetch()}
      />

      <ApproveExpenditureDialog
        open={Boolean(approvingReq)}
        request={approvingReq}
        onClose={() => setApprovingReq(null)}
        onSuccess={() => refetch()}
      />

      <RejectExpenditureDialog
        open={Boolean(rejectingReq)}
        request={rejectingReq}
        onClose={() => setRejectingReq(null)}
        onSuccess={() => refetch()}
      />

      <CancelExpenditureDialog
        open={Boolean(cancellingReq)}
        request={cancellingReq}
        onClose={() => setCancellingReq(null)}
        onSuccess={() => refetch()}
      />

      <PostExpenditureDialog
        open={Boolean(postingReq)}
        request={postingReq}
        onClose={() => setPostingReq(null)}
        onSuccess={() => refetch()}
      />
    </div>
  );
};
