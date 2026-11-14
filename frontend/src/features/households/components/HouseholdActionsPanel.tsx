import React, { useState } from 'react';
import type { Household } from '../../../types/household.ts';
import { AddHouseholdMemberDialog } from './AddHouseholdMemberDialog.tsx';
import { ChangeHouseholdHeadDialog } from './ChangeHouseholdHeadDialog.tsx';
import { DissolveHouseholdDialog } from './DissolveHouseholdDialog.tsx';
import { EditHouseholdDialog } from './EditHouseholdDialog.tsx';
import { getHouseholdAvailableActions } from './householdActionUtils.ts';
import { MoveHouseholdDialog } from './MoveHouseholdDialog.tsx';

type ActionKey = 'edit' | 'addMember' | 'changeHead' | 'move' | 'dissolve' | null;

interface Props {
  household: Household;
}

export const HouseholdActionsPanel: React.FC<Props> = ({ household }) => {
  const [activeAction, setActiveAction] = useState<ActionKey>(null);

  const { canEdit, canAddMember, canChangeHead, canMove, canDissolve } =
    getHouseholdAvailableActions(household);

  const hasAnyAction = canEdit || canAddMember || canChangeHead || canMove || canDissolve;

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
          Kartu Keluarga dalam status {household.status} tidak menerima tindakan operasional lanjutan.
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

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
          {/* ── Edit Household ────────────────────────────────────────── */}
          {canEdit && (
            <ActionButton
              id="action-edit-household"
              icon={
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                </svg>
              }
              label="Ubah Data KK"
              description="Perbarui alamat dan catatan KK"
              colorClass="text-slate-300 bg-slate-800/60 border-slate-700/60 hover:border-slate-500 hover:bg-slate-800"
              onClick={() => setActiveAction('edit')}
            />
          )}

          {/* ── Add Member ────────────────────────────────────────────── */}
          {canAddMember && (
            <ActionButton
              id="action-add-member"
              icon={
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM3 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.5 21c-2.793 0-5.376-.92-7.5-2.47z" />
                </svg>
              }
              label="Tambah Anggota"
              description="Daftarkan warga ke dalam KK ini"
              colorClass="text-emerald-400 bg-emerald-950/40 border-emerald-700/40 hover:border-emerald-500/60 hover:bg-emerald-950/60"
              onClick={() => setActiveAction('addMember')}
            />
          )}

          {/* ── Change Head ───────────────────────────────────────────── */}
          {canChangeHead && (
            <ActionButton
              id="action-change-head"
              icon={
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                </svg>
              }
              label="Ganti Kepala KK"
              description={household.status === 'HEAD_VACANT' ? 'Tetapkan Kepala Keluarga aktif' : 'Pilih anggota menjadi Kepala KK'}
              colorClass="text-amber-400 bg-amber-950/40 border-amber-700/40 hover:border-amber-500/60 hover:bg-amber-950/60"
              onClick={() => setActiveAction('changeHead')}
            />
          )}

          {/* ── Move Household ────────────────────────────────────────── */}
          {canMove && (
            <ActionButton
              id="action-move-household"
              icon={
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                </svg>
              }
              label="Pindah RT"
              description="Pindahkan KK ke unit RT lain"
              colorClass="text-sky-400 bg-sky-950/40 border-sky-700/40 hover:border-sky-500/60 hover:bg-sky-950/60"
              onClick={() => setActiveAction('move')}
            />
          )}

          {/* ── Dissolve Household ────────────────────────────────────── */}
          {canDissolve && (
            <ActionButton
              id="action-dissolve-household"
              icon={
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
              }
              label="Bubarkan KK"
              description="Akhiri status aktif seluruh keluarga"
              colorClass="text-rose-400 bg-rose-950/40 border-rose-700/40 hover:border-rose-500/60 hover:bg-rose-950/60"
              onClick={() => setActiveAction('dissolve')}
            />
          )}
        </div>
      </section>

      {/* Dialogs */}
      <EditHouseholdDialog
        open={activeAction === 'edit'}
        onClose={() => setActiveAction(null)}
        household={household}
      />

      <AddHouseholdMemberDialog
        open={activeAction === 'addMember'}
        onClose={() => setActiveAction(null)}
        household={household}
      />

      <ChangeHouseholdHeadDialog
        open={activeAction === 'changeHead'}
        onClose={() => setActiveAction(null)}
        household={household}
      />

      <MoveHouseholdDialog
        open={activeAction === 'move'}
        onClose={() => setActiveAction(null)}
        household={household}
      />

      <DissolveHouseholdDialog
        open={activeAction === 'dissolve'}
        onClose={() => setActiveAction(null)}
        household={household}
      />
    </>
  );
};

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
    className={`flex items-start gap-3 p-3.5 rounded-xl border text-left transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-offset-slate-900 focus:ring-slate-400 ${colorClass}`}
  >
    <div className="mt-0.5 flex-shrink-0">{icon}</div>
    <div className="min-w-0 flex-1">
      <p className="text-sm font-semibold leading-snug">{label}</p>
      <p className="text-xs opacity-75 mt-0.5 line-clamp-1">{description}</p>
    </div>
  </button>
);
