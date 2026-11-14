import React, { useEffect, useRef, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Badge } from '../../../components/ui/Badge.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type { CashTransfer } from '../../../types/finance.ts';
import { useReverseCashTransfer } from '../api/financeQueries.ts';
import { formatRupiah } from '../utils/currency.ts';

interface ReverseTransferDialogProps {
  transfer: CashTransfer | null;
  open: boolean;
  onClose: () => void;
  onSuccess?: (reversalTransfer: CashTransfer) => void;
}

export const ReverseTransferDialog: React.FC<ReverseTransferDialogProps> = ({
  transfer,
  open,
  onClose,
  onSuccess,
}) => {
  const mutation = useReverseCashTransfer();
  const idempotencyKeyRef = useRef<string>(generateIdempotencyKey());
  const lastPayloadRef = useRef<string | null>(null);

  const [reason, setReason] = useState('');
  const [transferDate, setTransferDate] = useState('');
  const [clientError, setClientError] = useState<string | null>(null);

  useEffect(() => {
    if (open) {
      setReason('');
      setTransferDate(new Date().toISOString().split('T')[0]);
      setClientError(null);
      mutation.reset();
      idempotencyKeyRef.current = generateIdempotencyKey();
      lastPayloadRef.current = null;
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  if (!transfer) return null;

  const isAlreadyReversed =
    transfer.is_reversal ||
    Boolean(transfer.is_reversed) ||
    Boolean(transfer.reversal);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (isAlreadyReversed) return;

    if (!reason.trim()) {
      setClientError('Alasan pembatalan (reversal) transfer wajib diisi.');
      return;
    }

    if (reason.trim().length < 3) {
      setClientError('Alasan pembatalan minimal 3 karakter.');
      return;
    }

    setClientError(null);

    const payload = {
      reversal_reason: reason.trim(),
      transfer_date: transferDate || undefined,
    };

    const payloadString = JSON.stringify(payload);
    if (lastPayloadRef.current !== null && lastPayloadRef.current !== payloadString) {
      idempotencyKeyRef.current = generateIdempotencyKey();
    }
    lastPayloadRef.current = payloadString;

    try {
      const res = await mutation.mutateAsync({
        id: transfer.id,
        payload,
        idempotencyKey: idempotencyKeyRef.current,
      });

      onClose();
      if (onSuccess && res.data) {
        onSuccess(res.data);
      }
    } catch {
      // Handled by mutation error state
    }
  };

  const validationErrors = mutation.error?.errors;
  const isPeriodClosed = mutation.error?.code === 'period_closed';
  const isAlreadyReversedError = mutation.error?.code === 'transfer_already_reversed';
  const isInsufficientFunds = mutation.error?.code === 'insufficient_funds';
  const isForbidden = mutation.error?.status === 403;

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title="Batalkan Transfer Kas (Reversal)"
      description="Penerbitan transfer pembalik kompensatoris untuk membatalkan mutasi transfer kas yang telah dicatat."
      maxWidth="max-w-md"
    >
      <form onSubmit={handleSubmit} className="space-y-4 text-xs">
        {/* Closed Period Alert */}
        {isPeriodClosed && (
          <Alert
            type="error"
            title="Periode Telah Ditutup"
            message={
              mutation.error?.message ||
              'Tanggal pembatalan berada pada periode akuntansi yang telah ditutup. Pembatalan tidak dapat dilakukan pada periode terkunci.'
            }
          />
        )}

        {/* Already Reversed Alert */}
        {(isAlreadyReversed || isAlreadyReversedError) && (
          <Alert
            type="warning"
            title="Transfer Tidak Dapat Dibatalkan"
            message={
              transfer.is_reversal
                ? 'Transaksi ini merupakan transfer pembalik (reversal) dan tidak dapat dibatalkan kembali.'
                : 'Transfer kas ini sudah pernah dibatalkan sebelumnya.'
            }
          />
        )}

        {/* Insufficient Funds in destination account */}
        {isInsufficientFunds && (
          <Alert
            type="error"
            title="Saldo Rekening Tidak Mencukupi"
            message={
              mutation.error?.message ||
              'Rekening tujuan tidak memiliki saldo yang mencukupi untuk mengembalikan dana transfer ini.'
            }
          />
        )}

        {/* Forbidden */}
        {isForbidden && (
          <Alert
            type="error"
            title="Akses Ditolak (403)"
            message={
              mutation.error?.message ||
              'Anda tidak memiliki wewenang untuk membatalkan transfer pada cakupan rekening ini.'
            }
          />
        )}

        {/* General Error Alert */}
        {mutation.isError &&
          !isPeriodClosed &&
          !isAlreadyReversedError &&
          !isInsufficientFunds &&
          !isForbidden && (
            <Alert
              type="error"
              title="Gagal Membatalkan Transfer"
              message={mutation.error?.message || 'Terjadi kesalahan sistem saat memproses pembatalan.'}
            />
          )}

        {/* Transfer Summary Card */}
        <div className="p-3.5 rounded-xl bg-slate-900 border border-slate-800 space-y-2.5">
          <div className="flex items-center justify-between">
            <span className="text-slate-400 font-mono text-[11px]">
              {transfer.reference_number || `TRF-${transfer.id.slice(0, 8)}`}
            </span>
            <Badge variant={transfer.is_reversal ? 'amber' : 'slate'} size="sm">
              {transfer.is_reversal ? 'Pembalikan' : 'Transfer Normal'}
            </Badge>
          </div>

          <div className="text-center py-2 bg-slate-950/60 rounded-lg border border-slate-800/80">
            <div className="text-[11px] text-slate-400">Nominal yang Dibatalkan</div>
            <div className="text-xl font-bold text-white tracking-tight font-mono mt-0.5">
              {formatRupiah(transfer.amount)}
            </div>
          </div>

          <div className="space-y-1.5 text-[11px] border-t border-slate-800/80 pt-2">
            <div className="flex justify-between items-center text-slate-300">
              <span className="text-slate-400">Arah Mutasi Asli:</span>
              <span className="font-medium text-right">
                {transfer.source_account?.name || 'Rekening Asal'} &rarr;{' '}
                {transfer.destination_account?.name || 'Rekening Tujuan'}
              </span>
            </div>
            <div className="flex justify-between items-center text-slate-300">
              <span className="text-slate-400">Arah Pembalik (Reversal):</span>
              <span className="font-semibold text-amber-400 text-right">
                {transfer.destination_account?.name || 'Rekening Tujuan'} &rarr;{' '}
                {transfer.source_account?.name || 'Rekening Asal'}
              </span>
            </div>
            <div className="flex justify-between text-slate-300">
              <span className="text-slate-400">Tanggal Asli:</span>
              <span>{transfer.transfer_date}</span>
            </div>
            <div className="flex justify-between text-slate-300">
              <span className="text-slate-400">Keterangan:</span>
              <span className="italic truncate max-w-[200px]">{transfer.description}</span>
            </div>
          </div>
        </div>

        {/* Immutability Notice */}
        <div className="p-2.5 rounded-lg bg-indigo-950/30 border border-indigo-800/40 text-indigo-300 text-[11px] flex gap-2">
          <svg className="w-4 h-4 shrink-0 text-indigo-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div>
            Buku kas RT/RW bersifat <strong>append-only</strong>. Pembatalan tidak menghapus transfer asli, melainkan menerbitkan transaksi transfer pembalik dan mencatat rekam jejak audit secara permanen.
          </div>
        </div>

        {/* Inputs */}
        <div className="space-y-3">
          <div className="space-y-1.5">
            <label className="block text-slate-300 font-medium">
              Tanggal Efektif Pembatalan <span className="text-rose-400">*</span>
            </label>
            <input
              type="date"
              value={transferDate}
              onChange={(e) => setTransferDate(e.target.value)}
              disabled={mutation.isPending || isAlreadyReversed}
              className="w-full px-2.5 py-1.5 rounded-lg bg-slate-900 border border-slate-700 text-slate-100 text-xs focus:ring-1 focus:ring-amber-500 focus:outline-none"
            />
            <FieldError message={validationErrors?.transfer_date?.[0]} />
          </div>

          <div className="space-y-1.5">
            <label className="block text-slate-300 font-medium">
              Alasan Pembatalan (Reversal Reason) <span className="text-rose-400">*</span>
            </label>
            <textarea
              rows={2}
              value={reason}
              onChange={(e) => {
                setReason(e.target.value);
                if (clientError) setClientError(null);
              }}
              placeholder="Contoh: Salah input rekening tujuan atau nominal ganda"
              disabled={mutation.isPending || isAlreadyReversed}
              className="w-full px-2.5 py-1.5 rounded-lg bg-slate-900 border border-slate-700 text-slate-100 text-xs focus:ring-1 focus:ring-amber-500 focus:outline-none resize-none"
            />
            {clientError && <FieldError message={clientError} />}
            <FieldError message={validationErrors?.reversal_reason?.[0]} />
          </div>
        </div>

        {/* Actions */}
        <div className="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-800">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={onClose}
            disabled={mutation.isPending}
          >
            Tutup
          </Button>
          <Button
            type="submit"
            variant="danger"
            size="sm"
            disabled={mutation.isPending || isAlreadyReversed}
          >
            {mutation.isPending ? 'Memproses Pembatalan...' : 'Konfirmasi Pembalikan'}
          </Button>
        </div>
      </form>
    </Dialog>
  );
};
