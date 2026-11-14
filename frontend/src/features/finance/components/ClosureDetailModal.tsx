import React from 'react';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import type { FinancialPeriodClosure } from '../../../types/finance.ts';
import { formatRupiah } from '../utils/currency.ts';

const MONTH_NAMES = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

interface ClosureDetailModalProps {
  closure: FinancialPeriodClosure | null;
  open: boolean;
  onClose: () => void;
}

export const ClosureDetailModal: React.FC<ClosureDetailModalProps> = ({
  closure,
  open,
  onClose,
}) => {
  if (!closure) return null;

  const monthName = MONTH_NAMES[closure.period_month - 1] || `Bulan ${closure.period_month}`;
  const diff = closure.reconciliation_difference;

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title="Rincian Arsip Penutupan Buku Kas"
      description={`Arsip snapshot permanen tutup buku ${monthName} ${closure.period_year}.`}
      maxWidth="max-w-2xl"
    >
      <div className="space-y-4">
        {/* Immutable Notice */}
        <div className="p-3 rounded-lg bg-sky-950/40 border border-sky-800/60 text-sky-300 text-xs flex items-center justify-between">
          <div className="flex items-center gap-2">
            <svg className="w-4 h-4 text-sky-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <span className="font-medium">Snapshot Audit Terkunci (Immutable Ledger Snapshot)</span>
          </div>
          <span className="text-[11px] font-mono text-slate-400">ID: {closure.id.slice(0, 8)}...</span>
        </div>

        {/* Account & Period Meta */}
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 p-3.5 bg-slate-900 rounded-xl border border-slate-800 text-xs">
          <div>
            <span className="text-slate-400 block">Akun Kas:</span>
            <span className="font-semibold text-slate-200">{closure.account?.name || 'Kas Akun'}</span>
          </div>
          <div>
            <span className="text-slate-400 block">Periode Kalender:</span>
            <span className="font-semibold text-slate-200">{monthName} {closure.period_year}</span>
          </div>
          <div>
            <span className="text-slate-400 block">Rentang Tanggal:</span>
            <span className="font-medium text-slate-300">{closure.period_start} s/d {closure.period_end}</span>
          </div>
          <div>
            <span className="text-slate-400 block">Ditutup Oleh:</span>
            <span className="font-medium text-slate-200">{closure.closed_by.name || closure.closed_by.email || 'Pengurus'}</span>
          </div>
          <div className="sm:col-span-2">
            <span className="text-slate-400 block">Waktu Penutupan:</span>
            <span className="font-medium text-slate-300">
              {new Date(closure.closed_at).toLocaleString('id-ID', { dateStyle: 'full', timeStyle: 'medium' })}
            </span>
          </div>
        </div>

        {/* Financial Numbers Snapshot */}
        <div className="p-4 bg-slate-900 rounded-xl border border-slate-800 space-y-3">
          <div className="text-xs font-semibold text-slate-300 uppercase tracking-wider">
            Nilai Pembukuan Saat Penutupan
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
            <div>
              <span className="text-xs text-slate-400 block">Saldo Awal Buku:</span>
              <span className="font-semibold text-slate-200">{formatRupiah(closure.opening_balance)}</span>
            </div>
            <div>
              <span className="text-xs text-slate-400 block">Pemasukan Efektif:</span>
              <span className="font-semibold text-emerald-400">+{formatRupiah(closure.total_income)}</span>
              <span className="text-[10px] text-slate-500 block">
                (Dicatat: {formatRupiah(closure.posted_income)}, Dibalik: {formatRupiah(closure.reversed_income)})
              </span>
            </div>
            <div>
              <span className="text-xs text-slate-400 block">Pengeluaran Efektif:</span>
              <span className="font-semibold text-rose-400">-{formatRupiah(closure.total_expense)}</span>
              <span className="text-[10px] text-slate-500 block">
                (Dicatat: {formatRupiah(closure.posted_expense)}, Dibalik: {formatRupiah(closure.reversed_expense)})
              </span>
            </div>
            <div>
              <span className="text-xs text-slate-400 block">Pergerakan Bersih:</span>
              <span className={`font-semibold ${closure.net_movement >= 0 ? 'text-emerald-400' : 'text-rose-400'}`}>
                {closure.net_movement >= 0 ? '+' : ''}{formatRupiah(closure.net_movement)}
              </span>
            </div>
            <div className="sm:col-span-2">
              <span className="text-xs text-slate-400 block">Saldo Akhir Resmi (Authoritative Ledger):</span>
              <span className="font-bold text-slate-100 text-lg">{formatRupiah(closure.closing_balance)}</span>
            </div>
          </div>
          <div className="text-xs text-slate-400 border-t border-slate-800/80 pt-2 flex justify-between">
            <span>Jumlah Transaksi: {closure.transaction_count}</span>
            <span>Jumlah Transaksi Dibalik: {closure.reversal_count}</span>
          </div>
        </div>

        {/* Physical Cash Reconciliation Section */}
        <div className="p-4 bg-slate-950/70 rounded-xl border border-slate-800 space-y-3">
          <div className="text-xs font-semibold text-slate-300 uppercase tracking-wider flex justify-between items-center">
            <span>Rekonsiliasi Kas Fisik</span>
            {closure.is_reconciled ? (
              <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-sky-950 text-sky-400 border border-sky-800">
                Fisik Dicatat
              </span>
            ) : (
              <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-800 text-slate-400">
                Fisik Tidak Dicatat
              </span>
            )}
          </div>

          <div className="grid grid-cols-2 gap-3 text-sm">
            <div>
              <span className="text-xs text-slate-400 block">Fisik Kas Dihitung:</span>
              <span className="font-semibold text-slate-200">
                {closure.counted_cash_balance !== null ? formatRupiah(closure.counted_cash_balance) : 'Tidak ada catatan fisik'}
              </span>
            </div>
            <div>
              <span className="text-xs text-slate-400 block">Status Selisih:</span>
              {diff === null ? (
                <span className="text-xs text-slate-500">-</span>
              ) : diff === 0 ? (
                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-950 text-emerald-400 border border-emerald-800">
                  Cocok (Rp 0)
                </span>
              ) : diff > 0 ? (
                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-950 text-amber-300 border border-amber-800">
                  +{formatRupiah(diff)} (Kelebihan Fisik)
                </span>
              ) : (
                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-rose-950 text-rose-300 border border-rose-800">
                  -{formatRupiah(Math.abs(diff))} (Kekurangan Fisik)
                </span>
              )}
            </div>
          </div>

          {closure.reconciliation_notes && (
            <div className="text-xs space-y-1 bg-slate-900/80 p-3 rounded-lg border border-slate-800">
              <span className="font-medium text-slate-400 block">Catatan / Penjelasan Rekonsiliasi:</span>
              <p className="text-slate-200 whitespace-pre-wrap">{closure.reconciliation_notes}</p>
            </div>
          )}
        </div>

        {/* Read-only Dismiss Button */}
        <div className="flex justify-end pt-2">
          <Button variant="secondary" onClick={onClose}>
            Tutup
          </Button>
        </div>
      </div>
    </Dialog>
  );
};
