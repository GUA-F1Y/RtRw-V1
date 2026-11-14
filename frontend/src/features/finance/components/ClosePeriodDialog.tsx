import React, { useEffect, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type { CashAccount, FinancialPeriodClosure, PeriodClosurePreview } from '../../../types/finance.ts';
import { useClosePeriod } from '../api/financeQueries.ts';
import { formatRupiah, parseIntegerRupiah } from '../utils/currency.ts';

const MONTH_NAMES = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

interface ClosePeriodDialogProps {
  account: CashAccount | null;
  year: number;
  month: number;
  preview: PeriodClosurePreview | null;
  open: boolean;
  onClose: () => void;
  onSuccess?: (closure: FinancialPeriodClosure) => void;
}

export const ClosePeriodDialog: React.FC<ClosePeriodDialogProps> = ({
  account,
  year,
  month,
  preview,
  open,
  onClose,
  onSuccess,
}) => {
  const mutation = useClosePeriod();
  const [countedCashInput, setCountedCashInput] = useState('');
  const [notes, setNotes] = useState('');
  const [confirmed, setConfirmed] = useState(false);
  const [clientError, setClientError] = useState<string | null>(null);
  const [idempotencyKey, setIdempotencyKey] = useState<string>('');

  useEffect(() => {
    if (open) {
      setCountedCashInput('');
      setNotes('');
      setConfirmed(false);
      setClientError(null);
      setIdempotencyKey(generateIdempotencyKey());
      mutation.reset();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  if (!account || !preview) return null;

  const monthName = MONTH_NAMES[month - 1] || `Bulan ${month}`;
  const ledgerClosing = preview.closing_balance;

  // Calculate live physical count difference
  let countedValue: number | null = null;
  let difference: number | null = null;
  let hasFormatError = false;

  if (countedCashInput.trim() !== '') {
    const parseResult = parseIntegerRupiah(countedCashInput, { allowZero: true });
    if (parseResult.valid && parseResult.value !== undefined) {
      countedValue = parseResult.value;
      difference = countedValue - ledgerClosing;
    } else {
      hasFormatError = true;
    }
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setClientError(null);

    if (hasFormatError) {
      setClientError('Nominal kas fisik tidak valid. Masukkan bilangan bulat positif.');
      return;
    }

    if (difference !== null && difference !== 0) {
      if (!notes.trim()) {
        setClientError('Penjelasan selisih rekonsiliasi wajib diisi karena fisik kas berbeda dengan saldo buku.');
        return;
      }
    }

    if (!confirmed) {
      setClientError('Anda harus menyetujui pernyataan konfirmasi sebelum menutup buku.');
      return;
    }

    try {
      const res = await mutation.mutateAsync({
        accountId: account.id,
        year,
        month,
        payload: {
          counted_cash_balance: countedValue,
          reconciliation_notes: notes.trim() ? notes.trim() : null,
        },
        idempotencyKey,
      });

      onClose();
      if (onSuccess) {
        onSuccess(res.data);
      }
    } catch {
      // Handled by mutation.error
    }
  };

  const generalError = mutation.error?.message;

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title="Tutup Buku Kas Bulanan"
      description={`Konfirmasi penutupan buku untuk akun ${account.name} periode ${monthName} ${year}.`}
      maxWidth="max-w-xl"
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        {/* Warning Banner */}
        <div className="p-3.5 rounded-xl bg-amber-950/40 border border-amber-800/60 text-amber-300 text-xs flex gap-2.5 items-start">
          <svg className="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <div className="space-y-1">
            <div className="font-semibold text-amber-200">Perhatian: Penutupan Bersifat Final dan Mengunci Periode</div>
            <div>
              Setelah periode {monthName} {year} ditutup, sistem akan mengunci periode ini. Tidak ada transaksi baru atau pembatalan yang dapat di-backdate ke dalam periode tertutup. Koreksi hanya dapat dicatat di periode berikutnya yang masih terbuka.
            </div>
          </div>
        </div>

        {/* Ledger Balance Summary Card */}
        <div className="p-4 rounded-xl bg-slate-900 border border-slate-800 space-y-3">
          <div className="text-xs font-medium text-slate-400 uppercase tracking-wider">
            Ringkasan Kalkulasi Buku Kas (Authoritative Ledger)
          </div>
          <div className="grid grid-cols-2 gap-3 text-sm">
            <div>
              <span className="text-xs text-slate-400 block">Saldo Awal Buku:</span>
              <span className="font-semibold text-slate-200">{formatRupiah(preview.opening_balance)}</span>
            </div>
            <div>
              <span className="text-xs text-slate-400 block">Pemasukan Bersih:</span>
              <span className="font-semibold text-emerald-400">+{formatRupiah(preview.effective_income)}</span>
            </div>
            <div>
              <span className="text-xs text-slate-400 block">Pengeluaran Bersih:</span>
              <span className="font-semibold text-rose-400">-{formatRupiah(preview.effective_expense)}</span>
            </div>
            <div>
              <span className="text-xs text-slate-400 block">Saldo Akhir Buku Kas:</span>
              <span className="font-bold text-slate-100 text-base">{formatRupiah(ledgerClosing)}</span>
            </div>
          </div>
          <div className="text-xs text-slate-400 border-t border-slate-800/80 pt-2 flex justify-between">
            <span>Total Transaksi Mutasi: {preview.total_transactions_count}</span>
            <span>Transaksi Dibatalkan: {preview.reversed_transactions_count}</span>
          </div>
        </div>

        {generalError && (
          <Alert
            type="error"
            title="Gagal Menutup Periode"
            message={generalError}
            requestId={mutation.error?.requestId}
          />
        )}

        {clientError && (
          <Alert
            type="error"
            title="Validasi Formulir"
            message={clientError}
          />
        )}

        {/* Optional Physical Cash Reconciliation Section */}
        <div className="space-y-3 p-4 rounded-xl bg-slate-950/60 border border-slate-800">
          <div className="flex items-center justify-between">
            <label htmlFor="counted-cash-input" className="block text-xs font-semibold text-slate-200">
              Hasil Penghitungan Kas Fisik (Opsional)
            </label>
            <span className="text-[11px] text-slate-400">Kosongkan jika tidak rekonsiliasi fisik</span>
          </div>

          <div className="relative">
            <span className="absolute inset-y-0 left-0 pl-3 flex items-center text-sm font-semibold text-slate-400">
              Rp
            </span>
            <input
              id="counted-cash-input"
              type="text"
              inputMode="numeric"
              placeholder="Contoh: 1500000"
              value={countedCashInput}
              onChange={(e) => setCountedCashInput(e.target.value)}
              disabled={mutation.isPending}
              className="w-full pl-10 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
            />
          </div>

          {/* Difference Display */}
          {difference !== null && (
            <div className="p-3 rounded-lg bg-slate-900/90 border border-slate-800 text-xs space-y-1">
              <div className="flex justify-between items-center">
                <span className="text-slate-400">Selisih Rekonsiliasi (Fisik - Buku):</span>
                {difference === 0 && (
                  <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-950 text-emerald-400 border border-emerald-800">
                    Cocok (Rp 0)
                  </span>
                )}
                {difference > 0 && (
                  <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-950 text-amber-300 border border-amber-800">
                    +{formatRupiah(difference)} (Kelebihan Fisik Kas)
                  </span>
                )}
                {difference < 0 && (
                  <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-rose-950 text-rose-300 border border-rose-800">
                    -{formatRupiah(Math.abs(difference))} (Kekurangan Fisik Kas)
                  </span>
                )}
              </div>
              {difference !== 0 && (
                <p className="text-amber-400 text-[11px]">
                  Catatan: Saldo buku kas tetap menjadi acuan resmi. Selisih wajib dijelaskan pada kolom catatan di bawah.
                </p>
              )}
            </div>
          )}

          {/* Reconciliation Notes */}
          <div className="space-y-1">
            <label htmlFor="reconciliation-notes" className="block text-xs font-medium text-slate-300">
              Penjelasan Rekonsiliasi / Catatan Penutupan{' '}
              {difference !== null && difference !== 0 ? (
                <span className="text-rose-400 font-bold">* (Wajib karena ada selisih)</span>
              ) : (
                <span className="text-slate-400">(Opsional)</span>
              )}
            </label>
            <textarea
              id="reconciliation-notes"
              rows={3}
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              disabled={mutation.isPending}
              placeholder={
                difference !== null && difference !== 0
                  ? 'Jelaskan penyebab selisih fisik kas (misal: panjar belum selesai, uang kembalian belanja belum disetor)...'
                  : 'Catatan tambahan terkait penutupan buku periode ini (opsional)...'
              }
              className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500"
            />
          </div>
        </div>

        {/* Confirmation Checkbox */}
        <div className="p-3 rounded-lg bg-slate-900 border border-slate-800">
          <label className="flex items-start gap-2.5 cursor-pointer text-xs text-slate-300">
            <input
              type="checkbox"
              id="confirm-closure-checkbox"
              checked={confirmed}
              onChange={(e) => setConfirmed(e.target.checked)}
              disabled={mutation.isPending}
              className="mt-0.5 rounded border-slate-700 bg-slate-950 text-sky-600 focus:ring-sky-500"
            />
            <span>
              Saya telah memeriksa kebenaran data pembukuan dan memahami bahwa <strong>penutupan buku ini bersifat final, permanen, dan tidak dapat dibatalkan atau diubah</strong>.
            </span>
          </label>
        </div>

        {/* Action Buttons */}
        <div className="flex justify-end gap-3 pt-2">
          <Button
            type="button"
            variant="secondary"
            onClick={onClose}
            disabled={mutation.isPending}
          >
            Batal
          </Button>
          <Button
            type="submit"
            variant="primary"
            isLoading={mutation.isPending}
            disabled={mutation.isPending || !confirmed || (difference !== null && difference !== 0 && !notes.trim())}
          >
            Konfirmasi Tutup Buku
          </Button>
        </div>
      </form>
    </Dialog>
  );
};
