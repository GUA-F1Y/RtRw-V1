import React, { useRef, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type { ExpenditureRequest } from '../../../types/finance.ts';
import { useCancelExpenditureRequest } from '../api/financeQueries.ts';

interface CancelExpenditureDialogProps {
  open: boolean;
  request: ExpenditureRequest | null;
  onClose: () => void;
  onSuccess?: () => void;
}

export const CancelExpenditureDialog: React.FC<CancelExpenditureDialogProps> = ({
  open,
  request,
  onClose,
  onSuccess,
}) => {
  const cancelMutation = useCancelExpenditureRequest();
  const idempotencyKeyRef = useRef<string>(generateIdempotencyKey());
  const [reason, setReason] = useState<string>('');
  const [clientError, setClientError] = useState<string>('');

  if (!request) return null;

  const handleCancel = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!reason.trim()) {
      setClientError('Alasan pembatalan pengajuan belanja wajib diisi.');
      return;
    }

    try {
      await cancelMutation.mutateAsync({
        id: request.id,
        payload: { cancellation_reason: reason.trim(), reason: reason.trim() },
        idempotencyKey: idempotencyKeyRef.current,
      });
      onSuccess?.();
      onClose();
    } catch {
      // Handled by mutation error state
    }
  };

  return (
    <Dialog open={open} onClose={onClose} title="Batalkan Pengajuan Belanja">
      <form onSubmit={handleCancel} className="space-y-4">
        {cancelMutation.isError && (
          <Alert type="error" message={cancelMutation.error?.message || 'Gagal membatalkan pengajuan belanja.'} />
        )}

        <div className="bg-amber-500/10 border border-amber-500/30 rounded-xl p-3.5 text-xs text-amber-200">
          <p>
            Anda akan membatalkan pengajuan <strong>&quot;{request.title}&quot;</strong> (Status saat ini: {request.status}).
            Setelah dibatalkan, status pengajuan menjadi <strong>CANCELLED</strong> dan komitmen anggaran akan dibebaskan.
          </p>
        </div>

        <div>
          <label className="block text-xs font-semibold text-slate-300 mb-1">
            Alasan Pembatalan *
          </label>
          <textarea
            rows={3}
            value={reason}
            onChange={(e) => {
              setReason(e.target.value);
              if (clientError) setClientError('');
            }}
            placeholder="Jelaskan alasan pembatalan..."
            className="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-xs text-white focus:outline-none focus:border-amber-500"
          />
          {clientError && <FieldError message={clientError} />}
        </div>

        <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
          <Button type="button" variant="secondary" onClick={onClose} disabled={cancelMutation.isPending}>
            Kembali
          </Button>
          <Button
            type="submit"
            variant="danger"
            disabled={cancelMutation.isPending}
          >
            {cancelMutation.isPending ? 'Membatalkan...' : 'Batalkan Pengajuan'}
          </Button>
        </div>
      </form>
    </Dialog>
  );
};
