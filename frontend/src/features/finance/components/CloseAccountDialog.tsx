import React, { useEffect, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import type { CashAccount } from '../../../types/finance.ts';
import { useCloseCashAccount } from '../api/financeQueries.ts';

interface CloseAccountDialogProps {
  account: CashAccount | null;
  open: boolean;
  onClose: () => void;
  onSuccess?: () => void;
}

export const CloseAccountDialog: React.FC<CloseAccountDialogProps> = ({
  account,
  open,
  onClose,
  onSuccess,
}) => {
  const mutation = useCloseCashAccount();
  const [reason, setReason] = useState('');
  const [clientError, setClientError] = useState<string | null>(null);

  useEffect(() => {
    if (open) {
      setReason('');
      setClientError(null);
      mutation.reset();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  if (!account) return null;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!reason.trim()) {
      setClientError('Alasan penutupan akun kas wajib diisi.');
      return;
    }
    if (reason.trim().length < 5) {
      setClientError('Alasan penutupan harus memiliki minimal 5 karakter.');
      return;
    }

    setClientError(null);

    try {
      await mutation.mutateAsync({
        id: account.id,
        payload: { reason: reason.trim() },
      });
      onClose();
      if (onSuccess) onSuccess();
    } catch {
      // Handled by mutation error
    }
  };

  const validationErrors = mutation.error?.errors;
  const generalError = mutation.error?.message;

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title="Tutup Akun Kas"
      description={`Konfirmasi penutupan permanen untuk akun "${account.name}".`}
      maxWidth="max-w-md"
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        {/* Irreversible Warning Banner */}
        <div className="p-3.5 rounded-xl bg-amber-950/40 border border-amber-800/60 text-amber-300 text-xs flex gap-2.5 items-start">
          <svg className="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <div className="space-y-1">
            <div className="font-semibold text-amber-200">Perhatian: Tindakan Bersifat Permanen</div>
            <div>
              Akun kas yang ditutup tidak dapat lagi menerima atau mencatat transaksi mutasi baru (pemasukan/pengeluaran). Seluruh saldo dan riwayat pembukuan ledger masa lalu tetap tersimpan untuk keperluan audit.
            </div>
          </div>
        </div>

        {generalError && (
          <Alert
            type="error"
            title="Gagal Menutup Akun"
            message={generalError}
            requestId={mutation.error?.requestId}
          />
        )}

        <div className="space-y-1.5">
          <label htmlFor="close-account-reason" className="block text-xs font-medium text-slate-300">
            Alasan Penutupan Akun <span className="text-rose-400">*</span>
          </label>
          <textarea
            id="close-account-reason"
            rows={3}
            placeholder="Jelaskan alasan audit penutupan akun kas ini (cth. Pergantian kepengurusan periode, konsolidasi kas RT)..."
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            className="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs placeholder-slate-500 focus:ring-2 focus:ring-rose-500 focus:outline-none resize-none"
          />
          <FieldError message={clientError || validationErrors?.reason?.[0]} />
        </div>

        {/* Actions */}
        <div className="pt-3 border-t border-slate-800 flex items-center justify-end gap-2">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={onClose}
            disabled={mutation.isPending}
          >
            Batal
          </Button>
          <Button
            type="submit"
            variant="danger"
            size="sm"
            isLoading={mutation.isPending}
            disabled={mutation.isPending}
          >
            Konfirmasi Tutup Akun
          </Button>
        </div>
      </form>
    </Dialog>
  );
};
