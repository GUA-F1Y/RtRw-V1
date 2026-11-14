import React, { useCallback, useEffect, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import type { ApiError } from '../../../lib/api/errors.ts';
import type { Resident, VerifyResidentPayload } from '../../../types/resident.ts';
import { useVerifyResident } from '../api/residentQueries.ts';

interface VerifyResidentDialogProps {
  open: boolean;
  onClose: () => void;
  resident: Resident;
}

const EMPTY: VerifyResidentPayload = {
  notes: '',
  reference_letter_number: '',
};

/**
 * Dialog for verifying a PROSPECTIVE resident (→ ACTIVE).
 *
 * API: POST /api/v1/residents/{id}/verify
 * Payload: { notes?, reference_letter_number?, attachment_file_id? }
 *   — attachment_file_id is omitted because there is no file-upload
 *     endpoint exposed to the frontend at this time.
 */
export const VerifyResidentDialog: React.FC<VerifyResidentDialogProps> = ({
  open,
  onClose,
  resident,
}) => {
  const mutation = useVerifyResident();

  const [form, setForm] = useState<VerifyResidentPayload>(EMPTY);

  // Reset form on open/close
  useEffect(() => {
    if (open) {
      setForm(EMPTY);
      mutation.reset();
    }
    // mutation.reset is stable — safe to include
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  const handleClose = useCallback(() => {
    if (mutation.isPending) return;
    onClose();
  }, [mutation.isPending, onClose]);

  const handleSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      if (mutation.isPending) return;

      const payload: VerifyResidentPayload = {};
      if (form.notes?.trim()) payload.notes = form.notes.trim();
      if (form.reference_letter_number?.trim())
        payload.reference_letter_number = form.reference_letter_number.trim();

      mutation.mutate(
        { id: resident.id, payload },
        {
          onSuccess: () => {
            onClose();
          },
        }
      );
    },
    [form, mutation, resident.id, onClose]
  );

  const apiError = mutation.error as ApiError | null;
  const fieldErrors = apiError?.errors ?? {};

  return (
    <Dialog
      open={open}
      onClose={handleClose}
      title="Verifikasi Warga"
      description={`Menyetujui status warga "${resident.name}" menjadi Aktif.`}
      data-testid="verify-resident-dialog"
      footer={
        <div className="flex flex-col-reverse sm:flex-row justify-end gap-2">
          <Button
            type="button"
            variant="outline"
            onClick={handleClose}
            disabled={mutation.isPending}
          >
            Batal
          </Button>
          <Button
            type="submit"
            form="verify-resident-form"
            variant="primary"
            isLoading={mutation.isPending}
            disabled={mutation.isPending}
          >
            Verifikasi
          </Button>
        </div>
      }
    >
      <form id="verify-resident-form" onSubmit={handleSubmit} noValidate className="space-y-4">
        {/* General / non-field error */}
        {apiError && !apiError.isValidationError() && (
          <Alert
            type="error"
            title="Gagal memverifikasi"
            message={apiError.message}
            requestId={apiError.requestId}
          />
        )}

        {/* notes */}
        <div>
          <label
            htmlFor="verify-notes"
            className="block text-sm font-medium text-slate-300 mb-1"
          >
            Catatan <span className="text-slate-500 font-normal">(opsional)</span>
          </label>
          <textarea
            id="verify-notes"
            name="notes"
            rows={3}
            maxLength={500}
            value={form.notes ?? ''}
            onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))}
            placeholder="Catatan terkait verifikasi…"
            aria-describedby={fieldErrors['notes'] ? 'verify-notes-error' : undefined}
            aria-invalid={!!fieldErrors['notes']}
            className="w-full rounded-lg bg-slate-800/60 border border-slate-700/50 text-slate-100 placeholder-slate-500 text-sm px-3 py-2 resize-none focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors"
          />
          <FieldError id="verify-notes-error" message={fieldErrors['notes']?.[0]} />
        </div>

        {/* reference_letter_number */}
        <div>
          <label
            htmlFor="verify-ref-letter"
            className="block text-sm font-medium text-slate-300 mb-1"
          >
            Nomor Surat Referensi <span className="text-slate-500 font-normal">(opsional)</span>
          </label>
          <input
            id="verify-ref-letter"
            name="reference_letter_number"
            type="text"
            maxLength={100}
            value={form.reference_letter_number ?? ''}
            onChange={(e) =>
              setForm((f) => ({ ...f, reference_letter_number: e.target.value }))
            }
            placeholder="Contoh: 474/RT01/X/2026"
            aria-describedby={
              fieldErrors['reference_letter_number'] ? 'verify-ref-error' : undefined
            }
            aria-invalid={!!fieldErrors['reference_letter_number']}
            className="w-full rounded-lg bg-slate-800/60 border border-slate-700/50 text-slate-100 placeholder-slate-500 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors"
          />
          <FieldError
            id="verify-ref-error"
            message={fieldErrors['reference_letter_number']?.[0]}
          />
        </div>

        {/* attachment_file_id — not rendered: no file-upload endpoint exists */}
      </form>
    </Dialog>
  );
};
