import React, { useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useAuth } from '../../../app/providers/useAuth.ts';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Badge } from '../../../components/ui/Badge.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { financeApi } from '../api/financeApi.ts';
import {
  useAttachTransactionEvidence,
  useFinancialTransaction,
  useTransactionEvidence,
} from '../api/financeQueries.ts';
import { ReverseTransactionDialog } from '../components/index.ts';
import { formatRupiah } from '../utils/currency.ts';
import {
  canRecordFinanceTransactions,
  canReverseFinanceTransactions,
} from '../utils/permissions.ts';

export const TransactionDetailPage: React.FC = () => {
  const { transactionId = '' } = useParams<{ transactionId: string }>();
  const { user } = useAuth();
  const canReverse = canReverseFinanceTransactions(user);
  const canRecord = canRecordFinanceTransactions(user);

  const [isReverseOpen, setIsReverseOpen] = useState(false);
  const [isDownloadingDoc, setIsDownloadingDoc] = useState(false);
  const [downloadingFileId, setDownloadingFileId] = useState<string | null>(null);
  const [documentError, setDocumentError] = useState<string | null>(null);
  const [documentSuccess, setDocumentSuccess] = useState<string | null>(null);
  const [attachError, setAttachError] = useState<string | null>(null);

  const fileInputRef = useRef<HTMLInputElement>(null);

  const {
    data: txResponse,
    isLoading,
    error,
  } = useFinancialTransaction(transactionId);

  const {
    data: evidenceResponse,
    isLoading: isEvidenceLoading,
  } = useTransactionEvidence(transactionId);

  const attachMutation = useAttachTransactionEvidence();

  const tx = txResponse?.data;

  // Combine primary evidence and any extra attached evidence files
  const evidenceFilesList = React.useMemo(() => {
    const list: Array<{ id: string; original_name: string; extension?: string; mime_type: string; size_bytes: number }> = [];
    const seenIds = new Set<string>();

    if (tx?.evidence_file) {
      list.push(tx.evidence_file);
      seenIds.add(tx.evidence_file.id);
    }

    if (tx?.evidence_files) {
      for (const f of tx.evidence_files) {
        if (!seenIds.has(f.id)) {
          list.push(f);
          seenIds.add(f.id);
        }
      }
    }

    if (evidenceResponse?.data) {
      for (const f of evidenceResponse.data) {
        if (!seenIds.has(f.id)) {
          list.push(f);
          seenIds.add(f.id);
        }
      }
    }

    return list;
  }, [tx, evidenceResponse]);

  const handleDownloadDocument = async (format: 'pdf' | 'html' = 'pdf') => {
    if (!tx || isDownloadingDoc) return;
    setDocumentError(null);
    setDocumentSuccess(null);

    try {
      setIsDownloadingDoc(true);

      if (format === 'html') {
        const { blob } = await financeApi.downloadTransactionDocument(tx.id, {
          format: 'html',
          disposition: 'inline',
        });
        const url = window.URL.createObjectURL(blob);
        window.open(url, '_blank');
        window.URL.revokeObjectURL(url);
        return;
      }

      const { blob, filename } = await financeApi.downloadTransactionDocument(tx.id, {
        format: 'pdf',
        disposition: 'attachment',
      });

      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);

      setDocumentSuccess(`Dokumen ${filename} berhasil diunduh.`);
    } catch (err) {
      setDocumentError(err instanceof Error ? err.message : 'Gagal mengunduh dokumen transaksi.');
    } finally {
      setIsDownloadingDoc(false);
    }
  };

  const handleDownloadEvidence = async (fileId: string, filenameFallback: string) => {
    if (!tx || downloadingFileId !== null) return;
    setDocumentError(null);

    try {
      setDownloadingFileId(fileId);
      const { blob, filename } = await financeApi.downloadTransactionEvidence(tx.id, fileId, {
        disposition: 'attachment',
      });

      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename || filenameFallback;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
    } catch (err) {
      setDocumentError(err instanceof Error ? err.message : 'Gagal mengunduh berkas bukti transaksi.');
    } finally {
      setDownloadingFileId(null);
    }
  };

  const handleFileAttach = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file || !tx) return;

    if (file.size > 5 * 1024 * 1024) {
      setAttachError('Ukuran berkas melebihi batas 5 MB.');
      if (fileInputRef.current) fileInputRef.current.value = '';
      return;
    }

    const allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    if (!allowed.includes(file.type)) {
      setAttachError('Format berkas tidak diizinkan. Gunakan PDF, JPG, atau PNG.');
      if (fileInputRef.current) fileInputRef.current.value = '';
      return;
    }

    setAttachError(null);

    try {
      await attachMutation.mutateAsync({
        id: tx.id,
        payload: { file },
      });
      setDocumentSuccess(`Bukti transaksi "${file.name}" berhasil dilampirkan.`);
      if (fileInputRef.current) fileInputRef.current.value = '';
    } catch (err) {
      setAttachError(err instanceof Error ? err.message : 'Gagal melampirkan berkas bukti.');
    }
  };

  if (error) {
    return (
      <div className="space-y-4">
        <Link
          to="/app/finance"
          className="text-xs text-slate-400 hover:text-slate-200 inline-flex items-center gap-1.5"
        >
          ← Kembali ke Kas & Keuangan
        </Link>
        <Alert
          type="error"
          title="Transaksi Tidak Ditemukan"
          message={error.message || 'Transaksi yang Anda cari tidak ditemukan atau berada di luar cakupan izin Anda.'}
          requestId={error.requestId}
        />
      </div>
    );
  }

  if (isLoading || !tx) {
    return (
      <div className="space-y-4">
        <div className="h-6 w-48 bg-slate-800 rounded animate-pulse" />
        <div className="h-64 bg-slate-900/60 border border-slate-800 rounded-2xl animate-pulse" />
      </div>
    );
  }

  const isIncome = tx.type === 'INCOME';
  const isReversalEntry = tx.is_reversal;
  const revId = tx.reversal?.id || tx.reversal_transaction_id;
  const hasBeenReversed = Boolean(tx.is_reversed || revId);
  const accountObj = tx.cash_account || tx.account;

  return (
    <div className="space-y-6 max-w-4xl">
      {/* Back link */}
      <div>
        <Link
          to={tx.cash_account_id ? `/app/finance/accounts/${tx.cash_account_id}` : '/app/finance'}
          className="text-xs text-slate-400 hover:text-slate-200 inline-flex items-center gap-1.5 transition-colors"
        >
          <span>← Kembali ke {accountObj?.name || 'Buku Kas'}</span>
        </Link>
      </div>

      {documentError && (
        <Alert
          type="error"
          title="Kesalahan Dokumen"
          message={documentError}
        />
      )}

      {documentSuccess && (
        <Alert
          type="success"
          title="Berhasil"
          message={documentSuccess}
        />
      )}

      {/* Main Detail Card */}
      <div className="rounded-2xl border border-slate-800 bg-slate-900/80 shadow-2xl overflow-hidden">
        {/* Header Strip */}
        <div className={`p-6 border-b border-slate-800/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 ${
          isReversalEntry
            ? 'bg-amber-950/20'
            : isIncome
            ? 'bg-emerald-950/20'
            : 'bg-rose-950/20'
        }`}>
          <div className="space-y-2">
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant={isIncome ? 'emerald' : 'rose'} size="md">
                {isIncome ? 'PEMASUKAN (INCOME)' : 'PENGELUARAN (EXPENSE)'}
              </Badge>

              {isReversalEntry ? (
                <Badge variant="amber" size="md">
                  REVERSAL ENTRY
                </Badge>
              ) : hasBeenReversed ? (
                <Badge variant="slate" size="md">
                  STATUS: DIBATALKAN
                </Badge>
              ) : (
                <Badge variant="slate" size="md">
                  STATUS: POSTED
                </Badge>
              )}
            </div>

            <div className="text-3xl font-extrabold font-mono tracking-tight text-white">
              <span className={isIncome ? 'text-emerald-400' : 'text-rose-400'}>
                {isIncome ? '+' : '-'}
              </span>
              {formatRupiah(tx.amount)}
            </div>
          </div>

          {/* Action buttons */}
          <div className="flex flex-wrap items-center gap-2">
            {/* Document PDF Generation Button */}
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => handleDownloadDocument('pdf')}
              isLoading={isDownloadingDoc}
              disabled={isDownloadingDoc}
              className="text-xs text-sky-400 border-sky-800 hover:bg-sky-950/40"
            >
              <svg className="w-4 h-4 mr-1 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <span>
                {isReversalEntry
                  ? 'Cetak Dokumen Koreksi'
                  : isIncome
                  ? 'Cetak Kwitansi'
                  : 'Cetak Voucher Pengeluaran'}
              </span>
            </Button>

            {/* Quick Print/Preview */}
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => handleDownloadDocument('html')}
              className="text-xs text-slate-400 hover:text-slate-200"
              title="Pratinjau Dokumen HTML"
            >
              <svg className="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </Button>

            {/* Reversal action if eligible */}
            {canReverse && !isReversalEntry && !hasBeenReversed && (
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setIsReverseOpen(true)}
                className="text-xs text-rose-400 border-rose-900/60 hover:bg-rose-950/40"
              >
                Batalkan Transaksi (Reverse)
              </Button>
            )}
          </div>
        </div>

        {/* Reversal Linkage Notice Banners */}
        {isReversalEntry && (
          <div className="p-4 bg-amber-950/40 border-b border-amber-900/40 text-xs text-amber-300 space-y-1.5">
            <div className="flex items-center gap-2 font-semibold text-amber-200">
              <svg className="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>Ini adalah Entri Pembalik (Compensating Reversal Entry)</span>
            </div>
            <p>
              Entri ini diterbitkan secara otomatis untuk membatalkan dampak saldo dari transaksi sebelumnya tanpa menghapus catatan historis.
            </p>
            {tx.original_transaction_id && (
              <div className="pt-1 flex items-center gap-2">
                <span className="text-slate-400">Merujuk ke transaksi asli: </span>
                <Link
                  to={`/app/finance/transactions/${tx.original_transaction_id}`}
                  className="font-mono text-amber-400 font-semibold underline hover:text-amber-300"
                >
                  Lihat Transaksi Asli (#{tx.original_transaction_id})
                </Link>
              </div>
            )}
            {tx.reversal_reason && (
              <div className="pt-1 text-slate-300">
                <span className="text-slate-400 font-medium">Alasan Koreksi / Reversal: </span>
                {tx.reversal_reason}
              </div>
            )}
          </div>
        )}

        {hasBeenReversed && (
          <div className="p-4 bg-slate-950/60 border-b border-slate-800 text-xs text-slate-300 space-y-1.5">
            <div className="flex items-center gap-2 font-semibold text-rose-300">
              <svg className="w-4 h-4 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
              <span>Transaksi Ini Telah Dibatalkan (Reversed)</span>
            </div>
            <p className="text-slate-400">
              Dampak keuangan dari transaksi ini telah dinetralkan oleh entri pembalik.
            </p>
            {revId && (
              <div className="pt-1 flex items-center gap-2">
                <span className="text-slate-400">Entri pembalik terbit: </span>
                <Link
                  to={`/app/finance/transactions/${revId}`}
                  className="font-mono text-emerald-400 font-semibold underline hover:text-emerald-300"
                >
                  Lihat Dokumen Koreksi (#{revId})
                </Link>
              </div>
            )}
          </div>
        )}

        {/* Detailed Fields Grid */}
        <div className="p-6 grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
          {/* Tanggal & Referensi */}
          <div className="space-y-4">
            <div>
              <div className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                Tanggal Transaksi
              </div>
              <div className="mt-1 text-sm font-medium text-slate-100 font-mono">
                {tx.transaction_date}
              </div>
            </div>

            <div>
              <div className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                No. Referensi / Kwitansi
              </div>
              <div className="mt-1 text-sm font-mono text-slate-200">
                {tx.reference_number || '-'}
              </div>
            </div>

            <div>
              <div className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                Kategori Pos Anggaran
              </div>
              <div className="mt-1 text-sm font-medium text-slate-100 flex items-center gap-2">
                <span>{tx.category?.name || '-'}</span>
                {tx.category?.code && (
                  <span className="text-[10px] font-mono text-slate-400 bg-slate-800 px-1.5 py-0.5 rounded">
                    {tx.category.code}
                  </span>
                )}
              </div>
            </div>
          </div>

          {/* Akun Kas & Deskripsi */}
          <div className="space-y-4">
            <div>
              <div className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                Akun Rekening Kas
              </div>
              <div className="mt-1">
                {accountObj ? (
                  <Link
                    to={`/app/finance/accounts/${accountObj.id}`}
                    className="text-sm font-medium text-emerald-400 hover:text-emerald-300 underline"
                  >
                    {accountObj.name}
                  </Link>
                ) : (
                  <span className="text-slate-300">-</span>
                )}
              </div>
            </div>

            <div>
              <div className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                Deskripsi / Keterangan
              </div>
              <div className="mt-1 text-xs text-slate-200 leading-relaxed bg-slate-950/40 p-3 rounded-lg border border-slate-800/80">
                {tx.description}
              </div>
            </div>

            <div>
              <div className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                ID Transaksi Sistem
              </div>
              <div className="mt-1 text-[11px] font-mono text-slate-400">
                {tx.id}
              </div>
            </div>
          </div>
        </div>

        {/* Supporting Evidence Documents Section */}
        <div className="border-t border-slate-800/80 p-6 bg-slate-950/40 space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <h3 className="text-sm font-bold text-slate-100 flex items-center gap-2">
                <svg className="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                </svg>
                <span>Bukti Transaksi & Dokumen Pendukung</span>
              </h3>
              <p className="text-[11px] text-slate-400 mt-0.5">
                Berkas bukti sah (nota, struk, kwitansi fisik, voucher) yang tersimpan aman di server.
              </p>
            </div>

            {/* Attach button if permitted */}
            {canRecord && !hasBeenReversed && (
              <div>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                  onChange={handleFileAttach}
                  className="hidden"
                  id="attach-evidence-input"
                />
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => fileInputRef.current?.click()}
                  isLoading={attachMutation.isPending}
                  disabled={attachMutation.isPending}
                  className="text-xs text-emerald-400 border-emerald-900/60 hover:bg-emerald-950/40"
                >
                  <svg className="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                  </svg>
                  <span>Unggah Bukti Tambahan</span>
                </Button>
              </div>
            )}
          </div>

          {attachError && (
            <Alert
              type="error"
              title="Gagal Melampirkan Bukti"
              message={attachError}
            />
          )}

          {/* Evidence List */}
          {isEvidenceLoading ? (
            <div className="space-y-2">
              <div className="h-12 bg-slate-900 rounded-lg animate-pulse" />
            </div>
          ) : evidenceFilesList.length > 0 ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              {evidenceFilesList.map((file) => {
                const isDownloadingThis = downloadingFileId === file.id;
                const ext = file.extension || file.original_name.split('.').pop() || 'file';

                return (
                  <div
                    key={file.id}
                    className="p-3 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-between gap-3 text-xs"
                  >
                    <div className="flex items-center gap-2.5 min-w-0">
                      <div className="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center shrink-0 text-slate-300 font-mono text-[10px] font-bold uppercase">
                        {ext}
                      </div>
                      <div className="min-w-0">
                        <div className="font-medium text-slate-200 truncate">
                          {file.original_name}
                        </div>
                        <div className="text-[10px] text-slate-400">
                          {(file.size_bytes / 1024).toFixed(1)} KB &bull; {file.mime_type}
                        </div>
                      </div>
                    </div>

                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => handleDownloadEvidence(file.id, `${file.original_name}.${ext}`)}
                      isLoading={isDownloadingThis}
                      disabled={isDownloadingThis}
                      className="text-xs text-emerald-400 hover:text-emerald-300 shrink-0"
                    >
                      <span>Unduh Bukti</span>
                    </Button>
                  </div>
                );
              })}
            </div>
          ) : (
            <div className="p-6 rounded-xl border border-dashed border-slate-800 text-center text-xs text-slate-500">
              Tidak ada berkas bukti pendukung yang dilampirkan pada transaksi ini.
            </div>
          )}
        </div>
      </div>

      {/* Reverse Modal */}
      <ReverseTransactionDialog
        transaction={tx}
        open={isReverseOpen}
        onClose={() => setIsReverseOpen(false)}
      />
    </div>
  );
};
