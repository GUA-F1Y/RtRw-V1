import React, { useEffect, useId, useRef, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type { ExpenditureRequest } from '../../../types/finance.ts';
import { useCashAccounts, usePostExpenditureRequest } from '../api/financeQueries.ts';
import { formatRupiah, parseIntegerRupiah } from '../utils/currency.ts';

interface PostExpenditureDialogProps {
  open: boolean;
  request: ExpenditureRequest | null;
  onClose: () => void;
  onSuccess?: () => void;
}

export const PostExpenditureDialog: React.FC<PostExpenditureDialogProps> = ({
  open,
  request,
  onClose,
  onSuccess,
}) => {
  const accountIdField = useId();
  const dateIdField = useId();
  const amountIdField = useId();
  const refIdField = useId();

  const { data: accountsData } = useCashAccounts({ status: 'active' }, { enabled: open });
  const accounts = accountsData?.data || [];

  const postMutation = usePostExpenditureRequest();
  const idempotencyKeyRef = useRef<string>(generateIdempotencyKey());

  const [accountId, setAccountId] = useState<string>('');
  const [transactionDate, setTransactionDate] = useState<string>('');
  const [actualAmountInput, setActualAmountInput] = useState<string>('');
  const [referenceNumber, setReferenceNumber] = useState<string>('');
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (open && request) {
      setAccountId(request.cash_account_id || (accounts.length > 0 ? accounts[0].id : ''));
      setTransactionDate(request.planned_date || new Date().toISOString().slice(0, 10));
      setActualAmountInput(String(request.approved_amount || request.proposed_amount));
      setReferenceNumber(request.reference_number || '');
      setClientErrors({});
      idempotencyKeyRef.current = generateIdempotencyKey();
    }
  }, [open, request, accounts]);

  if (!request) return null;

  const amountParsed = parseIntegerRupiah(actualAmountInput);
  const parsedAmount = amountParsed.valid ? (amountParsed.value ?? 0) : 0;
  const selectedAccount = accounts.find((a) => a.id === accountId);

  const validate = (): boolean => {
    const errs: Record<string, string> = {};

    if (!accountId) {
      errs.cash_account_id = 'Pilih rekening kas pengeluaran.';
    }

    if (!transactionDate) {
      errs.transaction_date = 'Tanggal pencairan transaksi kas wajib diisi.';
    }

    if (!amountParsed.valid || parsedAmount <= 0) {
      errs.actual_amount = amountParsed.error || 'Nominal pencairan harus lebih dari 0.';
    }

    if (selectedAccount && parsedAmount > selectedAccount.current_balance) {
      errs.actual_amount = `Saldo rekening tidak mencukupi (${formatRupiah(selectedAccount.current_balance)}).`;
    }

    setClientErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const handlePost = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validate()) return;

    try {
      await postMutation.mutateAsync({
        id: request.id,
        payload: {
          cash_account_id: accountId,
          transaction_date: transactionDate,
          actual_amount: parsedAmount,
          reference_number: referenceNumber.trim() || undefined,
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
    <Dialog open={open} onClose={onClose} title="Bukukan ke Buku Kas (Posting Ledger)" maxWidth="max-w-lg">
      <form onSubmit={handlePost} className="space-y-4">
        {postMutation.isError && (
          <Alert type="error" message={postMutation.error?.message || 'Gagal membukukan transaksi pengeluaran.'} />
        )}

        <div className="bg-emerald-500/10 border border-emerald-500/30 rounded-xl p-3.5 text-xs text-emerald-200">
          <div className="font-semibold text-emerald-100 flex items-center gap-1.5 mb-1">
            <svg className="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Pencatatan Buku Kas (Posting Imutabel)
          </div>
          <p>
            Membukukan pengajuan belanja ini akan langsung membuat tepat 1 entri transaksi pengeluaran kas (Expense) di buku kas umum, memotong saldo rekening, dan mengubah status pengajuan menjadi <strong>POSTED</strong>.
          </p>
        </div>

        {/* Cash Account Selection */}
        <div>
          <label htmlFor={accountIdField} className="block text-xs font-semibold text-slate-300 mb-1">
            Rekening Kas Pengeluaran *
          </label>
          <select
            id={accountIdField}
            value={accountId}
            onChange={(e) => setAccountId(e.target.value)}
            className="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
          >
            <option value="">-- Pilih Rekening Kas --</option>
            {accounts.map((acc) => (
              <option key={acc.id} value={acc.id}>
                {acc.name} - Saldo: {formatRupiah(acc.current_balance)}
              </option>
            ))}
          </select>
          {clientErrors.cash_account_id && <FieldError message={clientErrors.cash_account_id} />}
        </div>

        {/* Date and Actual Amount */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label htmlFor={dateIdField} className="block text-xs font-semibold text-slate-300 mb-1">
              Tanggal Transaksi Kas *
            </label>
            <input
              id={dateIdField}
              type="date"
              value={transactionDate}
              onChange={(e) => setTransactionDate(e.target.value)}
              className="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
            />
            {clientErrors.transaction_date && <FieldError message={clientErrors.transaction_date} />}
          </div>

          <div>
            <label htmlFor={amountIdField} className="block text-xs font-semibold text-slate-300 mb-1">
              Nominal Riil Dicairkan (Rupiah) *
            </label>
            <div className="relative">
              <span className="absolute left-3 top-2 text-xs text-slate-400">Rp</span>
              <input
                id={amountIdField}
                type="text"
                value={actualAmountInput}
                onChange={(e) => setActualAmountInput(e.target.value)}
                className="w-full bg-slate-800 border border-slate-700 rounded-lg pl-8 pr-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-emerald-500"
              />
            </div>
            {clientErrors.actual_amount && <FieldError message={clientErrors.actual_amount} />}
          </div>
        </div>

        {/* Reference Number */}
        <div>
          <label htmlFor={refIdField} className="block text-xs font-semibold text-slate-300 mb-1">
            Nomor Bukti / Kuitansi (Opsional)
          </label>
          <input
            id={refIdField}
            type="text"
            placeholder="Contoh: KWT-2026-09-082"
            value={referenceNumber}
            onChange={(e) => setReferenceNumber(e.target.value)}
            className="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-emerald-500"
          />
        </div>

        {/* Actions */}
        <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
          <Button type="button" variant="secondary" onClick={onClose} disabled={postMutation.isPending}>
            Batal
          </Button>
          <Button
            type="submit"
            variant="primary"
            disabled={postMutation.isPending}
            className="bg-emerald-600 hover:bg-emerald-500 text-white"
          >
            {postMutation.isPending ? 'Membukukan...' : 'Bukukan ke Kas'}
          </Button>
        </div>
      </form>
    </Dialog>
  );
};
