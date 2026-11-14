import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import type { ApiError } from '../../../lib/api/errors.ts';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type { Resident, RelocateResidentPayload, ResidencyMovementType } from '../../../types/resident.ts';
import { useHouseholds } from '../../households/api/householdQueries.ts';
import { useRwWithRts } from '../../hierarchy/api/hierarchyQueries.ts';
import { useRelocateResident } from '../api/residentQueries.ts';

// ── Movement type descriptions ─────────────────────────────────────────────────

interface MovementTypeConfig {
  type: ResidencyMovementType;
  label: string;
  description: string;
  /** Whether the target RT selector is required */
  needsTargetRt: boolean;
  /** Whether a target household can optionally be selected */
  canSelectHousehold: boolean;
}

const MOVEMENT_TYPES: MovementTypeConfig[] = [
  {
    type: 'TRANSFER_INTERNAL',
    label: 'Pindah Internal',
    description: 'Warga pindah ke RT lain dalam wilayah RW yang sama atau berbeda.',
    needsTargetRt: true,
    canSelectHousehold: true,
  },
  {
    type: 'HOUSEHOLD_SPLIT',
    label: 'Pemekaran KK',
    description: 'Warga memisah dari KK dan membentuk KK baru di RT yang sama atau lain.',
    needsTargetRt: true,
    canSelectHousehold: true,
  },
  {
    type: 'MOVE_OUT_EXTERNAL',
    label: 'Pindah Keluar Wilayah',
    description: 'Warga pindah ke luar wilayah (luar RW/kelurahan). Status akan berubah menjadi Pindah.',
    needsTargetRt: false,
    canSelectHousehold: false,
  },
];

// ── Component ──────────────────────────────────────────────────────────────────

interface Props {
  open: boolean;
  onClose: () => void;
  resident: Resident;
}

interface FormState {
  movement_type: ResidencyMovementType | '';
  target_rt_id: string;
  target_household_id: string;
  effective_date: string;
  reference_letter_number: string;
  notes: string;
}

const EMPTY_FORM: FormState = {
  movement_type: '',
  target_rt_id: '',
  target_household_id: '',
  effective_date: '',
  reference_letter_number: '',
  notes: '',
};

/**
 * Dialog for relocating a resident.
 *
 * API: POST /api/v1/residents/{id}/relocate
 * Idempotency: required — key generated once per dialog open
 *
 * Payload:
 *   movement_type (required)
 *   target_rt_id  (required for TRANSFER_INTERNAL, HOUSEHOLD_SPLIT)
 *   target_household_id (optional, only when target_rt_id known)
 *   effective_date (optional)
 *   reference_letter_number (optional)
 *   notes (optional)
 *
 * target_rt candidates come from the resident's own RW hierarchy.
 * target_household candidates are filtered to the selected RT.
 */
