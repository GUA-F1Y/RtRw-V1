import React, { useEffect, useRef, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Badge } from '../../../components/ui/Badge.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type { FinancialTransaction } from '../../../types/finance.ts';
import { useReverseFinancialTransaction } from '../api/financeQueries.ts';
import { formatRupiah } from '../utils/currency.ts';

interface ReverseTransactionDialogProps {
  transaction: FinancialTransaction | null;
  open: boolean;
  onClose: () => void;
  onSuccess?: (reversalTransaction: FinancialTransaction) => void;
}

export const ReverseTransactionDialog: React.FC<ReverseTransactionDialogProps> = ({
  transaction,
  open,
  onClose,
  onSuccess,
}) => {
  const mutation = useReverseFinancialTransaction();
  const idempotencyKeyRef = useRef<string>(generateIdempotencyKey());
  const lastPayloadRef = useRef<string | null>(null);

  const [reason, setReason] = useState('');
  const [transactionDate, setTransactionDate] = useState('');
  const [clientError, setClientError] = useState<string | null>(null);

  useEffect(() => {
    if (open) {
      setReason('');
      setTransactionDate(new Date().toISOString().split('T')[0]);
      setClientError(null);
      mutation.reset();
      idempotencyKeyRef.current = generateIdempotencyKey();
      lastPayloadRef.current = null;
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  if (!transaction) return null;

  const isAlreadyReversed =
    transaction.is_reversal ||
    Boolean(transaction.is_reversed) ||
    Boolean(transaction.reversal_transaction_id) ||
    Boolean(transaction.reversal);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (isAlreadyReversed) return;

    if (!reason.trim()) {
      setClientError('Alasan pembatalan (reversal) transaksi wajib diisi.');
      return;
    }

    if (reason.trim().length < 5) {
      setClientError('Alasan pembatalan harus memiliki minimal 5 karakter.');
      return;
    }

    setClientError(null);

    const payload = {
      reversal_reason: reason.trim(),
      transaction_date: transactionDate || undefined,
    };

    const payloadString = JSON.stringify(payload);
    if (lastPayloadRef.current !== null && lastPayloadRef.current !== payloadString) {
      idempotencyKeyRef.current = generateIdempotencyKey();
    }
    lastPayloadRef.current = payloadString;

    try {
      const res = await mutation.mutateAsync({
        id: transaction.id,
        cashAccountId: transaction.cash_account_id,
        payload,
        idempotencyKey: idempotencyKeyRef.current,
      });

      onClose();
      if (onSuccess && res.data) {
        onSuccess(res.data);
      }
    } catch {
      // Handled by mutation error
    }
  };

  const validationErrors = mutation.error?.errors;
  const is409Conflict = mutation.error?.status === 409;
  const isPeriodClosed = mutation.error?.code === 'period_closed';
  const isAlreadyReversedError = mutation.error?.code === 'transaction_already_reversed';
  const generalError = isPeriodClosed
    ? mutation.error?.message || 'Tanggal pembalikan berada di dalam periode yang sudah ditutup. Tentukan tanggal pembalikan pada periode yang masih terbuka.'
    : isAlreadyReversedError
    ? 'Transaksi ini sudah pernah dibatalkan (reversed) sebelumnya.'
    : mutation.error?.message;

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title="Batalkan Transaksi Kas (Reversal)"
      description="Penerbitan entri pembalik akuntansi resmi untuk mengoreksi transaksi ledger yang telah diposting."
      maxWidth="max-w-lg"
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        {/* Accounting Rule Banner */}
        <div className="p-3.5 rounded-xl bg-amber-950/40 border border-amber-800/60 text-amber-300 text-xs flex gap-2.5 items-start">
          <svg className="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div className="space-y-1">
            <div className="font-semibold text-amber-200">Prinsip Audit Ledger: Append-Only</div>
            <div>
              Transaksi yang telah dibukukan tidak dapat dihapus secara fisik. Sistem akan menerbitkan <strong>entri pembalik (compensating reversal entry)</strong> yang secara matematis membatalkan dampak saldo namun tetap menjaga riwayat jejak audit tetap utuh.
            </div>
          </div>
        </div>

        {/* Original Transaction Summary Card */}
        <div className="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800 space-y-2 text-xs">
          <div className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
            Rincian Transaksi Asli
          </div>
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Badge variant={transaction.type === 'INCOME' ? 'emerald' : 'rose'} size="sm">
                {transaction.type}
              </Badge>
              <span className="font-semibold text-slate-200">
                {transaction.category?.name || 'Kategori Pos'}
              </span>
            </div>
            <div className="font-mono text-sm font-bold text-white">
              {formatRupiah(transaction.amount)}
            </div>
          </div>
          <div className="grid grid-cols-2 gap-2 pt-2 border-t border-slate-800/60 text-[11px] text-slate-400">
            <div>
              <span className="text-slate-500">Akun:</span> {transaction.cash_account?.name || transaction.account?.name || '-'}
            </div>
            <div>
              <span className="text-slate-500">Tanggal:</span> {transaction.transaction_date}
            </div>
            {transaction.reference_number && (
              <div className="col-span-2">
                <span className="text-slate-500">No. Ref:</span> {transaction.reference_number}
              </div>
            )}
            <div className="col-span-2">
              <span className="text-slate-500">Keterangan:</span> {transaction.description}
            </div>
          </div>
        </div>

        {/* Already reversed indicator */}
        {isAlreadyReversed && (
          <Alert
            type="warning"
            title="Tidak Dapat Dibatalkan Lagi"
            message="Transaksi ini sudah pernah dibatalkan atau merupakan entri pembatalan (reversal entry)."
          />
        )}

        {/* Error message */}
        {generalError && (
          <Alert
            type="error"
            title={isPeriodClosed ? 'Periode Akuntansi Telah Ditutup (Closed Period)' : is409Conflict ? 'Konflik Reversal (409)' : 'Gagal Membatalkan Transaksi'}
            message={generalError}
            requestId={mutation.error?.requestId}
          />
        )}

        {!isAlreadyReversed && (
          <>
            {/* Reversal Date */}
            <div className="space-y-1.5">
              <label htmlFor="rev-date" className="block text-xs font-medium text-slate-300">
                Tanggal Entri Pembatalan (Opsional)
              </label>
              <input
                id="rev-date"
                type="date"
                value={transactionDate}
                onChange={(e) => setTransactionDate(e.target.value)}
                className="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
              />
              <FieldError message={validationErrors?.transaction_date?.[0]} />
            </div>

            {/* Reversal Reason */}
            <div className="space-y-1.5">
              <label htmlFor="rev-reason" className="block text-xs font-medium text-slate-300">
                Alasan Koreksi Pembatalan <span className="text-rose-400">*</span>
              </label>
              <textarea
                id="rev-reason"
                rows={3}
                placeholder="Jelaskan alasan pembatalan transaksi untuk kebutuhan audit (cth. Salah input nominal, transaksi ganda, pembatalan kegiatan)..."
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                className="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs placeholder-slate-500 focus:ring-2 focus:ring-rose-500 focus:outline-none resize-none"
              />
              <FieldError message={clientError || validationErrors?.reversal_reason?.[0]} />
            </div>
          </>
        )}

        {/* Actions */}
        <div className="pt-3 border-t border-slate-800 flex items-center justify-end gap-2">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={onClose}
            disabled={mutation.isPending}
          >
            Tutup
          </Button>
          {!isAlreadyReversed && (
            <Button
              type="submit"
              variant="danger"
              size="sm"
              isLoading={mutation.isPending}
              disabled={mutation.isPending}
            >
              Terbitkan Reversal Entry
            </Button>
          )}
        </div>
      </form>
    </Dialog>
  );
};
