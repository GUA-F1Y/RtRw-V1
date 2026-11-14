import React, { useState } from 'react';
import type { Resident } from '../../../types/resident.ts';
import { ChangeResidentStatusDialog } from './ChangeResidentStatusDialog.tsx';
import { RelocateResidentDialog } from './RelocateResidentDialog.tsx';
import { RemoveFromHouseholdDialog } from './RemoveFromHouseholdDialog.tsx';
import { VerifyResidentDialog } from './VerifyResidentDialog.tsx';

type ActionKey = 'verify' | 'status' | 'relocate' | 'removeHousehold' | null;

interface Props {
  resident: Resident;
}

/**
 * Determines which operational actions are available for a given resident state.
 * This is a UI affordance only — authorization is enforced by the backend.
 */
function getAvailableActions(resident: Resident): {
  canVerify: boolean;
  canChangeStatus: boolean;
  canRelocate: boolean;
  canRemoveFromHousehold: boolean;
} {
  const { status } = resident;
  return {
    // Only PROSPECTIVE residents can be verified
    canVerify: status === 'PROSPECTIVE',
    // ACTIVE and INACTIVE residents have available status-change actions
    canChangeStatus: status === 'ACTIVE' || status === 'INACTIVE',
    // ACTIVE residents can be relocated
    canRelocate: status === 'ACTIVE',
    // ACTIVE residents with a current household membership can be removed
    canRemoveFromHousehold: status === 'ACTIVE' && Boolean(resident.current_household_id),
  };
}

/**
 * ResidentActionsPanel — operational action bar on the Resident Detail page.
 *
 * Groups available actions logically, opens the appropriate dialog,
 * and disables/hides unavailable actions based on resident state.
 *
 * Authorization note: visibility checks here are UX-only.
 * The backend enforces all policy rules regardless.
 */
export const ResidentActionsPanel: React.FC<Props> = ({ resident }) => {
  const [activeAction, setActiveAction] = useState<ActionKey>(null);

  const { canVerify, canChangeStatus, canRelocate, canRemoveFromHousehold } =
    getAvailableActions(resident);

  const hasAnyAction = canVerify || canChangeStatus || canRelocate || canRemoveFromHousehold;

  if (!hasAnyAction) {
    return (
      <section aria-labelledby="actions-heading" className="space-y-3">
        <h2
          id="actions-heading"
          className="text-sm font-bold uppercase tracking-widest text-slate-400"
        >
          Tindakan Operasional
        </h2>
        <p className="text-sm text-slate-500 italic">
          Tidak ada tindakan yang tersedia untuk status warga ini.
        </p>
      </section>
    );
  }

  return (
    <>
      <section aria-labelledby="actions-heading" className="space-y-3">
        <h2
          id="actions-heading"
          className="text-sm font-bold uppercase tracking-widest text-slate-400 pb-1"
        >
          Tindakan Operasional
        </h2>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
          {/* ── Verify ───────────────────────────────────────────────────── */}
          {canVerify && (
            <ActionButton
              id="action-verify"
              icon={
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              }
              label="Verifikasi Warga"
              description="Setujui dan aktifkan warga prospektif"
              colorClass="text-emerald-400 bg-emerald-950/40 border-emerald-700/40 hover:border-emerald-500/60 hover:bg-emerald-950/60"
              onClick={() => setActiveAction('verify')}
            />
          )}

          {/* ── Relocate ─────────────────────────────────────────────────── */}
          {canRelocate && (
            <ActionButton
              id="action-relocate"
              icon={
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                </svg>
              }
              label="Relokasi Warga"
              description="Catat perpindahan ke RT atau luar wilayah"
              colorClass="text-blue-400 bg-blue-950/40 border-blue-700/40 hover:border-blue-500/60 hover:bg-blue-950/60"
              onClick={() => setActiveAction('relocate')}
            />
          )}

          {/* ── Change Status ─────────────────────────────────────────────── */}
          {canChangeStatus && (
            <ActionButton
              id="action-change-status"
              icon={
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
              }
              label="Ubah Status"
              description="Nonaktifkan, aktifkan kembali, atau catat kematian"
              colorClass="text-amber-400 bg-amber-950/40 border-amber-700/40 hover:border-amber-500/60 hover:bg-amber-950/60"
              onClick={() => setActiveAction('status')}
            />
          )}

          {/* ── Remove from Household ──────────────────────────────────────── */}
          {canRemoveFromHousehold && (
            <ActionButton
              id="action-remove-household"
              icon={
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
              }
              label="Keluarkan dari KK"
              description="Hapus keanggotaan dari Kartu Keluarga saat ini"
              colorClass="text-rose-400 bg-rose-950/40 border-rose-700/40 hover:border-rose-500/60 hover:bg-rose-950/60"
              onClick={() => setActiveAction('removeHousehold')}
            />
          )}
        </div>
      </section>

      {/* ── Dialogs (rendered outside the grid for proper z-index) ──────── */}
      <VerifyResidentDialog
        open={activeAction === 'verify'}
        onClose={() => setActiveAction(null)}
        resident={resident}
      />
      <ChangeResidentStatusDialog
        open={activeAction === 'status'}
        onClose={() => setActiveAction(null)}
        resident={resident}
      />
      <RelocateResidentDialog
        open={activeAction === 'relocate'}
        onClose={() => setActiveAction(null)}
        resident={resident}
      />
      <RemoveFromHouseholdDialog
        open={activeAction === 'removeHousehold'}
        onClose={() => setActiveAction(null)}
        resident={resident}
      />
    </>
  );
};

// ── ActionButton ───────────────────────────────────────────────────────────────

interface ActionButtonProps {
  id: string;
  icon: React.ReactNode;
  label: string;
  description: string;
  colorClass: string;
  onClick: () => void;
}

const ActionButton: React.FC<ActionButtonProps> = ({
  id,
  icon,
  label,
  description,
  colorClass,
  onClick,
}) => (
  <button
    id={id}
    type="button"
    onClick={onClick}
    className={`flex items-start gap-3 w-full text-left p-3.5 rounded-xl border transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-slate-900 focus:ring-emerald-500 cursor-pointer ${colorClass}`}
  >
    <span className="flex-shrink-0 mt-0.5" aria-hidden="true">
      {icon}
    </span>
    <span className="flex-1 min-w-0">
      <span className="block text-sm font-semibold leading-snug">{label}</span>
      <span className="block text-xs opacity-70 mt-0.5 font-normal text-current">{description}</span>
    </span>
  </button>
);