export const RelocateResidentDialog: React.FC<Props> = ({ open, onClose, resident }) => {
  const mutation = useRelocateResident();

  // Stable idempotency key for this dialog session
  const idempotencyKeyRef = useRef<string>('');

  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [confirmStep, setConfirmStep] = useState(false);

  const selectedConfig = MOVEMENT_TYPES.find((m) => m.type === form.movement_type) ?? null;

  // Reset on open
  useEffect(() => {
    if (open) {
      setForm(EMPTY_FORM);
      setConfirmStep(false);
      mutation.reset();
      idempotencyKeyRef.current = generateIdempotencyKey();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  // Load RTs from the resident's own RW for target selection
  // Pass null when closed so the hook's built-in enabled guard disables the query
  const { data: rwData, isLoading: rwLoading } = useRwWithRts(
    open ? resident.rw_id : null
  );
  const rtOptions = useMemo(() => rwData?.rts ?? [], [rwData]);

  // Load households in the selected target RT
  const { data: householdsData, isLoading: householdsLoading } = useHouseholds(
    form.target_rt_id ? { rt_id: form.target_rt_id, per_page: 200 } : undefined,
    { enabled: !!form.target_rt_id && (selectedConfig?.canSelectHousehold ?? false) }
  );
  const householdOptions = useMemo(
    () => householdsData?.data ?? [],
    [householdsData]
  );

  const handleClose = useCallback(() => {
    if (mutation.isPending) return;
    onClose();
  }, [mutation.isPending, onClose]);

  const handleSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      if (!form.movement_type || mutation.isPending) return;

      if (!confirmStep) {
        setConfirmStep(true);
        return;
      }

      const payload: RelocateResidentPayload = {
        movement_type: form.movement_type as ResidencyMovementType,
      };
      if (form.target_rt_id) payload.target_rt_id = form.target_rt_id;
      if (form.target_household_id) payload.target_household_id = form.target_household_id;
      if (form.effective_date) payload.effective_date = form.effective_date;
      if (form.reference_letter_number.trim())
        payload.reference_letter_number = form.reference_letter_number.trim();
      if (form.notes.trim()) payload.notes = form.notes.trim();

      mutation.mutate(
        { id: resident.id, payload, idempotencyKey: idempotencyKeyRef.current },
        { onSuccess: () => onClose() }
      );
    },
    [form, confirmStep, mutation, resident.id, onClose]
  );

  const apiError = mutation.error as ApiError | null;
  const fieldErrors = apiError?.errors ?? {};

  const submitLabel = confirmStep ? 'Konfirmasi Pindah' : 'Lanjutkan';

  return (
    <Dialog
      open={open}
      onClose={handleClose}
      title="Relokasi Warga"
      description={`Warga: ${resident.name}`}
      data-testid="relocate-resident-dialog"
      maxWidth="max-w-xl"
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
            form="relocate-resident-form"
            variant={confirmStep ? 'danger' : 'primary'}
            isLoading={mutation.isPending}
            disabled={mutation.isPending || !form.movement_type}
          >
            {submitLabel}
          </Button>
        </div>
      }
    >
      <form id="relocate-resident-form" onSubmit={handleSubmit} noValidate className="space-y-4">
        {/* General error */}
        {apiError && !apiError.isValidationError() && (
          <Alert
            type="error"
            title="Gagal merelokasi warga"
            message={apiError.message}
            requestId={apiError.requestId}
          />
        )}

        {!confirmStep ? (
          <>
            {/* Movement type */}
            <div>
              <label className="block text-sm font-medium text-slate-300 mb-2">
                Jenis Perpindahan <span className="text-rose-400">*</span>
              </label>
              <div className="space-y-2">
                {MOVEMENT_TYPES.map((mt) => (
                  <label
                    key={mt.type}
                    className={`flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors ${
                      form.movement_type === mt.type
                        ? 'border-emerald-600/60 bg-emerald-950/30'
                        : 'border-slate-700/50 bg-slate-800/30 hover:border-slate-600/60'
                    }`}
                  >
                    <input
                      type="radio"
                      name="movement_type"
                      value={mt.type}
                      checked={form.movement_type === mt.type}
                      onChange={() =>
                        setForm((f) => ({
                          ...f,
                          movement_type: mt.type,
                          target_rt_id: '',
                          target_household_id: '',
                        }))
                      }
                      className="mt-0.5 accent-emerald-500"
                    />
                    <div>
                      <div className="text-sm font-medium text-slate-200">{mt.label}</div>
                      <div className="text-xs text-slate-400 mt-0.5">{mt.description}</div>
                    </div>
                  </label>
                ))}
              </div>
              <FieldError message={fieldErrors['movement_type']?.[0]} />
            </div>

            {/* Target RT — shown only for types that require it */}
            {selectedConfig?.needsTargetRt && (
              <div>
                <label
                  htmlFor="relocate-target-rt"
                  className="block text-sm font-medium text-slate-300 mb-1"
                >
                  RT Tujuan <span className="text-rose-400">*</span>
                </label>
                {rwLoading ? (
                  <div className="h-9 rounded-lg bg-slate-800/60 animate-pulse" />
                ) : (
                  <select
                    id="relocate-target-rt"
                    name="target_rt_id"
                    value={form.target_rt_id}
                    onChange={(e) =>
                      setForm((f) => ({
                        ...f,
                        target_rt_id: e.target.value,
                        target_household_id: '',
                      }))
                    }
                    required
                    aria-describedby={fieldErrors['target_rt_id'] ? 'relocate-rt-error' : undefined}
                    aria-invalid={!!fieldErrors['target_rt_id']}
                    className="w-full rounded-lg bg-slate-800/60 border border-slate-700/50 text-slate-100 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors"
                  >
                    <option value="">-- Pilih RT --</option>
                    {rtOptions.map((rt) => (
                      <option key={rt.id} value={rt.id}>
                        RT {rt.number} — {rt.name}
                      </option>
                    ))}
                  </select>
                )}
                <FieldError id="relocate-rt-error" message={fieldErrors['target_rt_id']?.[0]} />
              </div>
            )}

            {/* Target Household — optional, only when an RT is selected */}
            {selectedConfig?.canSelectHousehold && form.target_rt_id && (
              <div>
                <label
                  htmlFor="relocate-target-household"
                  className="block text-sm font-medium text-slate-300 mb-1"
                >
                  KK Tujuan{' '}
                  <span className="text-slate-500 font-normal">(opsional)</span>
                </label>
                {householdsLoading ? (
                  <div className="h-9 rounded-lg bg-slate-800/60 animate-pulse" />
                ) : (
                  <select
                    id="relocate-target-household"
                    name="target_household_id"
                    value={form.target_household_id}
                    onChange={(e) =>
                      setForm((f) => ({ ...f, target_household_id: e.target.value }))
                    }
                    aria-describedby={
                      fieldErrors['target_household_id'] ? 'relocate-hh-error' : undefined
                    }
                    aria-invalid={!!fieldErrors['target_household_id']}
                    className="w-full rounded-lg bg-slate-800/60 border border-slate-700/50 text-slate-100 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors"
                  >
                    <option value="">-- Tanpa KK spesifik --</option>
                    {householdOptions.map((hh) => (
                      <option key={hh.id} value={hh.id}>
                        {hh.address ?? hh.id}
                        {hh.kk_number && !hh.kk_number_masked
                          ? ` (${hh.kk_number})`
                          : ''}
                      </option>
                    ))}
                  </select>
                )}
                <FieldError
                  id="relocate-hh-error"
                  message={fieldErrors['target_household_id']?.[0]}
                />
              </div>
            )}

            {/* effective_date */}
            <div>
              <label
                htmlFor="relocate-effective-date"
                className="block text-sm font-medium text-slate-300 mb-1"
              >
                Tanggal Berlaku{' '}
                <span className="text-slate-500 font-normal">(opsional)</span>
              </label>
              <input
                id="relocate-effective-date"
                name="effective_date"
                type="date"
                value={form.effective_date}
                onChange={(e) => setForm((f) => ({ ...f, effective_date: e.target.value }))}
                aria-describedby={
                  fieldErrors['effective_date'] ? 'relocate-date-error' : undefined
                }
                aria-invalid={!!fieldErrors['effective_date']}
                className="w-full rounded-lg bg-slate-800/60 border border-slate-700/50 text-slate-100 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors"
              />
              <FieldError
                id="relocate-date-error"
                message={fieldErrors['effective_date']?.[0]}
              />
            </div>

            {/* reference_letter_number */}
            <div>
              <label
                htmlFor="relocate-ref-letter"
                className="block text-sm font-medium text-slate-300 mb-1"
              >
                Nomor Surat Referensi{' '}
                <span className="text-slate-500 font-normal">(opsional)</span>
              </label>
              <input
                id="relocate-ref-letter"
                name="reference_letter_number"
                type="text"
                maxLength={100}
                value={form.reference_letter_number}
                onChange={(e) =>
                  setForm((f) => ({ ...f, reference_letter_number: e.target.value }))
                }
                placeholder="Contoh: 474/RT01/X/2026"
                aria-describedby={
                  fieldErrors['reference_letter_number'] ? 'relocate-ref-error' : undefined
                }
                aria-invalid={!!fieldErrors['reference_letter_number']}
                className="w-full rounded-lg bg-slate-800/60 border border-slate-700/50 text-slate-100 placeholder-slate-500 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors"
              />
              <FieldError
                id="relocate-ref-error"
                message={fieldErrors['reference_letter_number']?.[0]}
              />
            </div>

            {/* notes */}
            <div>
              <label
                htmlFor="relocate-notes"
                className="block text-sm font-medium text-slate-300 mb-1"
              >
                Catatan{' '}
                <span className="text-slate-500 font-normal">(opsional)</span>
              </label>
              <textarea
                id="relocate-notes"
                name="notes"
                rows={2}
                maxLength={500}
                value={form.notes}
                onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))}
                placeholder="Catatan terkait perpindahan…"
                aria-describedby={fieldErrors['notes'] ? 'relocate-notes-error' : undefined}
                aria-invalid={!!fieldErrors['notes']}
                className="w-full rounded-lg bg-slate-800/60 border border-slate-700/50 text-slate-100 placeholder-slate-500 text-sm px-3 py-2 resize-none focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors"
              />
              <FieldError id="relocate-notes-error" message={fieldErrors['notes']?.[0]} />
            </div>
          </>
        ) : (
          /* Confirmation summary */
          <div className="space-y-4">
            <div className="rounded-xl border border-amber-500/30 bg-amber-950/20 p-4">
              <div className="flex items-start gap-3">
                <svg
                  className="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5"
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
                  <p className="text-sm font-semibold text-amber-300 mb-2">
                    Konfirmasi Relokasi Warga
                  </p>
                  <dl className="space-y-1.5 text-sm">
                    <div className="flex gap-2">
                      <dt className="text-slate-400 w-32 flex-shrink-0">Warga:</dt>
                      <dd className="text-slate-200 font-medium">{resident.name}</dd>
                    </div>
                    <div className="flex gap-2">
                      <dt className="text-slate-400 w-32 flex-shrink-0">Jenis:</dt>
                      <dd className="text-slate-200">
                        {selectedConfig?.label ?? form.movement_type}
                      </dd>
                    </div>
                    {form.target_rt_id && (
                      <div className="flex gap-2">
                        <dt className="text-slate-400 w-32 flex-shrink-0">RT Tujuan:</dt>
                        <dd className="text-slate-200">
                          {rtOptions.find((r) => r.id === form.target_rt_id)?.name ??
                            form.target_rt_id}
                        </dd>
                      </div>
                    )}
                    {form.effective_date && (
                      <div className="flex gap-2">
                        <dt className="text-slate-400 w-32 flex-shrink-0">Tgl. Berlaku:</dt>
                        <dd className="text-slate-200">{form.effective_date}</dd>
                      </div>
                    )}
                  </dl>
                </div>
              </div>
            </div>
            <p className="text-sm text-slate-400">
              Pastikan data sudah benar. Perpindahan tidak dapat dibatalkan melalui aplikasi ini.
            </p>
          </div>
        )}
      </form>
    </Dialog>
  );
};
