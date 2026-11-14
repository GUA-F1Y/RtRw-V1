import React, { useEffect, useId, useRef, useState } from 'react';
import { useAuth } from '../../../app/providers/useAuth.ts';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type { ExpenditureRequest } from '../../../types/finance.ts';
import { useApproveExpenditureRequest } from '../api/financeQueries.ts';
import { formatRupiah, parseIntegerRupiah } from '../utils/currency.ts';

interface ApproveExpenditureDialogProps {
  open: boolean;
  request: ExpenditureRequest | null;
  onClose: () => void;
  onSuccess?: () => void;
}

export const ApproveExpenditureDialog: React.FC<ApproveExpenditureDialogProps> = ({
  open,
  request,
  onClose,
  onSuccess,
}) => {
  const { user } = useAuth();
  const amountFieldId = useId();
  const reasonFieldId = useId();

  const approveMutation = useApproveExpenditureRequest();
  const idempotencyKeyRef = useRef<string>(generateIdempotencyKey());

  const [approvedAmountInput, setApprovedAmountInput] = useState<string>('');
  const [decisionReason, setDecisionReason] = useState<string>('');
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (open && request) {
      setApprovedAmountInput(String(request.proposed_amount));
      setDecisionReason('');
      setClientErrors({});
      idempotencyKeyRef.current = generateIdempotencyKey();
    }
  }, [open, request]);

  if (!request) return null;

  const isSelfRequester = Boolean(
    user?.id && (user.id === request.requested_by || user.id === (request as unknown as { requester_user_id?: string }).requester_user_id)
  );

  const amountParsed = parseIntegerRupiah(approvedAmountInput);
  const parsedApprovedAmount = amountParsed.valid ? (amountParsed.value ?? 0) : 0;

  const validate = (): boolean => {
    const errs: Record<string, string> = {};

    if (isSelfRequester) {
      errs.general = 'Pemisahan tugas (Segregation of Duties): Pemohon dilarang menyetujui pengajuannya sendiri.';
    }

    if (!amountParsed.valid || parsedApprovedAmount <= 0) {
      errs.approved_amount = amountParsed.error || 'Nominal persetujuan harus lebih dari 0.';
    } else if (parsedApprovedAmount > request.proposed_amount) {
      errs.approved_amount = `Nominal persetujuan tidak boleh melebihi pengajuan (${formatRupiah(request.proposed_amount)}).`;
    }

    setClientErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const handleApprove = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validate()) return;

    try {
      await approveMutation.mutateAsync({
        id: request.id,
        payload: {
          approved_amount: parsedApprovedAmount,
          decision_reason: decisionReason.trim() || undefined,
        },
        idempotencyKey: idempotencyKeyRef.current,
      });
      onSuccess?.();
      onClose();
    } catch {
      // Handled by mutation error state
    }
  };

  return (
    <Dialog open={open} onClose={onClose} title="Persetujuan Belanja (Ketua RW / RT)" maxWidth="max-w-lg">
      <form onSubmit={handleApprove} className="space-y-4">
        {approveMutation.isError && (
          <Alert type="error" message={approveMutation.error?.message || 'Gagal menyetujui pengajuan belanja.'} />
        )}

        {isSelfRequester && (
          <Alert
            type="warning"
            title="Peringatan Konflik Kepentingan"
            message={`Anda tercatat sebagai pemohon pengeluaran ini (${request.requester?.name || 'Anda'}). Berdasarkan prinsip tata kelola keuangan, persetujuan harus dilakukan oleh pejabat berwenang lain (bukan pemohon).`}
          />
        )}

        {/* Request Overview Card */}
        <div className="bg-slate-900/60 border border-slate-800 rounded-xl p-3.5 space-y-2 text-xs">
          <div className="flex justify-between">
            <span className="text-slate-400">Keperluan:</span>
            <span className="font-semibold text-white">{request.title}</span>
          </div>
          <div className="flex justify-between">
            <span className="text-slate-400">Pemohon:</span>
            <span className="text-slate-200">{request.requester?.name || request.requested_by}</span>
          </div>
          <div className="flex justify-between">
            <span className="text-slate-400">Pos Anggaran:</span>
            <span className="text-slate-200">
              [{request.category?.code || 'EXP'}] {request.category?.name || 'Kategori'}
            </span>
          </div>
          <div className="flex justify-between pt-2 border-t border-slate-800">
            <span className="text-slate-400">Nominal Diajukan:</span>
            <span className="font-bold text-white font-mono">
              {formatRupiah(request.proposed_amount)}
            </span>
          </div>
        </div>

        {/* Approved Amount */}
        <div>
          <label htmlFor={amountFieldId} className="block text-xs font-semibold text-slate-300 mb-1">
            Nominal Disetujui (Rupiah) *
          </label>
          <div className="relative">
            <span className="absolute left-3 top-2 text-xs text-slate-400">Rp</span>
            <input
              id={amountFieldId}
              type="text"
              value={approvedAmountInput}
              onChange={(e) => setApprovedAmountInput(e.target.value)}
              className="w-full bg-slate-800 border border-slate-700 rounded-lg pl-8 pr-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-cyan-500"
            />
          </div>
          <p className="text-[11px] text-slate-400 mt-1">
            Dapat disetujui penuh atau disetujui sebagian (nominal lebih kecil).
          </p>
          {clientErrors.approved_amount && <FieldError message={clientErrors.approved_amount} />}
        </div>

        {/* Decision Reason */}
        <div>
          <label htmlFor={reasonFieldId} className="block text-xs font-semibold text-slate-300 mb-1">
            Catatan Persetujuan (Opsional)
          </label>
          <textarea
            id={reasonFieldId}
            rows={2}
            value={decisionReason}
            onChange={(e) => setDecisionReason(e.target.value)}
            placeholder="Catatan arahan atau pertimbangan persetujuan..."
            className="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-xs text-white focus:outline-none focus:border-cyan-500"
          />
        </div>

        {/* Actions */}
        <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
          <Button type="button" variant="secondary" onClick={onClose} disabled={approveMutation.isPending}>
            Batal
          </Button>
          <Button
            type="submit"
            variant="primary"
            disabled={approveMutation.isPending || isSelfRequester}
            className="bg-cyan-600 hover:bg-cyan-500 text-white"
          >
            {approveMutation.isPending ? 'Memproses...' : 'Setujui Pengajuan'}
          </Button>
        </div>
      </form>
    </Dialog>
  );
};
