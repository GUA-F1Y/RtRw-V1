import React, { useRef, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type { ExpenditureRequest } from '../../../types/finance.ts';
import { useRejectExpenditureRequest } from '../api/financeQueries.ts';

interface RejectExpenditureDialogProps {
  open: boolean;
  request: ExpenditureRequest | null;
  onClose: () => void;
  onSuccess?: () => void;
}

export const RejectExpenditureDialog: React.FC<RejectExpenditureDialogProps> = ({
  open,
  request,
  onClose,
  onSuccess,
}) => {
  const rejectMutation = useRejectExpenditureRequest();
  const idempotencyKeyRef = useRef<string>(generateIdempotencyKey());
  const [reason, setReason] = useState<string>('');
  const [clientError, setClientError] = useState<string>('');

  if (!request) return null;

  const handleReject = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!reason.trim()) {
      setClientError('Alasan penolakan pengajuan belanja wajib diisi.');
      return;
    }

    try {
      await rejectMutation.mutateAsync({
        id: request.id,
        payload: { decision_reason: reason.trim() },
        idempotencyKey: idempotencyKeyRef.current,
      });
      onSuccess?.();
      onClose();
    } catch {
      // Handled by mutation error state
    }
  };

  return (
    <Dialog open={open} onClose={onClose} title="Tolak Pengajuan Belanja" maxWidth="max-w-lg">
      <form onSubmit={handleReject} className="space-y-4">
        {rejectMutation.isError && (
          <Alert type="error" message={rejectMutation.error?.message || 'Gagal menolak pengajuan belanja.'} />
        )}

        <div className="bg-rose-500/10 border border-rose-500/30 rounded-xl p-3.5 text-xs text-rose-200">
          <p>
            Anda akan menolak pengajuan <strong>&quot;{request.title}&quot;</strong>.
            Setelah ditolak, status pengajuan menjadi <strong>REJECTED</strong> dan tidak dapat diubah lagi.
          </p>
        </div>

        <div>
          <label className="block text-xs font-semibold text-slate-300 mb-1">
            Alasan Penolakan *
          </label>
          <textarea
            rows={3}
            value={reason}
            onChange={(e) => {
              setReason(e.target.value);
              if (clientError) setClientError('');
            }}
            placeholder="Jelaskan alasan penolakan, misalnya: anggaran dialihkan untuk prioritas lain..."
            className="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-xs text-white focus:outline-none focus:border-rose-500"
          />
          {clientError && <FieldError message={clientError} />}
        </div>

        <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
          <Button type="button" variant="secondary" onClick={onClose} disabled={rejectMutation.isPending}>
            Batal
          </Button>
          <Button
            type="submit"
            variant="danger"
            disabled={rejectMutation.isPending}
          >
            {rejectMutation.isPending ? 'Memproses...' : 'Tolak Pengajuan'}
          </Button>
        </div>
      </form>
    </Dialog>
  );
};
