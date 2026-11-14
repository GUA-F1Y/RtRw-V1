import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import type { ApiError } from '../../../lib/api/errors.ts';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type { Household, MoveHouseholdPayload } from '../../../types/household.ts';
import type { RtUnit } from '../../../types/organization.ts';
import { useRwWithRts } from '../../hierarchy/api/hierarchyQueries.ts';
import { useMoveHousehold } from '../api/householdQueries.ts';

interface MoveHouseholdDialogProps {
  open: boolean;
  onClose: () => void;
  household: Household;
}

interface FormState {
  target_rt_id: string;
  new_address: string;
  notes: string;
}

function getErrorMessage(error: string | string[] | undefined): string | undefined {
  if (!error) return undefined;
  return Array.isArray(error) ? error[0] : error;
}

export const MoveHouseholdDialog: React.FC<MoveHouseholdDialogProps> = ({
  open,
  onClose,
  household,
}) => {
  const mutation = useMoveHousehold();
  const idempotencyKeyRef = useRef<string>(generateIdempotencyKey());

  const [form, setForm] = useState<FormState>({
    target_rt_id: '',
    new_address: household.address ?? '',
    notes: '',
  });
  const [confirmStep, setConfirmStep] = useState<boolean>(false);
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  // Reset on open
  useEffect(() => {
    if (open) {
      setForm({
        target_rt_id: '',
        new_address: household.address ?? '',
        notes: '',
      });
      setConfirmStep(false);
      setClientErrors({});
      mutation.reset();
      idempotencyKeyRef.current = generateIdempotencyKey();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, household]);

  // Load available RTs in the household's RW
  const { data: rwData, isLoading: rwLoading } = useRwWithRts(open ? household.rw_id : null);

  // Filter out the household's current RT
  const targetRtOptions: RtUnit[] = useMemo(() => {
    const list = rwData?.rts ?? [];
    return list.filter((rt) => rt.id !== household.rt_id);
  }, [rwData, household.rt_id]);

  const selectedTargetRt = useMemo(() => {
    return targetRtOptions.find((rt) => rt.id === form.target_rt_id) ?? null;
  }, [targetRtOptions, form.target_rt_id]);

  const handleClose = useCallback(() => {
    if (mutation.isPending) return;
    onClose();
  }, [mutation.isPending, onClose]);

  const validate = (): boolean => {
    const errors: Record<string, string> = {};

    if (!form.target_rt_id) {
      errors.target_rt_id = 'Pilih RT tujuan pemindahan.';
    } else if (form.target_rt_id === household.rt_id) {
      errors.target_rt_id = 'RT tujuan tidak boleh sama dengan RT asal.';
    }

    const cleanAddress = form.new_address.trim();
    if (!cleanAddress) {
      errors.new_address = 'Alamat baru wajib diisi.';
    } else if (cleanAddress.length < 3) {
      errors.new_address = 'Alamat baru minimal 3 karakter.';
    } else if (cleanAddress.length > 500) {
      errors.new_address = 'Alamat baru maksimal 500 karakter.';
    }

    if (form.notes && form.notes.trim().length > 500) {
      errors.notes = 'Catatan maksimal 500 karakter.';
    }

    setClientErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleProceedToConfirm = (e: React.FormEvent) => {
    e.preventDefault();
    if (!validate()) return;
    setConfirmStep(true);
  };

  const handleConfirmSubmit = useCallback(() => {
    if (mutation.isPending) return;

    const payload: MoveHouseholdPayload = {
      move_type: 'INTER_RT',
      target_rt_id: form.target_rt_id,
      new_address: form.new_address.trim(),
    };

    if (form.notes.trim()) {
      payload.notes = form.notes.trim();
    }

    mutation.mutate(
      {
        id: household.id,
        payload,
        idempotencyKey: idempotencyKeyRef.current,
      },
      {
        onSuccess: () => {
          onClose();
        },
      }
    );
  }, [form, mutation, household.id, onClose]);

  const apiError = mutation.error as ApiError | null;
  const serverFieldErrors = apiError?.errors ?? {};

  const currentRtNumber = String(household.rt_id).slice(-3);
  const targetRtName = selectedTargetRt?.name ?? (selectedTargetRt ? `RT ${String(selectedTargetRt.number).padStart(3, '0')}` : 'RT Tujuan');

  return (
    <Dialog
      open={open}
      onClose={handleClose}
      title="Pindah RT Kartu Keluarga"
      description="Pindahkan domisili Kartu Keluarga beserta seluruh anggota aktif ke unit RT lain dalam RW."
      maxWidth="max-w-lg"
      data-testid="move-household-dialog"
    >
      {apiError && !Object.keys(serverFieldErrors).length && (
        <Alert
          type="error"
          title="Gagal memindahkan Kartu Keluarga"
          message={apiError.message ?? 'Periksa kembali data Anda.'}
          requestId={apiError.requestId}
        />
      )}

      {/* Relocation impact explanation notice */}
      <div className="p-3.5 rounded-xl border border-sky-800/50 bg-sky-950/30 text-sky-300 text-xs leading-relaxed">
        Operasi ini memindahkan KK ke RT baru secara administratif. Seluruh <strong>{household.active_members_count} anggota keluarga aktif</strong> akan otomatis dipindahkan cakupan RT-nya mengikuti KK ini.
      </div>

      {!confirmStep ? (
        <form onSubmit={handleProceedToConfirm} className="space-y-4">
          {/* Current RT Info */}
          <div className="rounded-xl border border-slate-700/60 bg-slate-800/40 p-3.5 flex items-center justify-between text-xs">
            <span className="text-slate-400 font-semibold uppercase tracking-wider">RT Saat Ini:</span>
            <span className="text-slate-200 font-mono font-medium">RT {currentRtNumber}</span>
          </div>

          {/* Target RT Selection */}
          <div>
            <label htmlFor="move-target-rt" className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
              RT Tujuan <span className="text-rose-400">*</span>
            </label>
            {rwLoading ? (
              <p className="text-xs text-slate-500 py-1">Memuat daftar RT...</p>
            ) : (
              <select
                id="move-target-rt"
                value={form.target_rt_id}
                onChange={(e) => {
                  setForm((prev) => ({ ...prev, target_rt_id: e.target.value }));
                  if (clientErrors.target_rt_id) {
                    setClientErrors((prev) => ({ ...prev, target_rt_id: '' }));
                  }
                }}
                disabled={mutation.isPending}
                className="w-full bg-slate-800/80 border border-slate-700/60 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors disabled:opacity-50"
              >
                <option value="">-- Pilih RT Tujuan --</option>
                {targetRtOptions.map((rt) => (
                  <option key={rt.id} value={rt.id}>
                    {rt.name ?? `RT ${String(rt.number).padStart(3, '0')}`}
                  </option>
                ))}
              </select>
            )}
            <FieldError message={clientErrors.target_rt_id ?? getErrorMessage(serverFieldErrors.target_rt_id)} />
          </div>

          {/* New Address */}
          <div>
            <label htmlFor="move-new-address" className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
              Alamat Baru di RT Tujuan <span className="text-rose-400">*</span>
            </label>
            <textarea
              id="move-new-address"
              rows={2}
              value={form.new_address}
              onChange={(e) => {
                setForm((prev) => ({ ...prev, new_address: e.target.value }));
                if (clientErrors.new_address) {
                  setClientErrors((prev) => ({ ...prev, new_address: '' }));
                }
              }}
              placeholder="Alamat rumah baru di RT tujuan"
              disabled={mutation.isPending}
              className="w-full bg-slate-800/80 border border-slate-700/60 rounded-lg px-3.5 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors disabled:opacity-50"
            />
            <FieldError message={clientErrors.new_address ?? getErrorMessage(serverFieldErrors.new_address)} />
          </div>

          {/* Notes */}
          <div>
            <label htmlFor="move-notes" className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
              Catatan Perpindahan (Opsional)
            </label>
            <textarea
              id="move-notes"
              rows={2}
              value={form.notes}
              onChange={(e) => setForm((prev) => ({ ...prev, notes: e.target.value }))}
              placeholder="Contoh: Pindah rumah ke blok sebelah"
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
              disabled={mutation.isPending || !form.target_rt_id}
            >
              Lanjutkan Konfirmasi
            </Button>
          </div>
        </form>
      ) : (
        <div className="space-y-4">
          <div className="rounded-xl border border-emerald-700/40 bg-emerald-950/20 p-4 text-sm text-emerald-200 space-y-2">
            <p className="font-semibold text-emerald-100">Konfirmasi Pemindahan RT</p>
            <p>
              Apakah Anda yakin ingin memindahkan Kartu Keluarga ini ke <strong>{targetRtName}</strong>?
            </p>
            <p className="text-xs text-emerald-300">
              Alamat baru: &ldquo;{form.new_address.trim()}&rdquo;
            </p>
            <p className="text-xs text-emerald-400">
              {household.active_members_count} anggota aktif akan otomatis ikut berpindah ke wilayah {targetRtName}.
            </p>
          </div>

          <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-800/60">
            <Button
              type="button"
              variant="secondary"
              onClick={() => setConfirmStep(false)}
              disabled={mutation.isPending}
            >
              Kembali
            </Button>
            <Button
              type="submit"
              variant="primary"
              onClick={handleConfirmSubmit}
              isLoading={mutation.isPending}
              disabled={mutation.isPending}
            >
              Ya, Pindahkan KK
            </Button>
          </div>
        </div>
      )}
    </Dialog>
  );
};
