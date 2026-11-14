import React, { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useAuth } from '../../../app/providers/useAuth.ts';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Badge } from '../../../components/ui/Badge.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import {
  useExpenditureRequest,
  useSubmitExpenditureRequest,
} from '../api/financeQueries.ts';
import {
  ApproveExpenditureDialog,
  CancelExpenditureDialog,
  ExpenditureStatusBadge,
  PostExpenditureDialog,
  RejectExpenditureDialog,
} from '../components/index.ts';
import { formatRupiah } from '../utils/currency.ts';
import {
  canApproveExpenditures,
  canPostExpenditures,
} from '../utils/permissions.ts';

export const ExpenditureDetailPage: React.FC = () => {
  const { requestId } = useParams<{ requestId: string }>();
  const { user } = useAuth();

  const canApprove = canApproveExpenditures(user);
  const canPost = canPostExpenditures(user);

  // Modals state
  const [approveOpen, setApproveOpen] = useState(false);
  const [rejectOpen, setRejectOpen] = useState(false);
  const [cancelOpen, setCancelOpen] = useState(false);
  const [postOpen, setPostOpen] = useState(false);

  const {
    data: reqResponse,
    isLoading,
    error,
    refetch,
  } = useExpenditureRequest(requestId || '');

  const submitMutation = useSubmitExpenditureRequest();
  const req = reqResponse?.data;

  if (isLoading) {
    return (
      <div className="p-12 text-center text-xs text-slate-400">
        Memuat detail pengajuan belanja...
      </div>
    );
  }

  if (error || !req) {
    return (
      <div className="space-y-4">
        <Link
          to="/app/finance/expenditures"
          className="inline-flex items-center gap-1.5 text-xs text-indigo-400 hover:text-indigo-300 transition-colors"
        >
          ← Kembali ke Daftar Pengajuan Belanja
        </Link>
        <Alert type="error" message={error?.message || 'Pengajuan belanja tidak ditemukan.'} />
      </div>
    );
  }

  const isRequester = user?.id === (req.requester_user_id || req.requested_by);

  const handleSubmitDraft = async () => {
    if (!window.confirm(`Kirim pengajuan "${req.title}" untuk persetujuan ketua?`)) return;
    try {
      await submitMutation.mutateAsync({
        id: req.id,
        idempotencyKey: generateIdempotencyKey(),
      });
      refetch();
    } catch {
      // Handled by query error
    }
  };

  const budgetPlan = req.budget_line?.plan || req.budget_line?.budget_plan;
  const category = req.budget_line?.category;

  return (
    <div className="space-y-6">
      {/* Back button & Header */}
      <div>
        <Link
          to="/app/finance/expenditures"
          className="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-slate-200 transition-colors mb-3"
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          Kembali ke Pengajuan Belanja
        </Link>

        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
          <div className="space-y-1">
            <div className="flex items-center gap-2.5">
              <h1 className="text-xl font-bold text-white tracking-tight">
                {req.request_number || 'Pengajuan Belanja (DRAFT)'}
              </h1>
              <ExpenditureStatusBadge status={req.status} />
              {req.urgency && req.urgency !== 'NORMAL' && (
                <Badge variant={req.urgency === 'EMERGENCY' ? 'rose' : 'amber'} size="sm">
                  {req.urgency}
                </Badge>
              )}
            </div>
            <p className="text-sm font-medium text-slate-200">
              {req.title}
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            {/* DRAFT Actions */}
            {req.status === 'DRAFT' && isRequester && (
              <>
                <Button
                  type="button"
                  variant="primary"
                  size="sm"
                  onClick={handleSubmitDraft}
                  disabled={submitMutation.isPending}
                >
                  {submitMutation.isPending ? 'Mengirim...' : 'Kirim untuk Persetujuan'}
                </Button>
                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  onClick={() => setCancelOpen(true)}
                  className="text-rose-400 hover:text-rose-300"
                >
                  Batalkan
                </Button>
              </>
            )}

            {/* SUBMITTED Actions */}
            {req.status === 'SUBMITTED' && (
              <>
                {canApprove && (
                  <Button
                    type="button"
                    variant="primary"
                    size="sm"
                    onClick={() => setApproveOpen(true)}
                    disabled={isRequester}
                    title={
                      isRequester
                        ? 'Pemisahan kewenangan: Pemohon tidak dapat menyetujui pengajuannya sendiri'
                        : 'Setujui pengajuan belanja'
                    }
                  >
                    Setujui Pengajuan
                  </Button>
                )}
                {canApprove && (
                  <Button
                    type="button"
                    variant="danger"
                    size="sm"
                    onClick={() => setRejectOpen(true)}
                  >
                    Tolak Pengajuan
                  </Button>
                )}
                {isRequester && (
                  <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    onClick={() => setCancelOpen(true)}
                    className="text-rose-400 hover:text-rose-300"
                  >
                    Batalkan Pengajuan
                  </Button>
                )}
              </>
            )}

            {/* APPROVED Actions */}
            {req.status === 'APPROVED' && canPost && (
              <Button
                type="button"
                variant="primary"
                size="sm"
                onClick={() => setPostOpen(true)}
                className="bg-emerald-600 hover:bg-emerald-500"
              >
                Posting ke Buku Kas
              </Button>
            )}
          </div>
        </div>
      </div>

      {/* Segregation of duties alert for requester */}
      {isRequester && req.status === 'SUBMITTED' && (
        <Alert
          type="warning"
          title="Pemisahan Kewenangan (Segregation of Duties)"
          message="Anda adalah pemohon pengajuan belanja ini. Berdasarkan kepatuhan tata kelola, Anda tidak diizinkan menyetujui pengajuan belanja sendiri. Persetujuan harus dilakukan oleh Ketua RW atau Ketua RT yang berwenang."
        />
      )}

      {/* Amount Hero Card */}
      <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 flex flex-wrap items-center justify-between gap-4">
        <div>
          <span className="text-xs font-semibold text-slate-400 uppercase tracking-wider block">
            Nominal Belanja yang Diajukan
          </span>
          <div className="text-3xl font-mono font-bold text-white mt-1">
            {formatRupiah(req.amount ?? req.proposed_amount)}
          </div>
          <span className="text-xs text-slate-500 mt-1 block">
            Kategori: {category?.code} - {category?.name || '-'}
          </span>
        </div>

        {budgetPlan && (
          <div className="bg-slate-950/60 p-3.5 rounded-xl border border-slate-800 text-xs space-y-1 text-right">
            <span className="text-slate-400 block text-[11px]">Beban Pagu Rencana Anggaran:</span>
            <Link
              to={`/app/finance/budgets/${budgetPlan.id}`}
              className="text-indigo-400 hover:text-indigo-300 font-semibold block"
            >
              RAPB {String(budgetPlan.month).padStart(2, '0')}/{budgetPlan.year} (v{budgetPlan.version})
            </Link>
            <span className="text-[11px] text-slate-500 block font-mono">
              Pagu Disetujui: {formatRupiah(req.budget_line?.planned_amount ?? 0)}
            </span>
          </div>
        )}
      </div>

      {/* Workflow Timeline */}
      <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-4">
        <h2 className="text-xs font-bold text-slate-300 uppercase tracking-wider">
          Alur & Status Persetujuan
        </h2>

        <div className="grid grid-cols-1 md:grid-cols-4 gap-3 text-xs">
          {/* Step 1: Draft */}
          <div className="bg-slate-950/60 p-3 rounded-lg border border-slate-800/80">
            <div className="flex items-center gap-2 mb-1.5">
              <span className="w-5 h-5 rounded-full bg-slate-800 text-slate-200 flex items-center justify-center font-bold text-[10px]">
                1
              </span>
              <span className="font-semibold text-white">Pembuatan Draft</span>
            </div>
            <p className="text-slate-400 text-[11px]">
              Oleh: <strong className="text-slate-200">{req.requester?.name || '-'}</strong>
            </p>
            <p className="text-[10px] text-slate-500 mt-0.5">
              {new Date(req.created_at).toLocaleString('id-ID')}
            </p>
          </div>

          {/* Step 2: Submitted */}
          <div className={`p-3 rounded-lg border ${
            req.status !== 'DRAFT'
              ? 'bg-slate-950/60 border-slate-800/80'
              : 'bg-slate-950/20 border-slate-900 text-slate-600'
          }`}>
            <div className="flex items-center gap-2 mb-1.5">
              <span className="w-5 h-5 rounded-full bg-slate-800 text-slate-200 flex items-center justify-center font-bold text-[10px]">
                2
              </span>
              <span className="font-semibold text-white">Diajukan</span>
            </div>
            <p className="text-[11px]">
              {req.status === 'DRAFT' ? 'Belum dikirim' : 'Menunggu verifikasi ketua'}
            </p>
          </div>

          {/* Step 3: Decision (Approved / Rejected / Cancelled) */}
          <div className={`p-3 rounded-lg border ${
            req.status === 'APPROVED'
              ? 'bg-emerald-950/20 border-emerald-800/60 text-emerald-300'
              : req.status === 'REJECTED'
              ? 'bg-rose-950/20 border-rose-800/60 text-rose-300'
              : req.status === 'CANCELLED'
              ? 'bg-slate-950/40 border-slate-800 text-slate-400'
              : 'bg-slate-950/20 border-slate-900 text-slate-600'
          }`}>
            <div className="flex items-center gap-2 mb-1.5">
              <span className="w-5 h-5 rounded-full bg-slate-800 text-slate-200 flex items-center justify-center font-bold text-[10px]">
                3
              </span>
              <span className="font-semibold">
                {req.status === 'APPROVED'
                  ? 'Disetujui'
                  : req.status === 'REJECTED'
                  ? 'Ditolak'
                  : req.status === 'CANCELLED'
                  ? 'Dibatalkan'
                  : 'Persetujuan'}
              </span>
            </div>
            {req.approver && (
              <p className="text-[11px]">
                Oleh: <strong>{req.approver.name}</strong>
              </p>
            )}
            {(req.decided_at || req.decision_at) && (
              <p className="text-[10px] text-slate-500 mt-0.5">
                {new Date(req.decided_at || req.decision_at || '').toLocaleString('id-ID')}
              </p>
            )}
            {req.decision_reason && (
              <p className="text-[11px] mt-1 italic">
                &quot;{req.decision_reason}&quot;
              </p>
            )}
            {req.cancellation_reason && (
              <p className="text-[11px] mt-1 italic">
                Alasan batal: &quot;{req.cancellation_reason}&quot;
              </p>
            )}
          </div>

          {/* Step 4: Posted to Ledger */}
          <div className={`p-3 rounded-lg border ${
            req.status === 'POSTED'
              ? 'bg-emerald-950/20 border-emerald-800/60 text-emerald-300'
              : 'bg-slate-950/20 border-slate-900 text-slate-600'
          }`}>
            <div className="flex items-center gap-2 mb-1.5">
              <span className="w-5 h-5 rounded-full bg-slate-800 text-slate-200 flex items-center justify-center font-bold text-[10px]">
                4
              </span>
              <span className="font-semibold">Posting Buku Kas</span>
            </div>
            {req.poster && (
              <p className="text-[11px]">
                Oleh: <strong>{req.poster.name}</strong>
              </p>
            )}
            {req.posted_at && (
              <p className="text-[10px] text-slate-500 mt-0.5">
                {new Date(req.posted_at).toLocaleString('id-ID')}
              </p>
            )}
            {req.financial_transaction_id && (
              <span className="text-[10px] text-indigo-400 font-mono mt-1 block">
                Ref ID: {req.financial_transaction_id.slice(0, 8)}...
              </span>
            )}
          </div>
        </div>
      </div>

      {/* Details Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
        {/* Specification Card */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-4">
          <h2 className="text-xs font-bold text-slate-300 uppercase tracking-wider">
            Rincian & Dokumen Pengajuan
          </h2>

          <div className="space-y-3 text-xs">
            <div>
              <span className="text-slate-400 block text-[11px]">Deskripsi / Keperluan:</span>
              <p className="text-slate-200 mt-1 leading-relaxed bg-slate-950/40 p-3 rounded-lg border border-slate-800/60">
                {req.description || 'Tidak ada deskripsi rinci.'}
              </p>
            </div>

            <div className="grid grid-cols-2 gap-3 pt-2">
              <div>
                <span className="text-slate-400 block text-[11px]">Nama Vendor / Penerima:</span>
                <span className="text-white font-medium">{req.vendor_name || '-'}</span>
              </div>
              <div>
                <span className="text-slate-400 block text-[11px]">Rekening Bank Vendor:</span>
                <span className="text-white font-mono">{req.vendor_bank_account || '-'}</span>
              </div>
            </div>

            {req.evidence_file_id && (
              <div className="pt-2">
                <span className="text-slate-400 block text-[11px]">Lampiran Bukti Pendukung:</span>
                <span className="text-indigo-400 font-mono text-[11px]">
                  File ID: {req.evidence_file_id}
                </span>
              </div>
            )}
          </div>
        </div>

        {/* Budget Line & Ledger Link Card */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-4">
          <h2 className="text-xs font-bold text-slate-300 uppercase tracking-wider">
            Pos Anggaran & Transaksi Buku Kas
          </h2>

          <div className="space-y-3 text-xs">
            <div>
              <span className="text-slate-400 block text-[11px]">Pos Kategori Belanja:</span>
              <span className="text-white font-medium">
                {category?.code} - {category?.name || '-'}
              </span>
            </div>

            {budgetPlan && (
              <div>
                <span className="text-slate-400 block text-[11px]">Dokumen Anggaran Induk:</span>
                <Link
                  to={`/app/finance/budgets/${budgetPlan.id}`}
                  className="text-indigo-400 hover:text-indigo-300 font-medium"
                >
                  RAPB Bulan {budgetPlan.month}/{budgetPlan.year} (Versi {budgetPlan.version}) →
                </Link>
              </div>
            )}

            {req.status === 'POSTED' && (
              <div className="bg-slate-950/60 p-3.5 rounded-lg border border-slate-800 space-y-2">
                <span className="text-xs font-semibold text-emerald-400 block">
                  Pencatatan Buku Kas Selesai
                </span>
                <p className="text-[11px] text-slate-400">
                  Transaksi belanja telah dibukukan secara permanen pada buku kas operasional.
                </p>
                {req.financial_transaction_id && (
                  <Link
                    to={`/app/finance/transactions/${req.financial_transaction_id}`}
                    className="inline-flex items-center gap-1.5 text-xs text-indigo-400 hover:text-indigo-300 font-medium mt-1"
                  >
                    Buka Rincian Transaksi Buku Kas →
                  </Link>
                )}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Dialogs */}
      <ApproveExpenditureDialog
        open={approveOpen}
        request={req}
        onClose={() => setApproveOpen(false)}
        onSuccess={() => refetch()}
      />

      <RejectExpenditureDialog
        open={rejectOpen}
        request={req}
        onClose={() => setRejectOpen(false)}
        onSuccess={() => refetch()}
      />

      <CancelExpenditureDialog
        open={cancelOpen}
        request={req}
        onClose={() => setCancelOpen(false)}
        onSuccess={() => refetch()}
      />

      <PostExpenditureDialog
        open={postOpen}
        request={req}
        onClose={() => setPostOpen(false)}
        onSuccess={() => refetch()}
      />
    </div>
  );
};
