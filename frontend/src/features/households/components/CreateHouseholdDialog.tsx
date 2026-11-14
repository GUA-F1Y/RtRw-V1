import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../../app/providers/useAuth.ts';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import type { ApiError } from '../../../lib/api/errors.ts';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type { CreateHouseholdPayload } from '../../../types/household.ts';
import { useRwWithRts } from '../../hierarchy/api/hierarchyQueries.ts';
import { useResidents } from '../../residents/api/residentQueries.ts';
import { useCreateHousehold } from '../api/householdQueries.ts';

interface CreateHouseholdDialogProps {
  open: boolean;
  onClose: () => void;
}

interface FormState {
  kk_number: string;
  address: string;
  rt_id: string;
  notes: string;
  initial_head_resident_id: string;
}

const EMPTY_FORM: FormState = {
  kk_number: '',
  address: '',
  rt_id: '',
  notes: '',
  initial_head_resident_id: '',
};

function getErrorMessage(error: string | string[] | undefined): string | undefined {
  if (!error) return undefined;
  return Array.isArray(error) ? error[0] : error;
}

export const CreateHouseholdDialog: React.FC<CreateHouseholdDialogProps> = ({
  open,
  onClose,
}) => {
  const navigate = useNavigate();
  const { activeScope } = useAuth();
  const mutation = useCreateHousehold();
  const idempotencyKeyRef = useRef<string>(generateIdempotencyKey());

  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  // Reset form and generate fresh idempotency key when dialog opens
  useEffect(() => {
    if (open) {
      setForm({
        ...EMPTY_FORM,
        rt_id: activeScope?.rt?.id ?? '',
      });
      setClientErrors({});
      mutation.reset();
      idempotencyKeyRef.current = generateIdempotencyKey();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, activeScope]);

  // Load RTs in the active RW
  const rwId = activeScope?.rw?.id;
  const { data: rwData, isLoading: rwLoading } = useRwWithRts(open && rwId ? rwId : null);

  const rtOptions = useMemo(() => {
    if (activeScope?.rt) {
      return [activeScope.rt];
    }
    return rwData?.rts ?? [];
  }, [activeScope, rwData]);

  // Pre-select first RT if available and none selected yet
  useEffect(() => {
    if (open && !form.rt_id && rtOptions.length > 0) {
      setForm((prev) => ({ ...prev, rt_id: rtOptions[0].id }));
    }
  }, [open, form.rt_id, rtOptions]);

  // Load eligible head candidates in selected RT
  // Must be active status in same RT, not currently belonging to an active household
  const { data: residentsData, isLoading: residentsLoading } = useResidents(
    form.rt_id ? { rt_id: form.rt_id, status: 'ACTIVE', per_page: 200 } : undefined,
    { enabled: open && Boolean(form.rt_id) }
  );

  const eligibleHeadCandidates = useMemo(() => {
    const list = residentsData?.data ?? [];
    return list.filter((r) => !r.current_household_id && r.status === 'ACTIVE');
  }, [residentsData]);

  const handleClose = useCallback(() => {
    if (mutation.isPending) return;
    onClose();
  }, [mutation.isPending, onClose]);

  const handleSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      if (mutation.isPending) return;

      const errors: Record<string, string> = {};
      const cleanKk = form.kk_number.trim();
      if (!cleanKk) {
        errors.kk_number = 'Nomor KK wajib diisi.';
      } else if (!/^[0-9]{16}$/.test(cleanKk)) {
        errors.kk_number = 'Nomor KK harus tepat 16 digit angka.';
      }

      const cleanAddress = form.address.trim();
      if (!cleanAddress) {
        errors.address = 'Alamat wajib diisi.';
      } else if (cleanAddress.length < 3) {
        errors.address = 'Alamat minimal 3 karakter.';
      } else if (cleanAddress.length > 500) {
        errors.address = 'Alamat maksimal 500 karakter.';
      }

      if (!form.rt_id) {
        errors.rt_id = 'RT wajib dipilih.';
      }

      if (form.notes && form.notes.trim().length > 500) {
        errors.notes = 'Catatan maksimal 500 karakter.';
      }

      setClientErrors(errors);
      if (Object.keys(errors).length > 0) return;

      const payload: CreateHouseholdPayload = {
        kk_number: form.kk_number.trim(),
        address: form.address.trim(),
        rt_id: form.rt_id,
      };

      if (form.notes.trim()) {
        payload.notes = form.notes.trim();
      }

      if (form.initial_head_resident_id) {
        payload.initial_head_resident_id = form.initial_head_resident_id;
      }

      mutation.mutate(
        {
          payload,
          idempotencyKey: idempotencyKeyRef.current,
        },
        {
          onSuccess: (response) => {
            onClose();
            if (response?.data?.id) {
              navigate(`/app/households/${response.data.id}`);
            }
          },
        }
      );
    },
    [form, mutation, navigate, onClose]
  );

  const apiError = mutation.error as ApiError | null;
  const serverFieldErrors = apiError?.errors ?? {};

  return (
    <Dialog
      open={open}
      onClose={handleClose}
      title="Tambah Kartu Keluarga Baru"
      description="Daftarkan Kartu Keluarga baru dalam wilayah RT yang dipilih."
      maxWidth="max-w-lg"
      data-testid="create-household-dialog"
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        {apiError && !Object.keys(serverFieldErrors).length && (
          <Alert
            type="error"
            title="Gagal membuat Kartu Keluarga"
            message={apiError.message ?? 'Periksa kembali data Anda.'}
            requestId={apiError.requestId}
          />
        )}

        {/* KK Number */}
        <div>
          <label htmlFor="create-kk-number" className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
            Nomor KK <span className="text-rose-400">*</span>
          </label>
          <input
            id="create-kk-number"
            type="text"
            inputMode="numeric"
            maxLength={16}
            value={form.kk_number}
            onChange={(e) => {
              const val = e.target.value.replace(/[^0-9]/g, '');
              setForm((prev) => ({ ...prev, kk_number: val }));
              if (clientErrors.kk_number) {
                setClientErrors((prev) => ({ ...prev, kk_number: '' }));
              }
            }}
            placeholder="3201010101010001 (16 digit angka)"
            disabled={mutation.isPending}
            className="w-full bg-slate-800/80 border border-slate-700/60 rounded-lg px-3.5 py-2.5 text-sm text-slate-100 font-mono placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors disabled:opacity-50"
          />
          <FieldError message={clientErrors.kk_number ?? getErrorMessage(serverFieldErrors.kk_number)} />
        </div>

        {/* RT Selection */}
        <div>
          <label htmlFor="create-rt-id" className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
            Wilayah RT <span className="text-rose-400">*</span>
          </label>
          {rwLoading ? (
            <p className="text-xs text-slate-500 py-1">Memuat data RT...</p>
          ) : (
            <select
              id="create-rt-id"
              value={form.rt_id}
              onChange={(e) => {
                setForm((prev) => ({
                  ...prev,
                  rt_id: e.target.value,
                  initial_head_resident_id: '', // Reset head candidate if RT changes
                }));
                if (clientErrors.rt_id) {
                  setClientErrors((prev) => ({ ...prev, rt_id: '' }));
                }
              }}
              disabled={mutation.isPending || Boolean(activeScope?.rt)}
              className="w-full bg-slate-800/80 border border-slate-700/60 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors disabled:opacity-50"
            >
              <option value="">Pilih RT</option>
              {rtOptions.map((rt) => (
                <option key={rt.id} value={rt.id}>
                  {rt.name ?? `RT ${String(rt.number).padStart(3, '0')}`}
                </option>
              ))}
            </select>
          )}
          <FieldError message={clientErrors.rt_id ?? getErrorMessage(serverFieldErrors.rt_id)} />
        </div>

        {/* Address */}
        <div>
          <label htmlFor="create-address" className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
            Alamat Tinggal <span className="text-rose-400">*</span>
          </label>
          <textarea
            id="create-address"
            rows={2}
            value={form.address}
            onChange={(e) => {
              setForm((prev) => ({ ...prev, address: e.target.value }));
              if (clientErrors.address) {
                setClientErrors((prev) => ({ ...prev, address: '' }));
              }
            }}
            placeholder="Nama jalan, nomor rumah, blok / gang"
            disabled={mutation.isPending}
            className="w-full bg-slate-800/80 border border-slate-700/60 rounded-lg px-3.5 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors disabled:opacity-50"
          />
          <FieldError message={clientErrors.address ?? getErrorMessage(serverFieldErrors.address)} />
        </div>

        {/* Initial Head Candidate */}
        <div>
          <label htmlFor="create-initial-head" className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
            Kepala Keluarga Awal (Opsional)
          </label>
          {residentsLoading ? (
            <p className="text-xs text-slate-500 py-1">Memuat warga yang memenuhi syarat...</p>
          ) : (
            <select
              id="create-initial-head"
              value={form.initial_head_resident_id}
              onChange={(e) =>
                setForm((prev) => ({ ...prev, initial_head_resident_id: e.target.value }))
              }
              disabled={mutation.isPending || !form.rt_id}
              className="w-full bg-slate-800/80 border border-slate-700/60 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors disabled:opacity-50"
            >
              <option value="">-- Tanpa Kepala Keluarga (Status: HEAD_VACANT) --</option>
              {eligibleHeadCandidates.map((resident) => (
                <option key={resident.id} value={resident.id}>
                  {resident.name} ({resident.age} thn, {resident.gender === 'MALE' ? 'L' : 'P'})
                </option>
              ))}
            </select>
          )}
          <p className="text-xs text-slate-500 mt-1">
            {form.initial_head_resident_id
              ? 'Warga terpilih akan ditetapkan sebagai Kepala Keluarga (Status KK: AKTIF).'
              : 'Jika dikosongkan, KK akan dibuat dengan status HEAD_VACANT dan kepala keluarga dapat ditentukan kemudian.'}
          </p>
          <FieldError message={getErrorMessage(serverFieldErrors.initial_head_resident_id)} />
        </div>

        {/* Notes */}
        <div>
          <label htmlFor="create-notes" className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
            Catatan Tambahan (Opsional)
          </label>
          <textarea
            id="create-notes"
            rows={2}
            value={form.notes}
            onChange={(e) => setForm((prev) => ({ ...prev, notes: e.target.value }))}
            placeholder="Catatan pendaftaran KK"
            disabled={mutation.isPending}
            className="w-full bg-slate-800/80 border border-slate-700/60 rounded-lg px-3.5 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors disabled:opacity-50"
          />
          <FieldError message={clientErrors.notes ?? getErrorMessage(serverFieldErrors.notes)} />
        </div>

        {/* Dialog Actions */}
        <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-800/60">
          <Button
            type="button"
            variant="secondary"
            onClick={handleClose}
            disabled={mutation.isPending}
          >
            Batal
          </Button>
          <Button
            type="submit"
            variant="primary"
            isLoading={mutation.isPending}
            disabled={mutation.isPending}
          >
            Simpan Kartu Keluarga
          </Button>
        </div>
      </form>
    </Dialog>
  );
};
