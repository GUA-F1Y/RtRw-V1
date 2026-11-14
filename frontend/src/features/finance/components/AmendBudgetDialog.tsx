import React, { useRef, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type { BudgetPlan } from '../../../types/finance.ts';
import { useAmendBudgetPlan } from '../api/financeQueries.ts';

interface AmendBudgetDialogProps {
  open: boolean;
  plan: BudgetPlan | null;
  onClose: () => void;
  onSuccess?: (newPlanId: string) => void;
}

export const AmendBudgetDialog: React.FC<AmendBudgetDialogProps> = ({
  open,
  plan,
  onClose,
  onSuccess,
}) => {
  const amendMutation = useAmendBudgetPlan();
  const idempotencyKeyRef = useRef<string>(generateIdempotencyKey());
  const [notes, setNotes] = useState<string>('');

  if (!plan) return null;

  const handleAmend = async () => {
    try {
      const res = await amendMutation.mutateAsync({
        id: plan.id,
        payload: { notes: notes.trim() || undefined },
        idempotencyKey: idempotencyKeyRef.current,
      });
      onSuccess?.(res.data.id);
      onClose();
    } catch {
      // Handled by mutation error state
    }
  };

  return (
    <Dialog open={open} onClose={onClose} title="Buat Amandemen Anggaran (Versi Baru)" maxWidth="max-w-lg">
      <div className="space-y-4">
        {amendMutation.isError && (
          <Alert type="error" message={amendMutation.error?.message || 'Gagal membuat amandemen anggaran.'} />
        )}

        <div className="bg-indigo-500/10 border border-indigo-500/30 rounded-xl p-4 text-xs text-indigo-200">
          <div className="font-semibold text-indigo-100 flex items-center gap-1.5 mb-1.5">
            <svg className="w-4 h-4 text-indigo-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Alur Amandemen Anggaran (Version Control)
          </div>
          <p>
            Tindakan ini akan membuat salinan rencana anggaran versi baru (v{plan.version + 1}) dalam status <strong>DRAFT</strong>.
            Semua pos anggaran dan pagu dari versi v{plan.version} saat ini akan disalin ke draf baru sehingga Anda dapat menyesuaikan alokasi pos pagu.
          </p>
          <p className="mt-2 text-slate-300">
            Versi v{plan.version} saat ini tetap aktif sampai draf baru disahkan dan diterbitkan (PUBLISHED).
          </p>
        </div>

        <div>
          <label className="block text-xs font-semibold text-slate-300 mb-1">
            Alasan / Catatan Amandemen
          </label>
          <textarea
            rows={3}
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            placeholder="Contoh: Penyesuaian pagu kebersihan dan perbaikan saluran air karena musim hujan..."
            className="w-full bg-slate-800 border border-slate-700 rounded-lg p-3 text-xs text-white focus:outline-none focus:border-indigo-500"
          />
        </div>

        <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
          <Button type="button" variant="secondary" onClick={onClose} disabled={amendMutation.isPending}>
            Batal
          </Button>
          <Button
            type="button"
            variant="primary"
            onClick={handleAmend}
            disabled={amendMutation.isPending}
            className="bg-indigo-600 hover:bg-indigo-500 text-white"
          >
            {amendMutation.isPending ? 'Membuat Draf Amandemen...' : 'Buat Draf v' + (plan.version + 1)}
          </Button>
        </div>
      </div>
    </Dialog>
  );
};
