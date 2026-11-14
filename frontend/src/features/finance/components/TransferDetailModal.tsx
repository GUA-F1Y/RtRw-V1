import React from 'react';
import { Badge } from '../../../components/ui/Badge.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import type { CashTransfer } from '../../../types/finance.ts';
import { formatRupiah } from '../utils/currency.ts';

interface TransferDetailModalProps {
  transfer: CashTransfer | null;
  open: boolean;
  onClose: () => void;
  onReverseClick?: (transfer: CashTransfer) => void;
  canReverse?: boolean;
}

export const TransferDetailModal: React.FC<TransferDetailModalProps> = ({
  transfer,
  open,
  onClose,
  onReverseClick,
  canReverse = false,
}) => {
  if (!transfer) return null;

  const isReversal = Boolean(transfer.is_reversal);
  const isReversed = Boolean(transfer.is_reversed || transfer.reversal);

  const getStatusBadge = () => {
    if (isReversed) {
      return <Badge variant="rose" size="sm">Dibalikkan (Reversed)</Badge>;
    }
    if (isReversal) {
      return <Badge variant="amber" size="sm">Pembalikan (Reversal)</Badge>;
    }
    return <Badge variant="emerald" size="sm">Normal</Badge>;
  };

  const handleOpenReverse = () => {
    onClose();
    if (onReverseClick) {
      onReverseClick(transfer);
    }
  };

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title="Rincian Transfer Kas"
      description={`ID: ${transfer.id}`}
      maxWidth="max-w-xl"
    >
      <div className="space-y-4 text-xs">
        {/* Main Status & Amount Card */}
        <div className="p-4 rounded-xl bg-slate-900 border border-slate-800 space-y-3">
          <div className="flex items-center justify-between">
            <span className="text-slate-400 font-mono text-[11px]">
              {transfer.reference_number || `TRF-${transfer.id.slice(0, 8)}`}
            </span>
            {getStatusBadge()}
          </div>

          <div className="text-center py-2 bg-slate-950/60 rounded-xl border border-slate-800/80">
            <div className="text-[11px] text-slate-400">Nominal Transfer</div>
            <div className="text-2xl font-bold text-white tracking-tight font-mono mt-0.5">
              {formatRupiah(transfer.amount)}
            </div>
          </div>

          {/* Transfer Flow Visualizer */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
            {/* Source */}
            <div className="p-2.5 rounded-lg bg-slate-950/40 border border-slate-800">
              <div className="text-[10px] uppercase font-semibold text-rose-400 tracking-wider">
                Rekening Asal (Keluar)
              </div>
              <div className="mt-1 font-medium text-slate-200">
                {transfer.source_account?.name || 'Rekening Asal'}
              </div>
              <div className="text-[11px] text-slate-400 mt-0.5 flex items-center justify-between">
                <span>{transfer.source_account?.account_number || '-'}</span>
                <span className="font-semibold text-slate-300">
                  {transfer.source_account?.scope_type === 'RT'
                    ? `RT ${transfer.source_account.rt_number || ''}`
                    : 'RW'}
                </span>
              </div>
            </div>

            {/* Destination */}
            <div className="p-2.5 rounded-lg bg-slate-950/40 border border-slate-800">
              <div className="text-[10px] uppercase font-semibold text-emerald-400 tracking-wider">
                Rekening Tujuan (Masuk)
              </div>
              <div className="mt-1 font-medium text-slate-200">
                {transfer.destination_account?.name || 'Rekening Tujuan'}
              </div>
              <div className="text-[11px] text-slate-400 mt-0.5 flex items-center justify-between">
                <span>{transfer.destination_account?.account_number || '-'}</span>
                <span className="font-semibold text-slate-300">
                  {transfer.destination_account?.scope_type === 'RT'
                    ? `RT ${transfer.destination_account.rt_number || ''}`
                    : 'RW'}
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* If Reversal: Show original transfer info */}
        {isReversal && (
          <div className="p-3 rounded-xl bg-amber-950/30 border border-amber-800/40 space-y-1.5 text-amber-200">
            <div className="flex items-center gap-1.5 font-semibold text-amber-400">
              <svg className="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>Merupakan Transaksi Pembalik (Compensating Reversal)</span>
            </div>
            <div className="text-[11px] text-amber-300/90 pl-5 space-y-0.5">
              <div>
                <strong>Alasan Pembatalan:</strong> {transfer.reversal_reason || '-'}
              </div>
              {transfer.original_transfer && (
                <div>
                  <strong>Transfer Asal:</strong> {transfer.original_transfer.transfer_date} — {formatRupiah(transfer.original_transfer.amount)} ({transfer.original_transfer.description})
                </div>
              )}
            </div>
          </div>
        )}

        {/* If Reversed: Show reversal info */}
        {isReversed && transfer.reversal && (
          <div className="p-3 rounded-xl bg-rose-950/30 border border-rose-800/40 space-y-1.5 text-rose-200">
            <div className="flex items-center gap-1.5 font-semibold text-rose-400">
              <svg className="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
              <span>Transfer Ini Telah Dibatalkan (Reversed)</span>
            </div>
            <div className="text-[11px] text-rose-300/90 pl-5 space-y-0.5">
              <div>
                <strong>Tanggal Pembalikan:</strong> {transfer.reversal.transfer_date}
              </div>
              <div>
                <strong>Alasan:</strong> {transfer.reversal.reversal_reason || '-'}
              </div>
            </div>
          </div>
        )}

        {/* Information Table */}
        <div className="p-3.5 rounded-xl bg-slate-900 border border-slate-800 space-y-2">
          <div className="flex justify-between items-center text-slate-300 py-1 border-b border-slate-800/60">
            <span className="text-slate-400">Tanggal Transfer:</span>
            <span className="font-semibold">{transfer.transfer_date}</span>
          </div>

          <div className="flex justify-between items-center text-slate-300 py-1 border-b border-slate-800/60">
            <span className="text-slate-400">Keterangan:</span>
            <span className="font-medium text-right max-w-[280px]">{transfer.description}</span>
          </div>

          <div className="flex justify-between items-center text-slate-300 py-1 border-b border-slate-800/60">
            <span className="text-slate-400">Nomor Referensi:</span>
            <span className="font-mono text-slate-200">{transfer.reference_number || '-'}</span>
          </div>

          <div className="flex justify-between items-center text-slate-300 py-1 border-b border-slate-800/60">
            <span className="text-slate-400">Dicatat Oleh:</span>
            <span className="font-medium">
              {transfer.recorder?.name || 'Administrator'}
              {transfer.recorder?.email ? ` (${transfer.recorder.email})` : ''}
            </span>
          </div>

          <div className="flex justify-between items-center text-slate-300 py-1">
            <span className="text-slate-400">Waktu Pencatatan:</span>
            <span className="text-slate-400 font-mono text-[11px]">
              {new Date(transfer.created_at).toLocaleString('id-ID')}
            </span>
          </div>
        </div>

        {/* Evidence File Section (if any) */}
        {transfer.evidence_file && (
          <div className="p-3 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-between">
            <div className="flex items-center gap-2.5 truncate">
              <svg className="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
              </svg>
              <div className="truncate">
                <div className="font-medium text-slate-200 truncate">
                  {transfer.evidence_file.original_name}
                </div>
                <div className="text-[10px] text-slate-500">
                  {(transfer.evidence_file.extension || 'BERKAS').toUpperCase()} &bull; {(transfer.evidence_file.size_bytes / 1024).toFixed(0)} KB
                </div>
              </div>
            </div>
            <a
              href={`/api/v1/files/${transfer.evidence_file.id}/download`}
              target="_blank"
              rel="noreferrer"
              className="px-2.5 py-1 text-[11px] font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition-colors"
            >
              Unduh
            </a>
          </div>
        )}

        {/* Modal Actions */}
        <div className="flex items-center justify-between pt-3 border-t border-slate-800">
          <div>
            {canReverse && !isReversed && !isReversal && (
              <Button
                type="button"
                variant="danger"
                size="sm"
                onClick={handleOpenReverse}
                className="text-xs"
              >
                Batalkan Transfer (Reversal)
              </Button>
            )}
          </div>
          <Button type="button" variant="secondary" size="sm" onClick={onClose}>
            Tutup
          </Button>
        </div>
      </div>
    </Dialog>
  );
};
