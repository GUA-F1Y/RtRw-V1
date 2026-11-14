import React, { useCallback, useEffect, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import type { ApiError } from '../../../lib/api/errors.ts';
import type { Household, UpdateHouseholdPayload } from '../../../types/household.ts';
import { useUpdateHousehold } from '../api/householdQueries.ts';

interface EditHouseholdDialogProps {
  open: boolean;
  onClose: () => void;
  household: Household;
}

interface FormState {
  address: string;
  notes: string;
}

function getErrorMessage(error: string | string[] | undefined): string | undefined {
  if (!error) return undefined;
  return Array.isArray(error) ? error[0] : error;
}

export const EditHouseholdDialog: React.FC<EditHouseholdDialogProps> = ({
  open,
  onClose,
  household,
}) => {
  const mutation = useUpdateHousehold();

  const [form, setForm] = useState<FormState>({
    address: household.address ?? '',
    notes: household.notes ?? '',
  });
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (open) {
      setForm({
        address: household.address ?? '',
        notes: household.notes ?? '',
      });
      setClientErrors({});
      mutation.reset();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, household]);

  const handleClose = useCallback(() => {
    if (mutation.isPending) return;
    onClose();
  }, [mutation.isPending, onClose]);

  const handleSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      if (mutation.isPending) return;

      const errors: Record<string, string> = {};
      const cleanAddress = form.address.trim();
      if (!cleanAddress) {
        errors.address = 'Alamat wajib diisi.';
      } else if (cleanAddress.length < 3) {
        errors.address = 'Alamat minimal 3 karakter.';
      } else if (cleanAddress.length > 500) {
        errors.address = 'Alamat maksimal 500 karakter.';
      }

      if (form.notes && form.notes.trim().length > 500) {
        errors.notes = 'Catatan maksimal 500 karakter.';
      }

      setClientErrors(errors);
      if (Object.keys(errors).length > 0) return;

      const payload: UpdateHouseholdPayload = {
        address: form.address.trim(),
        notes: form.notes.trim() || undefined,
      };

      mutation.mutate(
        { id: household.id, payload },
        {
          onSuccess: () => {
            onClose();
          },
        }
      );
    },
    [form, mutation, household.id, onClose]
  );

  const apiError = mutation.error as ApiError | null;
  const serverFieldErrors = apiError?.errors ?? {};

  return (
    <Dialog
      open={open}
      onClose={handleClose}
      title="Ubah Data Kartu Keluarga"
      description="Perbarui alamat domisili atau catatan KK. Nomor KK bersifat tetap dan tidak dapat diubah di sini."
      maxWidth="max-w-lg"
      data-testid="edit-household-dialog"
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        {apiError && !Object.keys(serverFieldErrors).length && (
          <Alert
            type="error"
            title="Gagal memperbarui Kartu Keluarga"
            message={apiError.message ?? 'Periksa kembali data Anda.'}
            requestId={apiError.requestId}
          />
        )}

        {/* KK Number (Read-only display) */}
        <div>
          <label className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
            Nomor KK (Tetap / Terkunci)
          </label>
          <div className="w-full bg-slate-900 border border-slate-800 rounded-lg px-3.5 py-2.5 text-sm text-slate-400 font-mono select-none">
            {household.kk_number ?? 'Nomor KK tidak tersedia'}
          </div>
          <p className="text-xs text-slate-500 mt-1">
            Nomor Kartu Keluarga tidak dapat diubah secara langsung demi menjaga integritas data sipil.
          </p>
        </div>

        {/* Address */}
        <div>
          <label htmlFor="edit-address" className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
            Alamat Domisili <span className="text-rose-400">*</span>
          </label>
          <textarea
            id="edit-address"
            rows={3}
            value={form.address}
            onChange={(e) => {
              setForm((prev) => ({ ...prev, address: e.target.value }));
              if (clientErrors.address) {
                setClientErrors((prev) => ({ ...prev, address: '' }));
              }
            }}
            placeholder="Alamat lengkap tempat tinggal"
            disabled={mutation.isPending}
            className="w-full bg-slate-800/80 border border-slate-700/60 rounded-lg px-3.5 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors disabled:opacity-50"
          />
          <FieldError message={clientErrors.address ?? getErrorMessage(serverFieldErrors.address)} />
        </div>

        {/* Notes */}
        <div>
          <label htmlFor="edit-notes" className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
            Catatan (Opsional)
          </label>
          <textarea
            id="edit-notes"
            rows={2}
            value={form.notes}
            onChange={(e) => setForm((prev) => ({ ...prev, notes: e.target.value }))}
            placeholder="Catatan tambahan mengenai KK"
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
            Simpan Perubahan
          </Button>
        </div>
      </form>
    </Dialog>
  );
};
