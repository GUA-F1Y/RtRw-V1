import React, { useCallback, useEffect, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import type { ApiError } from '../../../lib/api/errors.ts';
import type { RemoveResidentFromHouseholdPayload, Resident } from '../../../types/resident.ts';
import { useRemoveResidentFromHousehold } from '../api/residentQueries.ts';

interface Props {
  open: boolean;
  onClose: () => void;
  resident: Resident;
}

interface FormState {
  left_reason: string;
  left_at: string;
}

const EMPTY_FORM: FormState = {
  left_reason: '',
  left_at: '',
};

/**
 * Dialog for removing a resident from their current household.
 *
 * API: POST /api/v1/residents/{id}/remove-from-household
 * Payload: { left_reason?, left_at? }
 *
 * Shows current household context before confirmation.
 * Requires an explicit two-step confirmation.
 * Handles domain constraint rejections from backend (e.g., household head invariants).
 */
export const RemoveFromHouseholdDialog: React.FC<Props> = ({ open, onClose, resident }) => {
  const mutation = useRemoveResidentFromHousehold();

  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [confirmStep, setConfirmStep] = useState(false);

  const householdId = resident.current_household_id;

  useEffect(() => {
    if (open) {
      setForm(EMPTY_FORM);
      setConfirmStep(false);
      mutation.reset();
    }
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

      if (!confirmStep) {
        setConfirmStep(true);
        return;
      }

      const payload: RemoveResidentFromHouseholdPayload = {};
      if (form.left_reason.trim()) payload.left_reason = form.left_reason.trim();
      if (form.left_at) payload.left_at = form.left_at;

      mutation.mutate(
        { id: resident.id, payload },
        { onSuccess: () => onClose() }
      );
    },
    [form, confirmStep, mutation, resident.id, onClose]
  );

  const apiError = mutation.error as ApiError | null;
  const fieldErrors = apiError?.errors ?? {};

  return (
    <Dialog
      open={open}
      onClose={handleClose}
      title="Hapus dari Kartu Keluarga"
      description={`Mengeluarkan warga "${resident.name}" dari KK saat ini.`}
      data-testid="remove-from-household-dialog"
      footer={
        <div className="flex flex-col-reverse sm:flex-row justify-end gap-2">
          <Button
            type="button"
            variant="outline"
            onClick={() => {
              if (confirmStep) { setConfirmStep(false); return; }
              handleClose();
            }}
            disabled={mutation.isPending}
          >
            {confirmStep ? 'Kembali' : 'Batal'}
          </Button>
          <Button
            type="submit"
            form="remove-household-form"
            variant="danger"
            isLoading={mutation.isPending}
            disabled={mutation.isPending}
          >
            {confirmStep ? 'Ya, Keluarkan dari KK' : 'Lanjutkan'}
          </Button>
        </div>
      }
    >
      <form id="remove-household-form" onSubmit={handleSubmit} noValidate className="space-y-4">
        {/* General error */}
        {apiError && !apiError.isValidationError() && (
          <Alert
            type="error"
            title="Gagal mengeluarkan dari KK"
            message={apiError.message}
            requestId={apiError.requestId}
          />
        )}

        {!confirmStep ? (
          <>
            {/* Household context */}
            {householdId && (
              <div className="rounded-xl border border-slate-700/50 bg-slate-800/30 p-4">
                <p className="text-xs text-slate-500 font-semibold uppercase tracking-wider mb-1">
                  KK Saat Ini
                </p>
                <p className="text-sm font-mono text-slate-300 break-all">{householdId}</p>
              </div>
            )}

            {/* Domain constraint notice */}
            <Alert
              type="warning"
              message="Jika warga ini adalah Kepala Keluarga aktif, pengeluaran akan ditolak oleh sistem. Ganti kepala keluarga terlebih dahulu."
            />

            {/* left_reason */}
            <div>
              <label
                htmlFor="remove-hh-reason"
                className="block text-sm font-medium text-slate-300 mb-1"
              >
                Alasan Keluar{' '}
                <span className="text-slate-500 font-normal">(opsional)</span>
              </label>
              <textarea
                id="remove-hh-reason"
                name="left_reason"
                rows={3}
                maxLength={255}
                value={form.left_reason}
                onChange={(e) => setForm((f) => ({ ...f, left_reason: e.target.value }))}
                placeholder="Tuliskan alasan keluar dari KK…"
                aria-describedby={fieldErrors['left_reason'] ? 'remove-hh-reason-error' : undefined}
                aria-invalid={!!fieldErrors['left_reason']}
                className="w-full rounded-lg bg-slate-800/60 border border-slate-700/50 text-slate-100 placeholder-slate-500 text-sm px-3 py-2 resize-none focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors"
              />
              <FieldError id="remove-hh-reason-error" message={fieldErrors['left_reason']?.[0]} />
            </div>

            {/* left_at */}
            <div>
              <label
                htmlFor="remove-hh-date"
                className="block text-sm font-medium text-slate-300 mb-1"
              >
                Tanggal Keluar{' '}
                <span className="text-slate-500 font-normal">(opsional)</span>
              </label>
              <input
                id="remove-hh-date"
                name="left_at"
                type="date"
                value={form.left_at}
                onChange={(e) => setForm((f) => ({ ...f, left_at: e.target.value }))}
                aria-describedby={fieldErrors['left_at'] ? 'remove-hh-date-error' : undefined}
                aria-invalid={!!fieldErrors['left_at']}
                className="w-full rounded-lg bg-slate-800/60 border border-slate-700/50 text-slate-100 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors"
              />
              <FieldError id="remove-hh-date-error" message={fieldErrors['left_at']?.[0]} />
            </div>
          </>
        ) : (
          /* Confirmation step */
          <div className="rounded-xl border border-rose-500/30 bg-rose-950/30 p-4">
            <div className="flex items-start gap-3">
              <svg
                className="w-5 h-5 text-rose-400 flex-shrink-0 mt-0.5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                strokeWidth={2}
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
                />
              </svg>
              <div>
                <p className="text-sm font-semibold text-rose-300 mb-2">Konfirmasi Tindakan</p>
                <p className="text-sm text-rose-400">
                  Warga{' '}
                  <strong className="text-rose-300">{resident.name}</strong> akan dikeluarkan
                  dari Kartu Keluarga{' '}
                  <strong className="text-rose-300 font-mono break-all">{householdId}</strong>.
                </p>
                <p className="text-sm text-rose-500 mt-2">
                  Pastikan ini sesuai dengan data fisik yang ada.
                </p>
              </div>
            </div>
          </div>
        )}
      </form>
    </Dialog>
  );
};
