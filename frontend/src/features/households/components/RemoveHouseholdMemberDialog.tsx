import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import type { ApiError } from '../../../lib/api/errors.ts';
import type { Household, HouseholdMember } from '../../../types/household.ts';
import { useRemoveHouseholdMember } from '../api/householdQueries.ts';
import { RELATIONSHIP_LABEL } from './householdConstants.ts';

interface RemoveHouseholdMemberDialogProps {
  open: boolean;
  onClose: () => void;
  household: Household;
  member: HouseholdMember | null;
}

interface FormState {
  left_reason: string;
  left_at: string;
}

function getErrorMessage(error: string | string[] | undefined): string | undefined {
  if (!error) return undefined;
  return Array.isArray(error) ? error[0] : error;
}

export const RemoveHouseholdMemberDialog: React.FC<RemoveHouseholdMemberDialogProps> = ({
  open,
  onClose,
  household,
  member,
}) => {
  const mutation = useRemoveHouseholdMember();

  const todayStr = useMemo(() => new Date().toISOString().split('T')[0], []);

  const [form, setForm] = useState<FormState>({
    left_reason: '',
    left_at: todayStr,
  });
  const [confirmStep, setConfirmStep] = useState<boolean>(false);
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (open) {
      setForm({
        left_reason: '',
        left_at: todayStr,
      });
      setConfirmStep(false);
      setClientErrors({});
      mutation.reset();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, member, todayStr]);

  const handleClose = useCallback(() => {
    if (mutation.isPending) return;
    onClose();
  }, [mutation.isPending, onClose]);

  const handleConfirmSubmit = useCallback(() => {
    if (mutation.isPending || !member) return;

    mutation.mutate(
      {
        id: household.id,
        residentId: member.resident_id,
        payload: {
          left_reason: form.left_reason.trim() || undefined,
          left_at: form.left_at || undefined,
        },
      },
      {
        onSuccess: () => {
          onClose();
        },
      }
    );
  }, [form, mutation, household.id, member, onClose]);

  if (!member) return null;

  const isHead = member.relationship === 'KEPALA_KELUARGA' && member.is_active;
  const isLastMember = household.active_members_count <= 1;
  const memberName = member.resident?.name ?? `Warga ID: ${member.resident_id.slice(0, 8)}…`;
  const relationshipLabel = RELATIONSHIP_LABEL[member.relationship] ?? member.relationship;

  const handleProceedToConfirm = (e: React.FormEvent) => {
    e.preventDefault();
    const errors: Record<string, string> = {};
    if (form.left_reason && form.left_reason.trim().length > 255) {
      errors.left_reason = 'Alasan keluar maksimal 255 karakter.';
    }
    if (form.left_at && !/^\d{4}-\d{2}-\d{2}$/.test(form.left_at)) {
      errors.left_at = 'Format tanggal harus YYYY-MM-DD.';
    }
    setClientErrors(errors);
    if (Object.keys(errors).length === 0) {
      setConfirmStep(true);
    }
  };

  const apiError = mutation.error as ApiError | null;
  const serverFieldErrors = apiError?.errors ?? {};

  return (
    <Dialog
      open={open}
      onClose={handleClose}
      title="Keluarkan Anggota dari KK"
      description="Hapus keanggotaan aktif warga ini dari Kartu Keluarga."
      maxWidth="max-w-lg"
      data-testid="remove-household-member-dialog"
    >
      {apiError && !Object.keys(serverFieldErrors).length && (
        <Alert
          type="error"
          title="Gagal mengeluarkan anggota"
          message={apiError.message ?? 'Periksa kembali data Anda.'}
          requestId={apiError.requestId}
        />
      )}

      {/* Target Member Summary Card */}
      <div className="rounded-xl border border-slate-700/60 bg-slate-800/40 p-4 space-y-2">
        <div className="flex items-center justify-between">
          <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">
            Anggota Terpilih
          </span>
          {isHead && (
            <span className="text-xs bg-amber-950/60 text-amber-400 border border-amber-700/40 px-2 py-0.5 rounded-full font-medium">
              Kepala Keluarga
            </span>
          )}
        </div>
        <p className="text-base font-semibold text-white">{memberName}</p>
        <p className="text-xs text-slate-400">
          Hubungan dalam keluarga: <strong className="text-slate-200">{relationshipLabel}</strong>
        </p>
      </div>

      {/* Domain Invariant Warnings */}
      {isLastMember ? (
        <div className="p-3.5 rounded-xl border border-amber-800/50 bg-amber-950/30 text-amber-300 text-xs leading-relaxed">
          <strong>Perhatian:</strong> Ini adalah satu-satunya anggota aktif di KK ini. Jika dikeluarkan, status Kartu Keluarga akan otomatis berubah menjadi <strong>DIBUBARKAN (DISSOLVED)</strong>.
        </div>
      ) : isHead ? (
        <div className="p-3.5 rounded-xl border border-amber-800/50 bg-amber-950/30 text-amber-300 text-xs leading-relaxed">
          <strong>Perhatian:</strong> Warga ini adalah <strong>Kepala Keluarga</strong> aktif. Mengeluarkan Kepala Keluarga akan membuat status KK menjadi <strong>KEPALA KELUARGA KOSONG (HEAD_VACANT)</strong>. Anda dapat mengangkat Kepala Keluarga baru kemudian.
        </div>
      ) : null}

      {!confirmStep ? (
        <form onSubmit={handleProceedToConfirm} className="space-y-4">
          <div>
            <label htmlFor="remove-reason" className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
              Alasan Keluar (Opsional)
            </label>
            <input
              id="remove-reason"
              type="text"
              value={form.left_reason}
              onChange={(e) => {
                setForm((prev) => ({ ...prev, left_reason: e.target.value }));
                if (clientErrors.left_reason) {
                  setClientErrors((prev) => ({ ...prev, left_reason: '' }));
                }
              }}
              placeholder="Contoh: Menikah dan membuat KK sendiri, pindah domisili, dll."
              disabled={mutation.isPending}
              className="w-full bg-slate-800/80 border border-slate-700/60 rounded-lg px-3.5 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors disabled:opacity-50"
            />
            <FieldError message={clientErrors.left_reason ?? getErrorMessage(serverFieldErrors.left_reason)} />
          </div>

          <div>
            <label htmlFor="remove-left-at" className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
              Tanggal Efektif Keluar
            </label>
            <input
              id="remove-left-at"
              type="date"
              value={form.left_at}
              onChange={(e) => {
                setForm((prev) => ({ ...prev, left_at: e.target.value }));
                if (clientErrors.left_at) {
                  setClientErrors((prev) => ({ ...prev, left_at: '' }));
                }
              }}
              disabled={mutation.isPending}
              className="w-full bg-slate-800/80 border border-slate-700/60 rounded-lg px-3.5 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors disabled:opacity-50"
            />
            <FieldError message={clientErrors.left_at ?? getErrorMessage(serverFieldErrors.left_at)} />
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
          <div className="rounded-xl border border-rose-700/40 bg-rose-950/20 p-4 text-sm text-rose-300">
            <p className="font-semibold text-rose-200 mb-1">Konfirmasi Pengeluaran Anggota</p>
            <p>
              Apakah Anda yakin ingin mengeluarkan <strong>{memberName}</strong> dari Kartu Keluarga ini?
            </p>
            {form.left_reason && (
              <p className="mt-2 text-xs text-rose-400">
                Alasan: &ldquo;{form.left_reason}&rdquo;
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
              Ya, Keluarkan Warga
            </Button>
          </div>
        </div>
      )}
    </Dialog>
  );
};
