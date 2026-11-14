import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import type { ApiError } from '../../../lib/api/errors.ts';
import type {
  ChangeHouseholdHeadPayload,
  Household,
  HouseholdMemberRelationship,
} from '../../../types/household.ts';
import { useChangeHouseholdHead } from '../api/householdQueries.ts';
import { RELATIONSHIP_LABEL } from './householdConstants.ts';

interface ChangeHouseholdHeadDialogProps {
  open: boolean;
  onClose: () => void;
  household: Household;
}

interface FormState {
  new_head_resident_id: string;
  old_head_new_relationship: HouseholdMemberRelationship;
}

// Allowed relationships for demoted former head (KEPALA_KELUARGA is strictly forbidden)
const DEMOTED_HEAD_RELATIONSHIPS: HouseholdMemberRelationship[] = [
  'SUAMI',
  'ISTRI',
  'ANAK',
  'MENANTU',
  'CUCU',
  'ORANG_TUA',
  'MERTUA',
  'FAMILI_LAIN',
  'PEMBANTU',
  'LAINNYA',
];

function getErrorMessage(error: string | string[] | undefined): string | undefined {
  if (!error) return undefined;
  return Array.isArray(error) ? error[0] : error;
}

export const ChangeHouseholdHeadDialog: React.FC<ChangeHouseholdHeadDialogProps> = ({
  open,
  onClose,
  household,
}) => {
  const mutation = useChangeHouseholdHead();

  const currentHead = household.active_head ?? (household.status === 'ACTIVE' ? household.head : null);
  const isHeadVacant = household.status === 'HEAD_VACANT' || !currentHead;

  // Eligible replacement candidates: active members of this household who are not the current active head
  const eligibleCandidates = useMemo(() => {
    const members = household.members ?? [];
    return members.filter((m) => {
      if (!m.is_active) return false;
      if (currentHead && m.resident_id === currentHead.resident_id) return false;
      return true;
    });
  }, [household.members, currentHead]);

  const [form, setForm] = useState<FormState>({
    new_head_resident_id: '',
    old_head_new_relationship: 'FAMILI_LAIN',
  });
  const [confirmStep, setConfirmStep] = useState<boolean>(false);
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (open) {
      setForm({
        new_head_resident_id: eligibleCandidates[0]?.resident_id ?? '',
        old_head_new_relationship: 'FAMILI_LAIN',
      });
      setConfirmStep(false);
      setClientErrors({});
      mutation.reset();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, household, eligibleCandidates]);

  const handleClose = useCallback(() => {
    if (mutation.isPending) return;
    onClose();
  }, [mutation.isPending, onClose]);

  const selectedNewHeadMember = useMemo(() => {
    return eligibleCandidates.find((m) => m.resident_id === form.new_head_resident_id) ?? null;
  }, [eligibleCandidates, form.new_head_resident_id]);

  const validate = (): boolean => {
    const errors: Record<string, string> = {};
    if (!form.new_head_resident_id) {
      errors.new_head_resident_id = 'Pilih calon Kepala Keluarga baru.';
    }
    if (!isHeadVacant && !form.old_head_new_relationship) {
      errors.old_head_new_relationship = 'Pilih status hubungan baru untuk mantan Kepala Keluarga.';
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

    const payload: ChangeHouseholdHeadPayload = {
      new_head_resident_id: form.new_head_resident_id,
    };

    if (!isHeadVacant && form.old_head_new_relationship) {
      payload.old_head_new_relationship = form.old_head_new_relationship;
    }

    mutation.mutate(
      { id: household.id, payload },
      {
        onSuccess: () => {
          onClose();
        },
      }
    );
  }, [form, mutation, household.id, isHeadVacant, onClose]);

  const apiError = mutation.error as ApiError | null;
  const serverFieldErrors = apiError?.errors ?? {};

  const currentHeadName = currentHead?.resident?.name ?? 'Kepala Keluarga Saat Ini';
  const newHeadName = selectedNewHeadMember?.resident?.name ?? 'Calon Kepala Keluarga';

  return (
    <Dialog
      open={open}
      onClose={handleClose}
      title="Ganti Kepala Keluarga"
      description="Tetapkan anggota keluarga aktif sebagai Kepala Keluarga yang baru."
      maxWidth="max-w-lg"
      data-testid="change-household-head-dialog"
    >
      {apiError && !Object.keys(serverFieldErrors).length && (
        <Alert
          type="error"
          title="Gagal mengganti kepala keluarga"
          message={apiError.message ?? 'Periksa kembali data Anda.'}
          requestId={apiError.requestId}
        />
      )}

      {/* Current Head Status Banner */}
      <div className="rounded-xl border border-slate-700/60 bg-slate-800/40 p-4 space-y-1">
        <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">
          Kepala Keluarga Saat Ini
        </span>
        {isHeadVacant ? (
          <p className="text-sm font-medium text-amber-400">
            KK ini saat ini tidak memiliki Kepala Keluarga aktif (HEAD_VACANT).
          </p>
        ) : (
          <p className="text-sm font-semibold text-slate-100">{currentHeadName}</p>
        )}
      </div>

      {eligibleCandidates.length === 0 ? (
        <div className="py-4 space-y-4">
          <div className="p-3.5 rounded-xl border border-amber-800/50 bg-amber-950/30 text-amber-300 text-xs leading-relaxed">
            Tidak ada anggota keluarga aktif lain yang dapat ditunjuk sebagai Kepala Keluarga. Tambahkan anggota keluarga terlebih dahulu.
          </div>
          <div className="flex justify-end pt-2">
            <Button type="button" variant="secondary" onClick={handleClose}>
              Tutup
            </Button>
          </div>
        </div>
      ) : !confirmStep ? (
        <form onSubmit={handleProceedToConfirm} className="space-y-4">
          {/* Candidate Selection */}
          <div>
            <label htmlFor="select-new-head" className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
              Pilih Kepala Keluarga Baru <span className="text-rose-400">*</span>
            </label>
            <select
              id="select-new-head"
              value={form.new_head_resident_id}
              onChange={(e) => {
                setForm((prev) => ({ ...prev, new_head_resident_id: e.target.value }));
                if (clientErrors.new_head_resident_id) {
                  setClientErrors((prev) => ({ ...prev, new_head_resident_id: '' }));
                }
              }}
              disabled={mutation.isPending}
              className="w-full bg-slate-800/80 border border-slate-700/60 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors disabled:opacity-50"
            >
              <option value="">-- Pilih Calon Kepala Keluarga --</option>
              {eligibleCandidates.map((m) => {
                const name = m.resident?.name ?? `Warga ID: ${m.resident_id.slice(0, 8)}…`;
                const rel = RELATIONSHIP_LABEL[m.relationship] ?? m.relationship;
                return (
                  <option key={m.resident_id} value={m.resident_id}>
                    {name} ({rel})
                  </option>
                );
              })}
            </select>
            <FieldError message={clientErrors.new_head_resident_id ?? getErrorMessage(serverFieldErrors.new_head_resident_id)} />
          </div>

          {/* Former Head New Relationship (Only needed if there was a previous head) */}
          {!isHeadVacant && (
            <div>
              <label htmlFor="select-old-head-rel" className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
                Hubungan Baru untuk {currentHeadName} <span className="text-rose-400">*</span>
              </label>
              <select
                id="select-old-head-rel"
                value={form.old_head_new_relationship}
                onChange={(e) => {
                  setForm((prev) => ({
                    ...prev,
                    old_head_new_relationship: e.target.value as HouseholdMemberRelationship,
                  }));
                  if (clientErrors.old_head_new_relationship) {
                    setClientErrors((prev) => ({ ...prev, old_head_new_relationship: '' }));
                  }
                }}
                disabled={mutation.isPending}
                className="w-full bg-slate-800/80 border border-slate-700/60 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors disabled:opacity-50"
              >
                {DEMOTED_HEAD_RELATIONSHIPS.map((rel) => (
                  <option key={rel} value={rel}>
                    {RELATIONSHIP_LABEL[rel] ?? rel}
                  </option>
                ))}
              </select>
              <p className="text-xs text-slate-500 mt-1">
                Mantan Kepala Keluarga akan tetap menjadi anggota aktif KK dengan hubungan keluarga di atas.
              </p>
              <FieldError message={clientErrors.old_head_new_relationship ?? getErrorMessage(serverFieldErrors.old_head_new_relationship)} />
            </div>
          )}

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
              disabled={mutation.isPending || !form.new_head_resident_id}
            >
              Lanjutkan Konfirmasi
            </Button>
          </div>
        </form>
      ) : (
        <div className="space-y-4">
          <div className="rounded-xl border border-emerald-700/40 bg-emerald-950/20 p-4 text-sm text-emerald-200 space-y-2">
            <p className="font-semibold text-emerald-100">Konfirmasi Pergantian Kepala Keluarga</p>
            <p>
              • <strong>{newHeadName}</strong> akan diangkat menjadi <strong>Kepala Keluarga</strong> aktif.
            </p>
            {!isHeadVacant && (
              <p>
                • <strong>{currentHeadName}</strong> akan diubah perannya menjadi{' '}
                <strong>{RELATIONSHIP_LABEL[form.old_head_new_relationship] ?? form.old_head_new_relationship}</strong>.
              </p>
            )}
            {isHeadVacant && (
              <p>• Status Kartu Keluarga akan otomatis berubah menjadi <strong>AKTIF</strong>.</p>
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
              type="submit"
              variant="primary"
              onClick={handleConfirmSubmit}
              isLoading={mutation.isPending}
              disabled={mutation.isPending}
            >
              Ya, Tetapkan Kepala Keluarga
            </Button>
          </div>
        </div>
      )}
    </Dialog>
  );
};
