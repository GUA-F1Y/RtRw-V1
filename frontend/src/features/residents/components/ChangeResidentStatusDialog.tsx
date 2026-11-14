import React, { useCallback, useEffect, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import type { ApiError } from '../../../lib/api/errors.ts';
import type {
  ChangeResidentStatusPayload,
  Resident,
  ResidentStatus,
  ResidentStatusIntent,
} from '../../../types/resident.ts';
import { useChangeResidentStatus } from '../api/residentQueries.ts';

// ── Lifecycle rules ────────────────────────────────────────────────────────────
// These reflect the backend domain state machine. We show only valid intent
// options per the current status, but backend validation is authoritative.

interface IntentConfig {
  intent: ResidentStatusIntent;
  label: string;
  description: string;
  confirmWarning?: string;
  buttonVariant: 'primary' | 'danger' | 'outline';
}

const INTENT_BY_STATUS: Record<ResidentStatus, IntentConfig[]> = {
  PROSPECTIVE: [],
  ACTIVE: [
    {
      intent: 'DEACTIVATE',
      label: 'Nonaktifkan',
      description: 'Mengubah status warga menjadi Tidak Aktif.',
      buttonVariant: 'danger',
    },
    {
      intent: 'MARK_DECEASED',
      label: 'Tandai Meninggal Dunia',
      description: 'Mengubah status warga menjadi Meninggal. Tindakan ini tidak dapat dibalik.',
      confirmWarning:
        'Apakah Anda yakin ingin menandai warga ini sebagai Meninggal Dunia? Tindakan ini bersifat permanen.',
      buttonVariant: 'danger',
    },
  ],
  INACTIVE: [
    {
      intent: 'REACTIVATE',
      label: 'Aktifkan Kembali',
      description: 'Mengubah status warga kembali menjadi Aktif.',
      buttonVariant: 'primary',
    },
    {
      intent: 'MARK_DECEASED',
      label: 'Tandai Meninggal Dunia',
      description: 'Mengubah status warga menjadi Meninggal. Tindakan ini tidak dapat dibalik.',
      confirmWarning:
        'Apakah Anda yakin ingin menandai warga ini sebagai Meninggal Dunia? Tindakan ini bersifat permanen.',
      buttonVariant: 'danger',
    },
  ],
  MOVED: [],
  DECEASED: [],
};

// ── Component ──────────────────────────────────────────────────────────────────

interface Props {
  open: boolean;
  onClose: () => void;
  resident: Resident;
}

interface FormState {
  intent: ResidentStatusIntent | '';
  reason: string;
  reference_letter_number: string;
  effective_at: string;
}

const EMPTY_FORM: FormState = {
  intent: '',
  reason: '',
  reference_letter_number: '',
  effective_at: '',
};

/**
 * Dialog for changing a resident's civil status.
 *
 * API: POST /api/v1/residents/{id}/status
 * Payload: { intent, reason?, reference_letter_number?, effective_at? }
 *
 * Only presents intents that are valid for the resident's current status.
 * Never exposes a free-form status field.
 * Requires explicit confirmation for MARK_DECEASED.
 */
export const ChangeResidentStatusDialog: React.FC<Props> = ({ open, onClose, resident }) => {
  const mutation = useChangeResidentStatus();

  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [confirmStep, setConfirmStep] = useState(false);

  const availableIntents = INTENT_BY_STATUS[resident.status] ?? [];

  const selectedIntentConfig = availableIntents.find((i) => i.intent === form.intent) ?? null;

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

  const handleProceed = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      if (!form.intent || mutation.isPending) return;

      // Consequential actions require a confirm step
      if (selectedIntentConfig?.confirmWarning && !confirmStep) {
        setConfirmStep(true);
        return;
      }

      const payload: ChangeResidentStatusPayload = {
        intent: form.intent as ResidentStatusIntent,
      };
      if (form.reason.trim()) payload.reason = form.reason.trim();
      if (form.reference_letter_number.trim())
        payload.reference_letter_number = form.reference_letter_number.trim();
      if (form.effective_at) payload.effective_at = form.effective_at;

      mutation.mutate(
        { id: resident.id, payload },
        { onSuccess: () => onClose() }
      );
    },
    [form, confirmStep, mutation, resident.id, selectedIntentConfig, onClose]
  );

  const apiError = mutation.error as ApiError | null;
  const fieldErrors = apiError?.errors ?? {};

  const isUnavailable = availableIntents.length === 0;

  const submitLabel = (() => {
    if (!form.intent) return 'Pilih Tindakan';
    if (selectedIntentConfig?.confirmWarning && !confirmStep) return 'Lanjutkan';
    return selectedIntentConfig?.label ?? 'Simpan';
  })();

  return (
    <Dialog
      open={open}
      onClose={handleClose}
      title="Ubah Status Warga"
      description={`Warga: ${resident.name}`}
      data-testid="change-status-dialog"
      maxWidth="max-w-lg"
      footer={
        <div className="flex flex-col-reverse sm:flex-row justify-end gap-2">
          <Button
            type="button"
            variant="outline"
            onClick={handleClose}
            disabled={mutation.isPending}
          >
            {confirmStep ? 'Kembali' : 'Batal'}
          </Button>
          {!isUnavailable && (
            <Button
              type="submit"
              form="change-status-form"
              variant={
                selectedIntentConfig?.intent === 'MARK_DECEASED' ? 'danger' : 'primary'
              }
              isLoading={mutation.isPending}
              disabled={mutation.isPending || !form.intent}
            >
              {submitLabel}
            </Button>
          )}
        </div>
      }
    >
      <form id="change-status-form" onSubmit={handleProceed} noValidate className="space-y-4">
        {/* Unavailable state */}
        {isUnavailable && (
          <Alert
            type="info"
            message={`Status "${resident.status}" tidak memiliki tindakan yang tersedia. Perubahan status untuk warga Pindah atau Meninggal tidak dapat dilakukan.`}
          />
        )}

        {/* General error */}
        {apiError && !apiError.isValidationError() && (
          <Alert
            type="error"
            title="Gagal mengubah status"
            message={apiError.message}
            requestId={apiError.requestId}
          />
        )}

        {!isUnavailable && !confirmStep && (
          <>
            {/* Intent selector */}
            <div>
              <label className="block text-sm font-medium text-slate-300 mb-2">
                Pilih Tindakan <span className="text-rose-400">*</span>
              </label>
              <div className="space-y-2">
                {availableIntents.map((ic) => (
                  <label
                    key={ic.intent}
                    className={`flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors ${
                      form.intent === ic.intent
                        ? 'border-emerald-600/60 bg-emerald-950/30'
                        : 'border-slate-700/50 bg-slate-800/30 hover:border-slate-600/60'
                    }`}
                  >
                    <input
                      type="radio"
                      name="intent"
                      value={ic.intent}
                      checked={form.intent === ic.intent}
                      onChange={() => setForm((f) => ({ ...f, intent: ic.intent }))}
                      className="mt-0.5 accent-emerald-500"
                      aria-describedby={`intent-desc-${ic.intent}`}
                    />
                    <div>
                      <div className="text-sm font-medium text-slate-200">{ic.label}</div>
                      <div
                        id={`intent-desc-${ic.intent}`}
                        className="text-xs text-slate-400 mt-0.5"
                      >
                        {ic.description}
                      </div>
                    </div>
                  </label>
                ))}
              </div>
              <FieldError message={fieldErrors['intent']?.[0]} />
            </div>

            {/* reason */}
            <div>
              <label
                htmlFor="status-reason"
                className="block text-sm font-medium text-slate-300 mb-1"
              >
                Alasan <span className="text-slate-500 font-normal">(opsional)</span>
              </label>
              <textarea
                id="status-reason"
                name="reason"
                rows={3}
                maxLength={500}
                value={form.reason}
                onChange={(e) => setForm((f) => ({ ...f, reason: e.target.value }))}
                placeholder="Tuliskan alasan perubahan status…"
                aria-describedby={fieldErrors['reason'] ? 'status-reason-error' : undefined}
                aria-invalid={!!fieldErrors['reason']}
                className="w-full rounded-lg bg-slate-800/60 border border-slate-700/50 text-slate-100 placeholder-slate-500 text-sm px-3 py-2 resize-none focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors"
              />
              <FieldError id="status-reason-error" message={fieldErrors['reason']?.[0]} />
            </div>

            {/* reference_letter_number */}
            <div>
              <label
                htmlFor="status-ref-letter"
                className="block text-sm font-medium text-slate-300 mb-1"
              >
                Nomor Surat Referensi{' '}
                <span className="text-slate-500 font-normal">(opsional)</span>
              </label>
              <input
                id="status-ref-letter"
                name="reference_letter_number"
                type="text"
                maxLength={100}
                value={form.reference_letter_number}
                onChange={(e) =>
                  setForm((f) => ({ ...f, reference_letter_number: e.target.value }))
                }
                placeholder="Contoh: 474/RT01/X/2026"
                aria-describedby={
                  fieldErrors['reference_letter_number'] ? 'status-ref-error' : undefined
                }
                aria-invalid={!!fieldErrors['reference_letter_number']}
                className="w-full rounded-lg bg-slate-800/60 border border-slate-700/50 text-slate-100 placeholder-slate-500 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors"
              />
              <FieldError
                id="status-ref-error"
                message={fieldErrors['reference_letter_number']?.[0]}
              />
            </div>

            {/* effective_at */}
            <div>
              <label
                htmlFor="status-effective-at"
                className="block text-sm font-medium text-slate-300 mb-1"
              >
                Tanggal Berlaku{' '}
                <span className="text-slate-500 font-normal">(opsional)</span>
              </label>
              <input
                id="status-effective-at"
                name="effective_at"
                type="date"
                value={form.effective_at}
                onChange={(e) => setForm((f) => ({ ...f, effective_at: e.target.value }))}
                aria-describedby={
                  fieldErrors['effective_at'] ? 'status-effective-error' : undefined
                }
                aria-invalid={!!fieldErrors['effective_at']}
                className="w-full rounded-lg bg-slate-800/60 border border-slate-700/50 text-slate-100 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors"
              />
              <FieldError
                id="status-effective-error"
                message={fieldErrors['effective_at']?.[0]}
              />
            </div>
          </>
        )}

        {/* Confirmation step for consequential actions */}
        {!isUnavailable && confirmStep && selectedIntentConfig?.confirmWarning && (
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
                <p className="text-sm font-semibold text-rose-300 mb-1">Konfirmasi Tindakan</p>
                <p className="text-sm text-rose-400">{selectedIntentConfig.confirmWarning}</p>
                <p className="text-sm text-rose-500 mt-2">
                  Warga: <strong className="text-rose-300">{resident.name}</strong>
                </p>
              </div>
            </div>
          </div>
        )}
      </form>
    </Dialog>
  );
};
