import React, { useEffect, useRef, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import { fileApi } from '../../../lib/api/fileApi.ts';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type {
  FinancialTransaction,
  RecordTransactionPayload,
  TransactionType,
} from '../../../types/finance.ts';
import {
  useCashAccounts,
  useFinanceCategories,
  useRecordFinancialTransaction,
} from '../api/financeQueries.ts';
import { formatRupiah, parseIntegerRupiah } from '../utils/currency.ts';

interface RecordTransactionDialogProps {
  open: boolean;
  onClose: () => void;
  defaultAccountId?: string;
  defaultType?: TransactionType;
  onSuccess?: (transaction: FinancialTransaction) => void;
}

interface FormState {
  cash_account_id: string;
  type: TransactionType;
  category_id: string;
  amount_input: string;
  transaction_date: string;
  description: string;
  reference_number: string;
}

const getTodayString = () => new Date().toISOString().split('T')[0];

const getInitialForm = (defaultAccountId?: string, defaultType: TransactionType = 'INCOME'): FormState => ({
  cash_account_id: defaultAccountId || '',
  type: defaultType,
  category_id: '',
  amount_input: '',
  transaction_date: getTodayString(),
  description: '',
  reference_number: '',
});

export const RecordTransactionDialog: React.FC<RecordTransactionDialogProps> = ({
  open,
  onClose,
  defaultAccountId,
  defaultType = 'INCOME',
  onSuccess,
}) => {
  const mutation = useRecordFinancialTransaction();
  const idempotencyKeyRef = useRef<string>(generateIdempotencyKey());
  const lastPayloadRef = useRef<string | null>(null);

  const [form, setForm] = useState<FormState>(getInitialForm(defaultAccountId, defaultType));
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  // Evidence file state
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [fileError, setFileError] = useState<string | null>(null);
  const [isUploadingFile, setIsUploadingFile] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Query active accounts
  const { data: accountsResponse, isLoading: accountsLoading } = useCashAccounts(
    { status: 'active', per_page: 50 },
    { enabled: open }
  );

  // Query categories matching current type
  const { data: categoriesResponse, isLoading: categoriesLoading } = useFinanceCategories(
    { type: form.type },
    { enabled: open }
  );

  const activeAccounts = (accountsResponse?.data ?? []).filter((a) => a.status === 'ACTIVE');
  const availableCategories = categoriesResponse?.data ?? [];

  useEffect(() => {
    if (open) {
      setForm({
        ...getInitialForm(defaultAccountId, defaultType),
        cash_account_id: defaultAccountId || activeAccounts[0]?.id || '',
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
  }, [open, defaultAccountId, defaultType]);

  // When type changes (e.g. user toggles between INCOME and EXPENSE), clear category selection
  const handleTypeChange = (newType: TransactionType) => {
    setForm((prev) => ({
      ...prev,
      type: newType,
      category_id: '',
    }));
    setClientErrors((prev) => {
      const next = { ...prev };
      delete next.category_id;
      return next;
    });
  };

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
    const file = e.target.files?.[0];
    if (!file) {
      setSelectedFile(null);
      setFileError(null);
      return;
    }

    // 5MB limit
    if (file.size > 5 * 1024 * 1024) {
      setFileError('Ukuran berkas melebihi batas maksimal 5 MB.');
      setSelectedFile(null);
      return;
    }

    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    if (!allowedTypes.includes(file.type)) {
      setFileError('Format berkas tidak diizinkan. Gunakan PDF, JPG, atau PNG.');
      setSelectedFile(null);
      return;
    }

    setFileError(null);
    setSelectedFile(file);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (isUploadingFile || mutation.isPending) return;

    const errors: Record<string, string> = {};

    if (!form.cash_account_id) {
      errors.cash_account_id = 'Pilih akun kas tujuan transaksi.';
    }

    if (!form.category_id) {
      errors.category_id = 'Pilih kategori pos anggaran.';
    }

    // Integer-safe Rupiah validation (must be strictly positive integer)
    const amountParsed = parseIntegerRupiah(form.amount_input, { allowZero: false });
    if (!amountParsed.valid) {
      errors.amount = amountParsed.error || 'Nominal tidak valid.';
    }

    if (!form.transaction_date) {
      errors.transaction_date = 'Tanggal transaksi wajib diisi.';
    }

    if (!form.description.trim()) {
      errors.description = 'Deskripsi transaksi wajib diisi.';
    }

    if (Object.keys(errors).length > 0) {
      setClientErrors(errors);
      return;
    }

    setClientErrors({});

    let uploadedEvidenceFileId: string | undefined = undefined;

    if (selectedFile) {
      try {
        setIsUploadingFile(true);
        const targetAccount = activeAccounts.find((a) => a.id === form.cash_account_id);
        const scopeType = targetAccount?.rt_id ? 'rt' : (targetAccount?.rw_id ? 'rw' : 'organization');
        const scopeId = (targetAccount?.rt_id || targetAccount?.rw_id || targetAccount?.organization_id) as string;

        const uploadRes = await fileApi.uploadFile({
          file: selectedFile,
          scopeType,
          scopeId,
          visibility: 'private',
          category: 'finance_evidence',
        });

        uploadedEvidenceFileId = uploadRes.data?.id;
      } catch (err) {
        setIsUploadingFile(false);
        setFileError(err instanceof Error ? err.message : 'Gagal mengunggah berkas bukti transaksi.');
        return;
      } finally {
        setIsUploadingFile(false);
      }
    }

    const payload: RecordTransactionPayload = {
      cash_account_id: form.cash_account_id,
      category_id: form.category_id,
      type: form.type,
      amount: amountParsed.value as number,
      transaction_date: form.transaction_date,
      description: form.description.trim(),
      reference_number: form.reference_number.trim() || undefined,
      evidence_file_id: uploadedEvidenceFileId,
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
      // Handled by mutation error
    }
  };

  const parsedPreview = parseIntegerRupiah(form.amount_input);
  const validationErrors = mutation.error?.errors;
  const isSubmitting = mutation.isPending || isUploadingFile;

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title={form.type === 'INCOME' ? 'Catat Pemasukan Kas' : 'Catat Pengeluaran Kas'}
      maxWidth="max-w-md"
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        {mutation.error && (
          <Alert
            type="error"
            title={mutation.error.code === 'period_closed' ? 'Periode Akuntansi Telah Ditutup (Closed Period)' : 'Gagal Mencatat Transaksi'}
            message={mutation.error.message || 'Terjadi kesalahan saat memproses pencatatan transaksi.'}
            requestId={mutation.error.requestId}
          />
        )}

        {/* Direction Switcher (Pemasukan vs Pengeluaran) */}
        <div>
          <label className="block text-xs font-medium text-slate-300 mb-1.5">
            Jenis Transaksi
          </label>
          <div className="grid grid-cols-2 gap-2 p-1 rounded-xl bg-slate-950/60 border border-slate-800">
            <button
              type="button"
              onClick={() => handleTypeChange('INCOME')}
              className={`py-2 px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 ${
                form.type === 'INCOME'
                  ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-900/40'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M12 4v16m8-8H4" />
              </svg>
              <span>Pemasukan (Income)</span>
            </button>
            <button
              type="button"
              onClick={() => handleTypeChange('EXPENSE')}
              className={`py-2 px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5 ${
                form.type === 'EXPENSE'
                  ? 'bg-rose-600 text-white shadow-lg shadow-rose-900/40'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M20 12H4" />
              </svg>
              <span>Pengeluaran (Expense)</span>
            </button>
          </div>
        </div>

        {/* Cash Account Selection */}
        <div className="space-y-1.5">
          <label htmlFor="tx-account" className="block text-xs font-medium text-slate-300">
            Akun Kas <span className="text-rose-400">*</span>
          </label>
          <select
            id="tx-account"
            value={form.cash_account_id}
            onChange={(e) => setForm((prev) => ({ ...prev, cash_account_id: e.target.value }))}
            disabled={accountsLoading}
            className="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
          >
            <option value="">Pilih Akun Kas...</option>
            {activeAccounts.map((acc) => (
              <option key={acc.id} value={acc.id}>
                {acc.name} ({formatRupiah(acc.current_balance)})
              </option>
            ))}
          </select>
          <FieldError message={clientErrors.cash_account_id || validationErrors?.cash_account_id?.[0]} />
        </div>

        {/* Category Selection */}
        <div className="space-y-1.5">
          <label htmlFor="tx-category" className="block text-xs font-medium text-slate-300">
            Kategori Anggaran <span className="text-rose-400">*</span>
          </label>
          <select
            id="tx-category"
            value={form.category_id}
            onChange={(e) => setForm((prev) => ({ ...prev, category_id: e.target.value }))}
            disabled={categoriesLoading}
            className="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
          >
            <option value="">Pilih Kategori...</option>
            {availableCategories.map((cat) => (
              <option key={cat.id} value={cat.id}>
                {cat.name} {cat.code ? `(${cat.code})` : ''}
              </option>
            ))}
          </select>
          <FieldError message={clientErrors.category_id || validationErrors?.category_id?.[0]} />
        </div>

        {/* Nominal Amount */}
        <div className="space-y-1.5">
          <label htmlFor="tx-amount" className="block text-xs font-medium text-slate-300">
            Nominal Transaksi (Rp) <span className="text-rose-400">*</span>
          </label>
          <div className="relative">
            <span className="absolute left-3 top-2 text-xs font-mono text-slate-400 select-none">
              Rp
            </span>
            <input
              id="tx-amount"
              type="text"
              placeholder="cth. 150.000 atau 150000"
              value={form.amount_input}
              onChange={(e) => handleAmountChange(e.target.value)}
              className="w-full pl-10 pr-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs font-mono placeholder-slate-500 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
          </div>
          {parsedPreview.valid && parsedPreview.value !== undefined && (
            <div className={`text-[11px] font-mono ${form.type === 'INCOME' ? 'text-emerald-400' : 'text-rose-400'}`}>
              Terbaca: {form.type === 'INCOME' ? '+' : '-'}{formatRupiah(parsedPreview.value)}
            </div>
          )}
          <FieldError message={clientErrors.amount || validationErrors?.amount?.[0]} />
        </div>

        {/* Transaction Date & Reference Number in 2 columns */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          {/* Date */}
          <div className="space-y-1.5">
            <label htmlFor="tx-date" className="block text-xs font-medium text-slate-300">
              Tanggal Transaksi <span className="text-rose-400">*</span>
            </label>
            <input
              id="tx-date"
              type="date"
              value={form.transaction_date}
              onChange={(e) => setForm((prev) => ({ ...prev, transaction_date: e.target.value }))}
              className="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
            <FieldError message={clientErrors.transaction_date || validationErrors?.transaction_date?.[0]} />
          </div>

          {/* Reference Number */}
          <div className="space-y-1.5">
            <label htmlFor="tx-ref" className="block text-xs font-medium text-slate-300">
              No. Referensi / Kwitansi (Opsional)
            </label>
            <input
              id="tx-ref"
              type="text"
              placeholder="cth. KWT-2026-001"
              value={form.reference_number}
              onChange={(e) => setForm((prev) => ({ ...prev, reference_number: e.target.value }))}
              className="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs font-mono placeholder-slate-500 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
            <FieldError message={validationErrors?.reference_number?.[0]} />
          </div>
        </div>

        {/* Evidence File Upload (Optional) */}
        <div className="space-y-1.5">
          <label htmlFor="tx-evidence" className="block text-xs font-medium text-slate-300">
            Bukti Transaksi (Opsional: PDF, JPG, PNG &bull; Maks 5MB)
          </label>
          <input
            id="tx-evidence"
            ref={fileInputRef}
            type="file"
            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
            onChange={handleFileChange}
            className="hidden"
          />
          {selectedFile ? (
            <div className="flex items-center justify-between p-2.5 rounded-lg bg-slate-800/80 border border-slate-700 text-xs text-slate-200">
              <div className="flex items-center gap-2 truncate">
                <svg className="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span className="truncate font-medium">{selectedFile.name}</span>
                <span className="text-[10px] text-slate-400 shrink-0">
                  ({(selectedFile.size / 1024).toFixed(1)} KB)
                </span>
              </div>
              <button
                type="button"
                onClick={() => {
                  setSelectedFile(null);
                  setFileError(null);
                  if (fileInputRef.current) fileInputRef.current.value = '';
                }}
                className="text-xs text-rose-400 hover:text-rose-300 ml-2 shrink-0 font-medium"
              >
                Hapus
              </button>
            </div>
          ) : (
            <button
              type="button"
              onClick={() => fileInputRef.current?.click()}
              className="w-full py-2.5 px-3 rounded-lg border border-dashed border-slate-700 hover:border-slate-600 bg-slate-800/40 hover:bg-slate-800/60 text-xs text-slate-400 hover:text-slate-200 flex items-center justify-center gap-2 transition-colors"
            >
              <svg className="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
              </svg>
              <span>Pilih Berkas Bukti Transaksi</span>
            </button>
          )}
          <FieldError message={fileError} />
        </div>

        {/* Description */}
        <div className="space-y-1.5">
          <label htmlFor="tx-description" className="block text-xs font-medium text-slate-300">
            Deskripsi Transaksi <span className="text-rose-400">*</span>
          </label>
          <textarea
            id="tx-description"
            rows={2}
            placeholder="Jelaskan rincian keperluan atau sumber transaksi ini..."
            value={form.description}
            onChange={(e) => setForm((prev) => ({ ...prev, description: e.target.value }))}
            className="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs placeholder-slate-500 focus:ring-2 focus:ring-emerald-500 focus:outline-none resize-none"
          />
          <FieldError message={clientErrors.description || validationErrors?.description?.[0]} />
        </div>

        {/* Actions */}
        <div className="pt-3 border-t border-slate-800 flex items-center justify-end gap-2">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={onClose}
            disabled={isSubmitting}
          >
            Batal
          </Button>
          <Button
            type="submit"
            variant={form.type === 'INCOME' ? 'primary' : 'danger'}
            size="sm"
            isLoading={isSubmitting}
            disabled={isSubmitting}
          >
            {isUploadingFile
              ? 'Mengunggah Bukti...'
              : form.type === 'INCOME'
              ? 'Catat Pemasukan'
              : 'Catat Pengeluaran'}
          </Button>
        </div>
      </form>
    </Dialog>
  );
};
