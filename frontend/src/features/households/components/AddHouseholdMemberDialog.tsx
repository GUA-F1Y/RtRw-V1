import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import type { ApiError } from '../../../lib/api/errors.ts';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type { AddHouseholdMemberPayload, Household, HouseholdMemberRelationship } from '../../../types/household.ts';
import { useResidents } from '../../residents/api/residentQueries.ts';
import { useAddHouseholdMember } from '../api/householdQueries.ts';
import { RELATIONSHIP_LABEL } from './householdConstants.ts';

interface AddHouseholdMemberDialogProps {
  open: boolean;
  onClose: () => void;
  household: Household;
}

interface FormState {
  resident_id: string;
  relationship: HouseholdMemberRelationship | '';
  joined_at: string;
}

const ALL_RELATIONSHIPS: HouseholdMemberRelationship[] = [
  'KEPALA_KELUARGA',
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

export const AddHouseholdMemberDialog: React.FC<AddHouseholdMemberDialogProps> = ({
  open,
  onClose,
  household,
}) => {
  const mutation = useAddHouseholdMember();
  const idempotencyKeyRef = useRef<string>(generateIdempotencyKey());

  const todayStr = useMemo(() => new Date().toISOString().split('T')[0], []);

  const [form, setForm] = useState<FormState>({
    resident_id: '',
    relationship: 'ANAK',
    joined_at: todayStr,
  });
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  const hasActiveHead = Boolean(household.active_head ?? (household.status === 'ACTIVE' && household.head));

  useEffect(() => {
    if (open) {
      setForm({
        resident_id: '',
        relationship: hasActiveHead ? 'ANAK' : 'KEPALA_KELUARGA',
        joined_at: todayStr,
      });
      setClientErrors({});
      mutation.reset();
      idempotencyKeyRef.current = generateIdempotencyKey();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, household, todayStr, hasActiveHead]);

  // Load candidate residents from the same RT
  const { data: residentsData, isLoading: residentsLoading } = useResidents(
    household.rt_id ? { rt_id: household.rt_id, per_page: 200 } : undefined,
    { enabled: open && Boolean(household.rt_id) }
  );

  const eligibleCandidates = useMemo(() => {
    const list = residentsData?.data ?? [];
    return list.filter(
      (r) =>
        !r.current_household_id &&
        r.status !== 'PROSPECTIVE' &&
        r.status !== 'DECEASED' &&
        r.status !== 'MOVED'
    );
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

      if (!form.resident_id) {
        errors.resident_id = 'Pilih warga yang akan ditambahkan.';
      }

      if (!form.relationship) {
        errors.relationship = 'Hubungan keluarga wajib dipilih.';
      } else if (form.relationship === 'KEPALA_KELUARGA' && hasActiveHead) {
        errors.relationship = 'KK sudah memiliki Kepala Keluarga aktif. Gunakan menu Ganti Kepala KK.';
      }

      if (form.joined_at && !/^\d{4}-\d{2}-\d{2}$/.test(form.joined_at)) {
        errors.joined_at = 'Format tanggal harus YYYY-MM-DD.';
      }

      setClientErrors(errors);
      if (Object.keys(errors).length > 0) return;

      const payload: AddHouseholdMemberPayload = {
        resident_id: form.resident_id,
        relationship: form.relationship as HouseholdMemberRelationship,
      };

      if (form.joined_at) {
        payload.joined_at = form.joined_at;
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
    },
    [form, mutation, household.id, hasActiveHead, onClose]
  );

  const apiError = mutation.error as ApiError | null;
  const serverFieldErrors = apiError?.errors ?? {};

  return (
    <Dialog
      open={open}
      onClose={handleClose}
      title="Tambah Anggota Keluarga"
      description="Tambahkan warga yang berdomisili di RT ini ke dalam Kartu Keluarga."
      maxWidth="max-w-lg"
      data-testid="add-household-member-dialog"
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        {apiError && !Object.keys(serverFieldErrors).length && (
          <Alert
            type="error"
            title="Gagal menambahkan anggota keluarga"
            message={apiError.message ?? 'Periksa kembali data Anda.'}
            requestId={apiError.requestId}
          />
        )}

        {/* Resident Selection */}
        <div>
          <label htmlFor="member-resident-id" className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
            Pilih Warga <span className="text-rose-400">*</span>
          </label>
          {residentsLoading ? (
            <p className="text-xs text-slate-500 py-1">Memuat warga di RT ini...</p>
          ) : (
            <select
              id="member-resident-id"
              value={form.resident_id}
              onChange={(e) => {
                setForm((prev) => ({ ...prev, resident_id: e.target.value }));
                if (clientErrors.resident_id) {
                  setClientErrors((prev) => ({ ...prev, resident_id: '' }));
                }
              }}
              disabled={mutation.isPending}
              className="w-full bg-slate-800/80 border border-slate-700/60 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors disabled:opacity-50"
            >
              <option value="">-- Pilih Warga yang Belum Memiliki KK --</option>
              {eligibleCandidates.map((r) => (
                <option key={r.id} value={r.id}>
                  {r.name} ({r.age} thn, {r.gender === 'MALE' ? 'Laki-laki' : 'Perempuan'})
                </option>
              ))}
            </select>
          )}
          {eligibleCandidates.length === 0 && !residentsLoading && (
            <p className="text-xs text-amber-400 mt-1">
              Tidak ada warga di RT ini yang belum terdaftar dalam KK lain.
            </p>
          )}
          <FieldError message={clientErrors.resident_id ?? getErrorMessage(serverFieldErrors.resident_id)} />
        </div>

        {/* Relationship */}
        <div>
          <label htmlFor="member-relationship" className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
            Hubungan dalam Keluarga (SHDK) <span className="text-rose-400">*</span>
          </label>
          <select
            id="member-relationship"
            value={form.relationship}
            onChange={(e) => {
              setForm((prev) => ({
                ...prev,
                relationship: e.target.value as HouseholdMemberRelationship,
              }));
              if (clientErrors.relationship) {
                setClientErrors((prev) => ({ ...prev, relationship: '' }));
              }
            }}
            disabled={mutation.isPending}
            className="w-full bg-slate-800/80 border border-slate-700/60 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors disabled:opacity-50"
          >
            {ALL_RELATIONSHIPS.map((rel) => {
              const isDisabled = rel === 'KEPALA_KELUARGA' && hasActiveHead;
              return (
                <option key={rel} value={rel} disabled={isDisabled}>
                  {RELATIONSHIP_LABEL[rel] ?? rel}
                  {isDisabled ? ' (KK sudah memiliki Kepala aktif)' : ''}
                </option>
              );
            })}
          </select>
          <FieldError message={clientErrors.relationship ?? getErrorMessage(serverFieldErrors.relationship)} />
        </div>

        {/* Joined Date */}
        <div>
          <label htmlFor="member-joined-at" className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
            Tanggal Bergabung (Opsional)
          </label>
          <input
            id="member-joined-at"
            type="date"
            value={form.joined_at}
            onChange={(e) => {
              setForm((prev) => ({ ...prev, joined_at: e.target.value }));
              if (clientErrors.joined_at) {
                setClientErrors((prev) => ({ ...prev, joined_at: '' }));
              }
            }}
            disabled={mutation.isPending}
            className="w-full bg-slate-800/80 border border-slate-700/60 rounded-lg px-3.5 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors disabled:opacity-50"
          />
          <FieldError message={clientErrors.joined_at ?? getErrorMessage(serverFieldErrors.joined_at)} />
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
            disabled={mutation.isPending || eligibleCandidates.length === 0}
          >
            Tambahkan Anggota
          </Button>
        </div>
      </form>
    </Dialog>
  );
};
