import React, { useCallback, useEffect, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import type { ApiError } from '../../../lib/api/errors.ts';
import type { DissolveHouseholdPayload, Household } from '../../../types/household.ts';
import { useDissolveHousehold } from '../api/householdQueries.ts';

interface DissolveHouseholdDialogProps {
  open: boolean;
  onClose: () => void;
  household: Household;
}

interface FormState {
  reason: string;
}

function getErrorMessage(error: string | string[] | undefined): string | undefined {
  if (!error) return undefined;
  return Array.isArray(error) ? error[0] : error;
}

export const DissolveHouseholdDialog: React.FC<DissolveHouseholdDialogProps> = ({
  open,
  onClose,
  household,
}) => {
  const mutation = useDissolveHousehold();

  const [form, setForm] = useState<FormState>({ reason: '' });
  const [confirmStep, setConfirmStep] = useState<boolean>(false);
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (open) {
      setForm({ reason: '' });
      setConfirmStep(false);
      setClientErrors({});
      mutation.reset();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, household]);

  const handleClose = useCallback(() => {
    if (mutation.isPending) return;
    onClose();
  }, [mutation.isPending, onClose]);

  const validate = (): boolean => {
    const errors: Record<string, string> = {};
    if (form.reason && form.reason.trim().length > 255) {
      errors.reason = 'Alasan pembubaran maksimal 255 karakter.';
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

    const payload: DissolveHouseholdPayload = {};
    if (form.reason.trim()) {
      payload.reason = form.reason.trim();
    }

    mutation.mutate(
      { id: household.id, payload },
      {
        onSuccess: () => {
          onClose();
        },
      }
    );
  }, [form, mutation, household.id, onClose]);

  const apiError = mutation.error as ApiError | null;
  const serverFieldErrors = apiError?.errors ?? {};

  return (
    <Dialog
      open={open}
      onClose={handleClose}
      title="Bubarkan Kartu Keluarga"
      description="Tindakan ini akan mengakhiri status aktif KK dan mencabut keanggotaan seluruh anggotanya."
      maxWidth="max-w-lg"
      data-testid="dissolve-household-dialog"
    >
      {apiError && !Object.keys(serverFieldErrors).length && (
        <Alert
          type="error"
          title="Gagal membubarkan Kartu Keluarga"
          message={apiError.message ?? 'Periksa kembali data Anda.'}
          requestId={apiError.requestId}
        />
      )}

      {/* Destructive Action Explanation Warning */}
      <div className="p-3.5 rounded-xl border border-rose-800/50 bg-rose-950/30 text-rose-300 text-xs leading-relaxed">
        <strong className="text-rose-200">Konsekuensi Pembubaran:</strong>
        <ul className="list-disc list-inside mt-1.5 space-y-1 text-xs text-rose-300">
          <li>Status KK akan berubah permanen menjadi <strong>DIBUBARKAN (DISSOLVED)</strong>.</li>
          <li>Seluruh <strong>{household.active_members_count} anggota aktif</strong> akan dinonaktifkan dari KK ini.</li>
          <li>Data warga <em>tidak akan dihapus</em> dari sistem dan catatan riwayat kependudukan tetap terjaga.</li>
        </ul>
      </div>

      {!confirmStep ? (
        <form onSubmit={handleProceedToConfirm} className="space-y-4">
          <div>
            <label htmlFor="dissolve-reason" className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
              Alasan Pembubaran (Opsional)
            </label>
            <input
              id="dissolve-reason"
              type="text"
              value={form.reason}
              onChange={(e) => {
                setForm({ reason: e.target.value });
                if (clientErrors.reason) {
                  setClientErrors({});
                }
              }}
              placeholder="Contoh: Seluruh keluarga pindah keluar kota, penggabungan KK, dll."
              disabled={mutation.isPending}
              className="w-full bg-slate-800/80 border border-slate-700/60 rounded-lg px-3.5 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-transparent transition-colors disabled:opacity-50"
            />
            <FieldError message={clientErrors.reason ?? getErrorMessage(serverFieldErrors.reason)} />
          </div>

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
              variant="danger"
              disabled={mutation.isPending}
            >
              Lanjutkan Konfirmasi
            </Button>
          </div>
        </form>
      ) : (
        <div className="space-y-4">
          <div className="rounded-xl border border-rose-700/50 bg-rose-950/30 p-4 text-sm text-rose-200 space-y-2">
            <p className="font-semibold text-rose-100">Apakah Anda benar-benar yakin?</p>
            <p>
              Kartu Keluarga <strong>{household.kk_number ?? `ID: ${household.id}`}</strong> akan dibubarkan. Tindakan ini tidak dapat dibatalkan secara langsung.
            </p>
            {form.reason && (
              <p className="text-xs text-rose-300">
                Alasan: &ldquo;{form.reason.trim()}&rdquo;
              </p>
            )}
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
              type="button"
              variant="danger"
              onClick={handleConfirmSubmit}
              isLoading={mutation.isPending}
              disabled={mutation.isPending}
            >
              Ya, Bubarkan Kartu Keluarga
            </Button>
          </div>
        </div>
      )}
    </Dialog>
  );
};
