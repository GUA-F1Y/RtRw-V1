import React, { useRef } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type { BudgetPlan } from '../../../types/finance.ts';
import { usePublishBudgetPlan } from '../api/financeQueries.ts';
import { formatRupiah } from '../utils/currency.ts';

interface PublishBudgetDialogProps {
  open: boolean;
  plan: BudgetPlan | null;
  onClose: () => void;
  onSuccess?: () => void;
}

export const PublishBudgetDialog: React.FC<PublishBudgetDialogProps> = ({
  open,
  plan,
  onClose,
  onSuccess,
}) => {
  const publishMutation = usePublishBudgetPlan();
  const idempotencyKeyRef = useRef<string>(generateIdempotencyKey());

  if (!plan) return null;

  const totalAmount = plan.summary?.total_planned ?? plan.lines?.reduce((acc, l) => acc + l.planned_amount, 0) ?? 0;

  const handlePublish = async () => {
    try {
      await publishMutation.mutateAsync({
        id: plan.id,
        payload: { confirmation: true },
        idempotencyKey: idempotencyKeyRef.current,
      });
      onSuccess?.();
      onClose();
    } catch {
      // Handled by mutation error state
    }
  };

  return (
    <Dialog open={open} onClose={onClose} title="Terbitkan Rencana Anggaran (Publish)" maxWidth="max-w-lg">
      <div className="space-y-4">
        {publishMutation.isError && (
          <Alert type="error" message={publishMutation.error?.message || 'Gagal menerbitkan rencana anggaran.'} />
        )}

        <div className="bg-amber-500/10 border border-amber-500/30 rounded-xl p-4 text-xs text-amber-200">
          <div className="font-semibold text-amber-100 flex items-center gap-1.5 mb-1.5">
            <svg className="w-4 h-4 text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            Perhatian Konfirmasi Pengesahan
          </div>
          <p>
            Menerbitkan rencana anggaran akan mengubah statusnya menjadi <strong>PUBLISHED</strong>.
            Pos-pos anggaran di dalamnya akan <strong>dikunci secara permanen di database</strong> dan tidak dapat diubah atau dihapus secara langsung.
          </p>
          <p className="mt-2">
            Jika sudah ada versi anggaran aktif untuk periode {plan.year}-{plan.month}, versi tersebut akan otomatis digantikan (<strong>SUPERSEDED</strong>).
          </p>
        </div>

        <div className="bg-slate-900/60 border border-slate-800 rounded-xl p-4 space-y-2 text-xs">
          <div className="flex justify-between text-slate-300">
            <span className="text-slate-400">Periode Anggaran:</span>
            <span className="font-semibold text-white">Bulan {plan.month} / Tahun {plan.year}</span>
          </div>
          <div className="flex justify-between text-slate-300">
            <span className="text-slate-400">Cakupan Unit:</span>
            <span className="font-semibold text-white">
              {plan.scope_type === 'RT' ? `RT ${plan.rt?.number ?? ''}` : 'Tingkat RW'}
            </span>
          </div>
          <div className="flex justify-between text-slate-300">
            <span className="text-slate-400">Versi Dokumen:</span>
            <span className="font-mono text-white">v{plan.version}</span>
          </div>
          <div className="flex justify-between text-slate-300 pt-2 border-t border-slate-800">
            <span className="text-slate-400">Total Plafon Pagu:</span>
            <span className="font-bold text-emerald-400 font-mono text-sm">
              {formatRupiah(totalAmount)}
            </span>
          </div>
        </div>

        <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
          <Button type="button" variant="secondary" onClick={onClose} disabled={publishMutation.isPending}>
            Batal
          </Button>
          <Button
            type="button"
            variant="primary"
            onClick={handlePublish}
            disabled={publishMutation.isPending}
            className="bg-emerald-600 hover:bg-emerald-500 text-white"
          >
            {publishMutation.isPending ? 'Menerbitkan...' : 'Ya, Terbitkan Anggaran'}
          </Button>
        </div>
      </div>
    </Dialog>
  );
};
