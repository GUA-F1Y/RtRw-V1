import React, { useEffect, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import type { CashAccount, UpdateCashAccountPayload } from '../../../types/finance.ts';
import { useUpdateCashAccount } from '../api/financeQueries.ts';

interface EditAccountDialogProps {
  account: CashAccount | null;
  open: boolean;
  onClose: () => void;
  onSuccess?: () => void;
}

interface FormState {
  name: string;
  account_number: string;
  description: string;
}

export const EditAccountDialog: React.FC<EditAccountDialogProps> = ({
  account,
  open,
  onClose,
  onSuccess,
}) => {
  const mutation = useUpdateCashAccount();
  const [form, setForm] = useState<FormState>({
    name: '',
    account_number: '',
    description: '',
  });
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (account && open) {
      setForm({
        name: account.name || '',
        account_number: account.account_number || '',
        description: account.description || '',
      });
      setClientErrors({});
      mutation.reset();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [account, open]);

  if (!account) return null;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.name.trim()) {
      setClientErrors({ name: 'Nama akun kas wajib diisi.' });
      return;
    }

    setClientErrors({});

    const payload: UpdateCashAccountPayload = {
      name: form.name.trim(),
      account_number: form.account_number.trim() || undefined,
      description: form.description.trim() || undefined,
    };

    try {
      await mutation.mutateAsync({ id: account.id, payload });
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
      title="Ubah Rincian Akun Kas"
      description={`Perbarui informasi dasar untuk akun "${account.name}". Saldo awal dan cakupan kewilayahan tidak dapat diubah.`}
      maxWidth="max-w-md"
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        {generalError && (
          <Alert
            type="error"
            title="Gagal Menyimpan Perubahan"
            message={generalError}
            requestId={mutation.error?.requestId}
          />
        )}

        {/* Account Name */}
        <div className="space-y-1.5">
          <label htmlFor="edit-account-name" className="block text-xs font-medium text-slate-300">
            Nama Akun Kas <span className="text-rose-400">*</span>
          </label>
          <input
            id="edit-account-name"
            type="text"
            value={form.name}
            onChange={(e) => setForm((prev) => ({ ...prev, name: e.target.value }))}
            className="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs placeholder-slate-500 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
          />
          <FieldError message={clientErrors.name || validationErrors?.name?.[0]} />
        </div>

        {/* Account Number */}
        <div className="space-y-1.5">
          <label htmlFor="edit-account-number" className="block text-xs font-medium text-slate-300">
            Nomor Rekening / Kode Referensi
          </label>
          <input
            id="edit-account-number"
            type="text"
            value={form.account_number}
            onChange={(e) => setForm((prev) => ({ ...prev, account_number: e.target.value }))}
            className="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs font-mono placeholder-slate-500 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
          />
          <FieldError message={validationErrors?.account_number?.[0]} />
        </div>

        {/* Description */}
        <div className="space-y-1.5">
          <label htmlFor="edit-account-description" className="block text-xs font-medium text-slate-300">
            Deskripsi / Keterangan
          </label>
          <textarea
            id="edit-account-description"
            rows={3}
            value={form.description}
            onChange={(e) => setForm((prev) => ({ ...prev, description: e.target.value }))}
            className="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs placeholder-slate-500 focus:ring-2 focus:ring-emerald-500 focus:outline-none resize-none"
          />
          <FieldError message={validationErrors?.description?.[0]} />
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
            variant="primary"
            size="sm"
            isLoading={mutation.isPending}
            disabled={mutation.isPending}
          >
            Simpan Perubahan
          </Button>
        </div>
      </form>
    </Dialog>
  );
};
