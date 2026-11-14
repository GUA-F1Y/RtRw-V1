import React, { useEffect, useRef, useState } from 'react';
import { useAuth } from '../../../app/providers/useAuth.ts';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type { CreateCashAccountPayload } from '../../../types/finance.ts';
import type { RtUnit } from '../../../types/organization.ts';
import { useRwWithRts } from '../../hierarchy/api/hierarchyQueries.ts';
import { useCreateCashAccount } from '../api/financeQueries.ts';
import { formatRupiah, parseIntegerRupiah } from '../utils/currency.ts';

interface CreateAccountDialogProps {
  open: boolean;
  onClose: () => void;
  onSuccess?: (accountId: string) => void;
}

interface FormState {
  name: string;
  scopeType: 'rw' | 'rt';
  rt_id: string;
  account_number: string;
  description: string;
  opening_balance_input: string;
}

const INITIAL_FORM: FormState = {
  name: '',
  scopeType: 'rw',
  rt_id: '',
  account_number: '',
  description: '',
  opening_balance_input: '0',
};

export const CreateAccountDialog: React.FC<CreateAccountDialogProps> = ({
  open,
  onClose,
  onSuccess,
}) => {
  const { activeScope } = useAuth();
  const mutation = useCreateCashAccount();
  const idempotencyKeyRef = useRef<string>(generateIdempotencyKey());
  const lastPayloadRef = useRef<string | null>(null);

  const isRtUser = activeScope?.scopeLevel === 'rt';
  const rwId = activeScope?.rw?.id;
  const { data: rwData, isLoading: rwLoading } = useRwWithRts(open && rwId ? rwId : null);

  const [form, setForm] = useState<FormState>(INITIAL_FORM);
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (open) {
      setForm({
        ...INITIAL_FORM,
        scopeType: isRtUser ? 'rt' : 'rw',
        rt_id: isRtUser && activeScope?.rt?.id ? activeScope.rt.id : '',
      });
      setClientErrors({});
      mutation.reset();
      idempotencyKeyRef.current = generateIdempotencyKey();
      lastPayloadRef.current = null;
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, activeScope, isRtUser]);

  const rts = rwData?.rts ?? [];

  const handleOpeningBalanceChange = (val: string) => {
    setForm((prev) => ({ ...prev, opening_balance_input: val }));
    if (clientErrors.opening_balance) {
      setClientErrors((prev) => {
        const next = { ...prev };
        delete next.opening_balance;
        return next;
      });
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const errors: Record<string, string> = {};

    if (!form.name.trim()) {
      errors.name = 'Nama akun kas wajib diisi.';
    }

    if (form.scopeType === 'rt' && !form.rt_id) {
      errors.rt_id = 'Pilih unit RT pengelola akun ini.';
    }

    // Validate opening balance (integer safe, non-negative whole Rupiah)
    const balanceParsed = parseIntegerRupiah(form.opening_balance_input, { allowZero: true });
    if (!balanceParsed.valid) {
      errors.opening_balance = balanceParsed.error || 'Saldo awal tidak valid.';
    }

    if (Object.keys(errors).length > 0) {
      setClientErrors(errors);
      return;
    }

    setClientErrors({});

    const payload: CreateCashAccountPayload = {
      name: form.name.trim(),
      opening_balance: balanceParsed.value ?? 0,
      account_number: form.account_number.trim() || undefined,
      description: form.description.trim() || undefined,
    };

    // If RT-level account, attach rt_id. Never send organization_id or rw_id.
    if (form.scopeType === 'rt' && form.rt_id) {
      payload.rt_id = form.rt_id;
    }

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
      if (onSuccess && res.data?.id) {
        onSuccess(res.data.id);
      }
    } catch {
      // Handled by mutation error state
    }
  };

  const validationErrors = mutation.error?.errors;
  const generalError = mutation.error?.message;

  // Real-time formatted preview of opening balance
  const parsedPreview = parseIntegerRupiah(form.opening_balance_input, { allowZero: true });

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title="Buka Akun Kas Baru"
      description="Buat rekening pembukuan kas baru di tingkat RW atau RT dalam cakupan otorisasi Anda."
      maxWidth="max-w-md"
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        {generalError && (
          <Alert
            type="error"
            title="Gagal Membuat Akun"
            message={generalError}
            requestId={mutation.error?.requestId}
          />
        )}

        {/* Scope Selection */}
        {!isRtUser && (
          <div className="space-y-1.5">
            <label className="block text-xs font-medium text-slate-300">
              Tingkat Cakupan Kas <span className="text-rose-400">*</span>
            </label>
            <div className="grid grid-cols-2 gap-2">
              <button
                type="button"
                onClick={() => setForm((prev) => ({ ...prev, scopeType: 'rw', rt_id: '' }))}
                className={`px-3 py-2 text-xs font-semibold rounded-lg border transition-all ${
                  form.scopeType === 'rw'
                    ? 'bg-emerald-950/60 border-emerald-500 text-emerald-300 ring-1 ring-emerald-500'
                    : 'bg-slate-800/60 border-slate-700 text-slate-400 hover:text-slate-200'
                }`}
              >
                Tingkat RW (Kas Pusat)
              </button>
              <button
                type="button"
                onClick={() => setForm((prev) => ({ ...prev, scopeType: 'rt' }))}
                className={`px-3 py-2 text-xs font-semibold rounded-lg border transition-all ${
                  form.scopeType === 'rt'
                    ? 'bg-blue-950/60 border-blue-500 text-blue-300 ring-1 ring-blue-500'
                    : 'bg-slate-800/60 border-slate-700 text-slate-400 hover:text-slate-200'
                }`}
              >
                Tingkat RT (Kas Unit)
              </button>
            </div>
          </div>
        )}

        {/* RT Select when RT scope is selected */}
        {form.scopeType === 'rt' && (
          <div className="space-y-1.5">
            <label htmlFor="account-rt-select" className="block text-xs font-medium text-slate-300">
              Pilih Unit RT <span className="text-rose-400">*</span>
            </label>
            {isRtUser ? (
              <input
                type="text"
                disabled
                value={`RT ${activeScope?.rt?.number ? String(activeScope.rt.number).padStart(3, '0') : activeScope?.rt?.name || ''}`}
                className="w-full px-3 py-2 rounded-lg bg-slate-800/40 border border-slate-700 text-slate-300 text-xs cursor-not-allowed"
              />
            ) : (
              <select
                id="account-rt-select"
                value={form.rt_id}
                onChange={(e) => setForm((prev) => ({ ...prev, rt_id: e.target.value }))}
                disabled={rwLoading}
                className="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
              >
                <option value="">-- Pilih RT --</option>
                {rts.map((rt: RtUnit) => (
                  <option key={rt.id} value={rt.id}>
                    RT {String(rt.number).padStart(3, '0')} {rt.name ? `(${rt.name})` : ''}
                  </option>
                ))}
              </select>
            )}
            <FieldError message={clientErrors.rt_id || validationErrors?.rt_id?.[0]} />
          </div>
        )}

        {/* Account Name */}
        <div className="space-y-1.5">
          <label htmlFor="account-name" className="block text-xs font-medium text-slate-300">
            Nama Akun Kas <span className="text-rose-400">*</span>
          </label>
          <input
            id="account-name"
            type="text"
            placeholder="cth. Kas Operasional Bulanan, Kas Kegiatan Pemuda"
            value={form.name}
            onChange={(e) => setForm((prev) => ({ ...prev, name: e.target.value }))}
            className="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs placeholder-slate-500 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
          />
          <FieldError message={clientErrors.name || validationErrors?.name?.[0]} />
        </div>

        {/* Opening Balance (Non-negative whole Rupiah) */}
        <div className="space-y-1.5">
          <label htmlFor="account-opening-balance" className="block text-xs font-medium text-slate-300">
            Saldo Awal Kas (Initial Balance)
          </label>
          <div className="relative">
            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">
              Rp
            </span>
            <input
              id="account-opening-balance"
              type="text"
              placeholder="0"
              value={form.opening_balance_input}
              onChange={(e) => handleOpeningBalanceChange(e.target.value)}
              className="w-full pl-10 pr-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs font-mono placeholder-slate-500 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />
          </div>
          {parsedPreview.valid && parsedPreview.value !== undefined && (
            <div className="text-[11px] text-emerald-400 font-mono">
              Terbaca: {formatRupiah(parsedPreview.value)}
            </div>
          )}
          <p className="text-[11px] text-slate-500">
            Masukkan bilangan bulat Rupiah tanpa sen/koma. Saldo awal tidak dapat diubah setelah akun dibuat.
          </p>
          <FieldError message={clientErrors.opening_balance || validationErrors?.opening_balance?.[0]} />
        </div>

        {/* Account Number (Optional) */}
        <div className="space-y-1.5">
          <label htmlFor="account-number" className="block text-xs font-medium text-slate-300">
            Nomor Rekening / Kode Referensi (Opsional)
          </label>
          <input
            id="account-number"
            type="text"
            placeholder="cth. 123-456-7890 (BCA) atau KAS-01"
            value={form.account_number}
            onChange={(e) => setForm((prev) => ({ ...prev, account_number: e.target.value }))}
            className="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs font-mono placeholder-slate-500 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
          />
          <FieldError message={validationErrors?.account_number?.[0]} />
        </div>

        {/* Description (Optional) */}
        <div className="space-y-1.5">
          <label htmlFor="account-description" className="block text-xs font-medium text-slate-300">
            Deskripsi / Keterangan (Opsional)
          </label>
          <textarea
            id="account-description"
            rows={2}
            placeholder="Keterangan peruntukan akun kas ini..."
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
            Simpan Akun
          </Button>
        </div>
      </form>
    </Dialog>
  );
};
