import React, { useEffect, useRef, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import { fileApi } from '../../../lib/api/fileApi.ts';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type { CashTransfer, RecordTransferPayload } from '../../../types/finance.ts';
import { useCashAccounts, useRecordCashTransfer } from '../api/financeQueries.ts';
import { formatRupiah, parseIntegerRupiah } from '../utils/currency.ts';

interface RecordTransferDialogProps {
  open: boolean;
  onClose: () => void;
  defaultSourceAccountId?: string;
  defaultDestinationAccountId?: string;
  onSuccess?: (transfer: CashTransfer) => void;
}

interface FormState {
  source_cash_account_id: string;
  destination_cash_account_id: string;
  amount_input: string;
  transfer_date: string;
  description: string;
  reference_number: string;
}

const getTodayString = () => new Date().toISOString().split('T')[0];

const getInitialForm = (defaultSourceId?: string, defaultDestId?: string): FormState => ({
  source_cash_account_id: defaultSourceId || '',
  destination_cash_account_id: defaultDestId || '',
  amount_input: '',
  transfer_date: getTodayString(),
  description: '',
  reference_number: '',
});

export const RecordTransferDialog: React.FC<RecordTransferDialogProps> = ({
  open,
  onClose,
  defaultSourceAccountId,
  defaultDestinationAccountId,
  onSuccess,
}) => {
  const mutation = useRecordCashTransfer();
  const idempotencyKeyRef = useRef<string>(generateIdempotencyKey());
  const lastPayloadRef = useRef<string | null>(null);

  const [form, setForm] = useState<FormState>(
    getInitialForm(defaultSourceAccountId, defaultDestinationAccountId)
  );
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  // Evidence file state
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [fileError, setFileError] = useState<string | null>(null);
  const [isUploadingFile, setIsUploadingFile] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Query active accounts
  const { data: accountsResponse, isLoading: accountsLoading } = useCashAccounts(
    { status: 'active', per_page: 100 },
    { enabled: open }
  );

  const activeAccounts = (accountsResponse?.data ?? []).filter((a) => a.status === 'ACTIVE');

  useEffect(() => {
    if (open) {
      const srcId = defaultSourceAccountId || activeAccounts[0]?.id || '';
      const destId =
        defaultDestinationAccountId ||
        activeAccounts.find((a) => a.id !== srcId)?.id ||
        '';

      setForm({
        ...getInitialForm(defaultSourceAccountId, defaultDestinationAccountId),
        source_cash_account_id: srcId,
        destination_cash_account_id: destId,
      });
      setClientErrors({});
      setSelectedFile(null);
      setFileError(null);
      setIsUploadingFile(false);
      if (fileInputRef.current) fileInputRef.current.value = '';
      mutation.reset();
      idempotencyKeyRef.current = generateIdempotencyKey();
      lastPayloadRef.current = null;
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, defaultSourceAccountId, defaultDestinationAccountId]);

  const sourceAccount = activeAccounts.find((a) => a.id === form.source_cash_account_id);
  const destinationAccount = activeAccounts.find(
    (a) => a.id === form.destination_cash_account_id
  );

  const parsedResult = parseIntegerRupiah(form.amount_input);
  const parsedAmount = parsedResult.value ?? 0;
  const isInsufficientFunds =
    sourceAccount !== undefined && parsedAmount > 0 && parsedAmount > sourceAccount.current_balance;
  const isSameAccount =
    Boolean(form.source_cash_account_id) &&
    Boolean(form.destination_cash_account_id) &&
    form.source_cash_account_id === form.destination_cash_account_id;

  const handleAmountChange = (val: string) => {
    setForm((prev) => ({ ...prev, amount_input: val }));
    if (clientErrors.amount) {
      setClientErrors((prev) => {
        const next = { ...prev };
        delete next.amount;
        return next;
      });
    }
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] || null;
    setFileError(null);

    if (!file) {
      setSelectedFile(null);
      return;
    }

    const maxBytes = 10 * 1024 * 1024; // 10MB
    if (file.size > maxBytes) {
      setFileError('Ukuran berkas melebihi batas maksimal 10MB.');
      setSelectedFile(null);
      return;
    }

    const allowedMimes = [
      'application/pdf',
      'image/jpeg',
      'image/png',
      'image/webp',
    ];
    if (!allowedMimes.includes(file.type)) {
      setFileError('Tipe berkas harus berupa PDF, JPG, PNG, atau WebP.');
      setSelectedFile(null);
      return;
    }

    setSelectedFile(file);
  };

  const handleRemoveFile = () => {
    setSelectedFile(null);
    setFileError(null);
    if (fileInputRef.current) fileInputRef.current.value = '';
  };

  const validate = (): boolean => {
    const errors: Record<string, string> = {};

    if (!form.source_cash_account_id) {
      errors.source_cash_account_id = 'Pilih rekening kas asal.';
    }

    if (!form.destination_cash_account_id) {
      errors.destination_cash_account_id = 'Pilih rekening kas tujuan.';
    }

    if (
      form.source_cash_account_id &&
      form.destination_cash_account_id &&
      form.source_cash_account_id === form.destination_cash_account_id
    ) {
      errors.destination_cash_account_id = 'Rekening kas asal dan tujuan tidak boleh sama.';
    }

    if (!form.amount_input.trim()) {
      errors.amount = 'Nominal transfer wajib diisi.';
    } else if (!parsedResult.valid || parsedAmount <= 0) {
      errors.amount = parsedResult.error || 'Nominal transfer harus lebih besar dari Rp 0.';
    } else if (sourceAccount && parsedAmount > sourceAccount.current_balance) {
      errors.amount = `Saldo rekening asal tidak mencukupi (Tersedia: ${formatRupiah(sourceAccount.current_balance)}).`;
    }

    if (!form.transfer_date) {
      errors.transfer_date = 'Tanggal transfer wajib diisi.';
    }

    if (!form.description.trim()) {
      errors.description = 'Keterangan transfer wajib diisi.';
    } else if (form.description.trim().length < 3) {
      errors.description = 'Keterangan transfer minimal 3 karakter.';
    }

    setClientErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validate()) return;

    let uploadedFileId: string | null = null;
    if (selectedFile) {
      try {
        setIsUploadingFile(true);
        const targetAccount = activeAccounts.find((a) => a.id === form.source_cash_account_id);
        const scopeType = targetAccount?.rt_id ? 'rt' : (targetAccount?.rw_id ? 'rw' : 'organization');
        const scopeId = (targetAccount?.rt_id || targetAccount?.rw_id || targetAccount?.organization_id) as string;

        const uploadRes = await fileApi.uploadFile({
          file: selectedFile,
          scopeType,
          scopeId,
          visibility: 'private',
          category: 'finance_evidence',
        });
        uploadedFileId = uploadRes.data?.id;
      } catch (err: unknown) {
        setIsUploadingFile(false);
        const uploadErr = err as { message?: string };
        setFileError(uploadErr.message || 'Gagal mengunggah berkas bukti transfer.');
        return;
      } finally {
        setIsUploadingFile(false);
      }
    }

    const payload: RecordTransferPayload = {
      source_cash_account_id: form.source_cash_account_id,
      destination_cash_account_id: form.destination_cash_account_id,
      amount: parsedAmount,
      transfer_date: form.transfer_date,
      description: form.description.trim(),
      reference_number: form.reference_number.trim() || undefined,
      evidence_file_id: uploadedFileId || undefined,
    };

    const payloadString = JSON.stringify(payload);
    if (lastPayloadRef.current !== null && lastPayloadRef.current !== payloadString) {
      idempotencyKeyRef.current = generateIdempotencyKey();
    }
    lastPayloadRef.current = payloadString;

    try {
      const res = await mutation.mutateAsync({
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
  const isInsufficientFundsApi = mutation.error?.code === 'insufficient_funds';
  const isSameAccountApi = mutation.error?.code === 'same_account_transfer';
  const isForbiddenApi = mutation.error?.status === 403;

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title="Transfer Kas Antar Rekening"
      description="Mutasi dana internal antar rekening kas RW dan RT tanpa menghasilkan pendapatan atau belanja operasional."
      maxWidth="max-w-xl"
    >
      <form onSubmit={handleSubmit} className="space-y-4 text-xs">
        {/* Closed Period Alert */}
        {isPeriodClosed && (
          <Alert
            type="error"
            title="Periode Telah Ditutup"
            message={
              mutation.error?.message ||
              'Tanggal transfer ini berada pada periode akuntansi yang telah ditutup. Tidak dapat melakukan mutasi pada periode yang telah dikunci.'
            }
          />
        )}

        {/* Insufficient Funds Alert */}
        {isInsufficientFundsApi && (
          <Alert
            type="error"
            title="Saldo Rekening Asal Tidak Mencukupi"
            message={
              mutation.error?.message ||
              'Saldo kas pada rekening asal tidak mencukupi untuk memenuhi nominal transfer.'
            }
          />
        )}

        {/* Same Account Alert */}
        {isSameAccountApi && (
          <Alert
            type="error"
            title="Rekening Asal dan Tujuan Sama"
            message="Rekening asal dan tujuan transfer tidak boleh sama. Silakan pilih dua rekening yang berbeda."
          />
        )}

        {/* Forbidden Alert */}
        {isForbiddenApi && (
          <Alert
            type="error"
            title="Akses Ditolak (403)"
            message={
              mutation.error?.message ||
              'Anda tidak memiliki izin untuk melakukan transfer antara cakupan rekening yang dipilih.'
            }
          />
        )}

        {/* General Error Alert */}
        {mutation.isError &&
          !isPeriodClosed &&
          !isInsufficientFundsApi &&
          !isSameAccountApi &&
          !isForbiddenApi && (
            <Alert
              type="error"
              title="Gagal Menyimpan Transfer"
              message={mutation.error?.message || 'Terjadi kesalahan saat memproses transfer kas.'}
            />
          )}

        {/* Accounts Selector Section */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5 p-3 rounded-xl bg-slate-900/80 border border-slate-800">
          {/* Source Account */}
          <div className="space-y-1.5">
            <label className="block text-slate-300 font-medium">
              Rekening Asal (Sumber Dana) <span className="text-rose-400">*</span>
            </label>
            <select
              value={form.source_cash_account_id}
              onChange={(e) => {
                setForm((prev) => ({ ...prev, source_cash_account_id: e.target.value }));
                if (clientErrors.source_cash_account_id) {
                  setClientErrors((prev) => {
                    const next = { ...prev };
                    delete next.source_cash_account_id;
                    return next;
                  });
                }
              }}
              disabled={accountsLoading || mutation.isPending}
              className="w-full px-2.5 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs focus:ring-1 focus:ring-emerald-500 focus:outline-none"
            >
              <option value="">Pilih Rekening Asal...</option>
              {activeAccounts.map((acc) => (
                <option key={acc.id} value={acc.id}>
                  {acc.name} ({acc.rt_id ? `RT ${acc.rt_id.slice(0, 4)}` : 'RW'}) — Saldo: {formatRupiah(acc.current_balance)}
                </option>
              ))}
            </select>
            {sourceAccount && (
              <div className="flex items-center justify-between text-[11px] px-1 text-slate-400">
                <span>Saldo Tersedia:</span>
                <span className={`font-semibold ${sourceAccount.current_balance > 0 ? 'text-emerald-400' : 'text-slate-400'}`}>
                  {formatRupiah(sourceAccount.current_balance)}
                </span>
              </div>
            )}
            <FieldError
              message={clientErrors.source_cash_account_id || validationErrors?.source_cash_account_id?.[0]}
            />
          </div>

          {/* Destination Account */}
          <div className="space-y-1.5">
            <label className="block text-slate-300 font-medium">
              Rekening Tujuan (Penerima) <span className="text-rose-400">*</span>
            </label>
            <select
              value={form.destination_cash_account_id}
              onChange={(e) => {
                setForm((prev) => ({ ...prev, destination_cash_account_id: e.target.value }));
                if (clientErrors.destination_cash_account_id) {
                  setClientErrors((prev) => {
                    const next = { ...prev };
                    delete next.destination_cash_account_id;
                    return next;
                  });
                }
              }}
              disabled={accountsLoading || mutation.isPending}
              className="w-full px-2.5 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs focus:ring-1 focus:ring-emerald-500 focus:outline-none"
            >
              <option value="">Pilih Rekening Tujuan...</option>
              {activeAccounts.map((acc) => (
                <option key={acc.id} value={acc.id} disabled={acc.id === form.source_cash_account_id}>
                  {acc.name} ({acc.rt_id ? `RT ${acc.rt_id.slice(0, 4)}` : 'RW'}) {acc.id === form.source_cash_account_id ? '(Sama dengan Asal)' : ''}
                </option>
              ))}
            </select>
            {destinationAccount && (
              <div className="flex items-center justify-between text-[11px] px-1 text-slate-400">
                <span>Saldo Saat Ini:</span>
                <span className="font-semibold text-slate-300">
                  {formatRupiah(destinationAccount.current_balance)}
                </span>
              </div>
            )}
            <FieldError
              message={clientErrors.destination_cash_account_id || validationErrors?.destination_cash_account_id?.[0]}
            />
          </div>
        </div>

        {/* Warning if same account */}
        {isSameAccount && (
          <div className="p-2.5 rounded-lg bg-amber-950/40 border border-amber-800/60 text-amber-300 text-xs flex items-center gap-2">
            <svg className="w-4 h-4 shrink-0 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span>Rekening asal dan tujuan harus berbeda.</span>
          </div>
        )}

        {/* Amount & Date */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          {/* Amount Input */}
          <div className="space-y-1.5">
            <label className="block text-slate-300 font-medium">
              Nominal Transfer (Rp) <span className="text-rose-400">*</span>
            </label>
            <div className="relative">
              <span className="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 font-medium">
                Rp
              </span>
              <input
                type="text"
                value={form.amount_input}
                onChange={(e) => handleAmountChange(e.target.value)}
                placeholder="0"
                disabled={mutation.isPending}
                className="w-full pl-9 pr-2.5 py-1.5 rounded-lg bg-slate-900 border border-slate-700 text-slate-100 text-xs font-mono focus:ring-1 focus:ring-emerald-500 focus:outline-none"
              />
            </div>
            {parsedAmount > 0 && (
              <div className="text-[11px] text-slate-400">
                Terbaca: <span className="text-emerald-400 font-semibold">{formatRupiah(parsedAmount)}</span>
              </div>
            )}
            {isInsufficientFunds && (
              <div className="text-[11px] text-rose-400 font-medium flex items-center gap-1">
                <span>⚠️ Nominal melebihi saldo kas asal yang tersedia.</span>
              </div>
            )}
            <FieldError message={clientErrors.amount || validationErrors?.amount?.[0]} />
          </div>

          {/* Transfer Date */}
          <div className="space-y-1.5">
            <label className="block text-slate-300 font-medium">
              Tanggal Transfer <span className="text-rose-400">*</span>
            </label>
            <input
              type="date"
              value={form.transfer_date}
              onChange={(e) => {
                setForm((prev) => ({ ...prev, transfer_date: e.target.value }));
                if (clientErrors.transfer_date) {
                  setClientErrors((prev) => {
                    const next = { ...prev };
                    delete next.transfer_date;
                    return next;
                  });
                }
              }}
              disabled={mutation.isPending}
              className="w-full px-2.5 py-1.5 rounded-lg bg-slate-900 border border-slate-700 text-slate-100 text-xs focus:ring-1 focus:ring-emerald-500 focus:outline-none"
            />
            <FieldError
              message={clientErrors.transfer_date || validationErrors?.transfer_date?.[0]}
            />
          </div>
        </div>

        {/* Description & Reference */}
        <div className="space-y-3">
          <div className="space-y-1.5">
            <label className="block text-slate-300 font-medium">
              Keterangan / Tujuan Transfer <span className="text-rose-400">*</span>
            </label>
            <textarea
              rows={2}
              value={form.description}
              onChange={(e) => {
                setForm((prev) => ({ ...prev, description: e.target.value }));
                if (clientErrors.description) {
                  setClientErrors((prev) => {
                    const next = { ...prev };
                    delete next.description;
                    return next;
                  });
                }
              }}
              placeholder="Contoh: Penyaluran dana kas operasional RW ke kas RT 001 untuk kegiatan ronda"
              disabled={mutation.isPending}
              className="w-full px-2.5 py-1.5 rounded-lg bg-slate-900 border border-slate-700 text-slate-100 text-xs focus:ring-1 focus:ring-emerald-500 focus:outline-none resize-none"
            />
            <FieldError
              message={clientErrors.description || validationErrors?.description?.[0]}
            />
          </div>

          <div className="space-y-1.5">
            <label className="block text-slate-300 font-medium">
              Nomor Referensi / Berita Acara <span className="text-slate-500 font-normal">(Opsional)</span>
            </label>
            <input
              type="text"
              value={form.reference_number}
              onChange={(e) => setForm((prev) => ({ ...prev, reference_number: e.target.value }))}
              placeholder="Contoh: TRF-202610-001"
              disabled={mutation.isPending}
              className="w-full px-2.5 py-1.5 rounded-lg bg-slate-900 border border-slate-700 text-slate-100 text-xs focus:ring-1 focus:ring-emerald-500 focus:outline-none"
            />
            <FieldError message={validationErrors?.reference_number?.[0]} />
          </div>
        </div>

        {/* Evidence File Upload (Optional) */}
        <div className="space-y-1.5 pt-1">
          <label className="block text-slate-300 font-medium">
            Bukti Transfer / Dokumen Pendukung <span className="text-slate-500 font-normal">(Opsional)</span>
          </label>
          <div className="p-2.5 rounded-lg bg-slate-900/60 border border-dashed border-slate-700 flex items-center justify-between gap-3">
            {selectedFile ? (
              <div className="flex items-center gap-2 text-slate-200 truncate flex-1">
                <svg className="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span className="truncate">{selectedFile.name}</span>
                <span className="text-[10px] text-slate-500 shrink-0">
                  ({(selectedFile.size / 1024).toFixed(0)} KB)
                </span>
              </div>
            ) : (
              <div className="text-[11px] text-slate-400">
                Pilih berkas PDF, JPG, PNG, atau WebP (maks. 10MB)
              </div>
            )}

            <div className="flex items-center gap-2 shrink-0">
              {selectedFile ? (
                <button
                  type="button"
                  onClick={handleRemoveFile}
                  disabled={mutation.isPending || isUploadingFile}
                  className="px-2 py-1 text-[11px] text-rose-400 hover:text-rose-300 hover:bg-rose-950/30 rounded transition-colors"
                >
                  Hapus
                </button>
              ) : (
                <label className="cursor-pointer px-2.5 py-1 text-[11px] font-medium bg-slate-800 hover:bg-slate-700 text-slate-200 rounded border border-slate-700 transition-colors">
                  Pilih Berkas
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png,.webp"
                    onChange={handleFileChange}
                    className="hidden"
                    disabled={mutation.isPending || isUploadingFile}
                  />
                </label>
              )}
            </div>
          </div>
          {fileError && <FieldError message={fileError} />}
        </div>

        {/* Action Buttons */}
        <div className="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-800">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={onClose}
            disabled={mutation.isPending || isUploadingFile}
          >
            Batal
          </Button>
          <Button
            type="submit"
            variant="primary"
            size="sm"
            disabled={mutation.isPending || isUploadingFile || isSameAccount}
          >
            {mutation.isPending || isUploadingFile ? 'Memproses Transfer...' : 'Kirim Transfer Kas'}
          </Button>
        </div>
      </form>
    </Dialog>
  );
};
