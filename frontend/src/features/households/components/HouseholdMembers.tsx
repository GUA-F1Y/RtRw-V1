import React from 'react';
import { Link } from 'react-router-dom';
import type { HouseholdMember } from '../../../types/household.ts';
import { RELATIONSHIP_LABEL } from './householdConstants.ts';

interface HouseholdMembersProps {
  members: HouseholdMember[];
  activeMembersCount: number;
  onRemoveMember?: (member: HouseholdMember) => void;
  canRemove?: boolean;
}

function formatDate(dt: string): string {
  return new Date(dt).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}

/**
 * Household member list.
 * Active/historical members are clearly differentiated via is_active.
 * Current head is identified by relationship = KEPALA_KELUARGA + is_active = true.
 * Uses backend active_members_count as authoritative count (not members.length).
 * Links to Resident Detail using backend-provided resident_id.
 * Never displays NIK, phone, religion, or user_id.
 */
export const HouseholdMembers: React.FC<HouseholdMembersProps> = ({
  members,
  activeMembersCount,
  onRemoveMember,
  canRemove = false,
}) => {
  const activeMembers = members.filter((m) => m.is_active);
  const historicalMembers = members.filter((m) => !m.is_active);

  if (members.length === 0) {
    return (
      <section aria-labelledby="section-members">
        <h2 id="section-members" className="text-sm font-bold uppercase tracking-widest text-slate-400 pb-3">
          Anggota Keluarga
        </h2>
        <p className="text-sm text-slate-500 italic py-2">Belum ada data anggota.</p>
      </section>
    );
  }

  const MemberRow = ({ member }: { member: HouseholdMember }) => {
    const isHead = member.relationship === 'KEPALA_KELUARGA' && member.is_active;
    const name = member.resident?.name ?? `Warga ID: ${member.resident_id.slice(0, 8)}…`;

    return (
      <li
        className={`flex items-start justify-between gap-3 py-3 border-b border-slate-800/40 last:border-0 ${
          !member.is_active ? 'opacity-50' : ''
        }`}
      >
        <div className="flex items-start gap-3 flex-1 min-w-0">
          {/* Avatar */}
          <div
            className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 border ${
              isHead
                ? 'bg-emerald-900/60 border-emerald-700/40 text-emerald-300'
                : member.is_active
                ? 'bg-slate-800 border-slate-700/50 text-slate-300'
                : 'bg-slate-900 border-slate-800/50 text-slate-500'
            }`}
            aria-hidden="true"
          >
            {name.charAt(0).toUpperCase()}
          </div>

          <div className="flex-1 min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              {/* Name → Resident Detail link */}
              <Link
                to={`/app/residents/${member.resident_id}`}
                className="text-sm font-medium text-slate-200 hover:text-emerald-300 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-500 rounded truncate"
                aria-label={`Lihat profil ${name}`}
              >
                {name}
              </Link>
              {isHead && (
                <span className="text-xs bg-emerald-950/60 text-emerald-400 border border-emerald-700/40 px-1.5 py-0.5 rounded-full font-medium">
                  KK
                </span>
              )}
              {!member.is_active && (
                <span className="text-xs text-slate-600 italic">(mantan anggota)</span>
              )}
            </div>
            <p className="text-xs text-slate-500 mt-0.5">
              {RELATIONSHIP_LABEL[member.relationship] ?? member.relationship}
            </p>
            <div className="flex flex-wrap gap-x-3 text-xs text-slate-600 mt-0.5">
              {member.joined_at && (
                <span>Bergabung: {formatDate(member.joined_at)}</span>
              )}
              {member.left_at && (
                <span>Keluar: {formatDate(member.left_at)}</span>
              )}
            </div>
          </div>
        </div>

        {/* Action Button: Remove Member */}
        {member.is_active && canRemove && onRemoveMember && (
          <button
            type="button"
            onClick={() => onRemoveMember(member)}
            aria-label={`Keluarkan ${name} dari KK`}
            className="text-xs text-slate-400 hover:text-rose-400 hover:bg-rose-950/40 border border-transparent hover:border-rose-800/40 px-2.5 py-1 rounded-lg transition-colors flex-shrink-0 cursor-pointer"
          >
            Keluarkan
          </button>
        )}
      </li>
    );
  };

  return (
    <section aria-labelledby="section-members">
      <div className="flex items-center justify-between pb-3">
        <h2 id="section-members" className="text-sm font-bold uppercase tracking-widest text-slate-400">
          Anggota Keluarga
        </h2>
        {/* Use backend aggregate count, not members.length */}
        <span className="text-xs text-slate-500">
          <strong className="text-slate-400">{activeMembersCount}</strong> anggota aktif
        </span>
      </div>

      {/* Active members */}
      {activeMembers.length > 0 && (
        <div className="rounded-xl border border-slate-800/60 bg-slate-900/40 px-4 mb-4">
          <ul aria-label="Anggota aktif" role="list">
            {activeMembers.map((m) => (
              <MemberRow key={m.id} member={m} />
            ))}
          </ul>
        </div>
      )}

      {/* Historical members */}
      {historicalMembers.length > 0 && (
        <details className="mt-2">
          <summary className="text-xs text-slate-500 cursor-pointer hover:text-slate-400 transition-colors select-none py-1">
            {historicalMembers.length} mantan anggota
          </summary>
          <div className="rounded-xl border border-slate-800/40 bg-slate-900/20 px-4 mt-2">
            <ul aria-label="Mantan anggota" role="list">
              {historicalMembers.map((m) => (
                <MemberRow key={m.id} member={m} />
              ))}
            </ul>
          </div>
        </details>
      )}
    </section>
  );
};
