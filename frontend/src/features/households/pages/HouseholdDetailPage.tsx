import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import type { HouseholdMember } from '../../../types/household.ts';
import { useHousehold } from '../api/householdQueries.ts';
import { HouseholdActionsPanel } from '../components/HouseholdActionsPanel.tsx';
import { HouseholdHeadSection } from '../components/HouseholdHeadSection.tsx';
import { HouseholdMembers } from '../components/HouseholdMembers.tsx';
import { HouseholdStatusBadge } from '../components/HouseholdStatusBadge.tsx';
import { RemoveHouseholdMemberDialog } from '../components/RemoveHouseholdMemberDialog.tsx';

// ── Helpers ───────────────────────────────────────────────────────────────────

function formatDate(dt: string): string {
  return new Date(dt).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}

// ── Skeleton ──────────────────────────────────────────────────────────────────

function DetailSkeleton() {
  return (
    <div className="animate-pulse space-y-6">
      <div className="rounded-2xl border border-slate-800/60 bg-slate-900/50 p-6 space-y-4">
        <div className="flex items-center gap-4">
          <div className="w-14 h-14 rounded-xl bg-slate-800 flex-shrink-0" />
          <div className="space-y-2 flex-1">
            <div className="h-6 bg-slate-800 rounded w-48" />
            <div className="h-4 bg-slate-800 rounded w-32" />
          </div>
        </div>
      </div>
      {[1, 2, 3].map((i) => (
        <div key={i} className="rounded-xl border border-slate-800/60 bg-slate-900/40 p-5 space-y-3">
          <div className="h-3 bg-slate-800 rounded w-28" />
          {[1, 2, 3].map((j) => (
            <div key={j} className="h-4 bg-slate-800 rounded w-full" />
          ))}
        </div>
      ))}
    </div>
  );
}

// ── Field Row ─────────────────────────────────────────────────────────────────

function FieldRow({ label, value }: { label: string; value?: string | null }) {
  if (!value) return null;
  return (
    <div className="flex flex-col sm:flex-row sm:items-baseline gap-0.5 sm:gap-4 py-2.5 border-b border-slate-800/40 last:border-0">
      <dt className="text-xs font-semibold uppercase tracking-wider text-slate-500 sm:w-44 flex-shrink-0">
        {label}
      </dt>
      <dd className="text-sm text-slate-200">{value}</dd>
    </div>
  );
}

// ── Page ───────────────────────────────────────────────────────────────────────

export function HouseholdDetailPage() {
  const { householdId } = useParams<{ householdId: string }>();
  const [memberToRemove, setMemberToRemove] = useState<HouseholdMember | null>(null);

  const { data, isLoading, isError, error } = useHousehold(
    householdId ?? '',
    { include: ['head.resident', 'members.resident'] },
    { enabled: Boolean(householdId) }
  );

  const household = data?.data;

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Back link — preserves registry URL state via browser back */}
        <div className="mb-6">
          <Link
            to="/app/households"
            className="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-emerald-400 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-500 rounded"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
            </svg>
            Kembali ke Registri
          </Link>
        </div>

        {/* Loading */}
        {isLoading && <DetailSkeleton />}

        {/* Error / not found */}
        {isError && (
          <div role="alert" className="rounded-xl border border-rose-500/30 bg-rose-500/10 px-6 py-5">
            <p className="text-base font-semibold text-rose-400">
              {error?.status === 404
                ? 'Data KK tidak ditemukan'
                : error?.status === 403
                ? 'Anda tidak memiliki akses ke KK ini'
                : 'Gagal memuat data KK'}
            </p>
            <p className="text-sm text-rose-500 mt-1">
              {error?.status === 404
                ? 'KK dengan ID ini tidak ada atau di luar cakupan Anda.'
                : error?.status === 403
                ? 'Pastikan Anda memiliki wewenang untuk mengakses data ini.'
                : (error?.message ?? 'Terjadi kesalahan. Silakan coba lagi.')}
            </p>
            <Link
              to="/app/households"
              className="inline-flex items-center gap-1 mt-4 text-sm text-rose-400 hover:text-rose-300 transition-colors"
            >
              ← Kembali ke Registri
            </Link>
          </div>
        )}

        {/* Main content */}
        {!isLoading && !isError && household && (
          <div className="space-y-5">
            {/* Header card */}
            <div className="rounded-2xl border border-slate-800/60 bg-slate-900/50 p-6">
              <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                {/* Icon */}
                <div
                  className="w-14 h-14 rounded-xl bg-gradient-to-br from-emerald-900/60 to-slate-800 border border-emerald-700/30 flex items-center justify-center flex-shrink-0"
                  aria-hidden="true"
                >
                  <svg className="w-7 h-7 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                  </svg>
                </div>

                <div className="flex-1 min-w-0">
                  <div className="flex flex-wrap items-center gap-3 mb-1">
                    {/* KK Number — rendered as-is from server */}
                    <h1 className="text-xl font-bold text-white tracking-tight font-mono">
                      {household.kk_number ?? (
                        <span className="text-slate-500 font-sans font-normal italic text-lg">No. KK tidak tersedia</span>
                      )}
                    </h1>
                    <HouseholdStatusBadge status={household.status} size="md" />
                  </div>
                  <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-400">
                    <span>{household.active_members_count} anggota aktif</span>
                    <span>RT {String(household.rt_id).slice(-3)}</span>
                    {household.address && (
                      <span className="truncate max-w-[240px]">{household.address}</span>
                    )}
                  </div>
                </div>
              </div>

              {/* Head-vacant alert in header */}
              {household.status === 'HEAD_VACANT' && (
                <div className="mt-4 flex items-center gap-2 text-sm text-amber-400 bg-amber-950/20 border border-amber-700/30 rounded-lg px-3 py-2">
                  <svg className="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                  </svg>
                  <span>KK ini belum memiliki Kepala Keluarga yang aktif</span>
                </div>
              )}
            </div>

            {/* Operational Actions Panel */}
            <div className="rounded-xl border border-slate-800/60 bg-slate-900/40 p-5">
              <HouseholdActionsPanel household={household} />
            </div>

            {/* Household Information */}
            <div className="rounded-xl border border-slate-800/60 bg-slate-900/40 p-5">
              <section aria-labelledby="section-info">
                <h2 id="section-info" className="text-sm font-bold uppercase tracking-widest text-slate-400 pb-3">
                  Informasi KK
                </h2>
                <dl>
                  <FieldRow label="Alamat" value={household.address} />
                  <FieldRow label="Catatan" value={household.notes ?? undefined} />
                  {household.created_at && (
                    <FieldRow label="Terdaftar" value={formatDate(household.created_at)} />
                  )}
                  {household.updated_at && (
                    <FieldRow label="Diperbarui" value={formatDate(household.updated_at)} />
                  )}
                </dl>
              </section>
            </div>

            {/* Head section */}
            <div className="rounded-xl border border-slate-800/60 bg-slate-900/40 p-5">
              <HouseholdHeadSection household={household} />
            </div>

            {/* Members section */}
            <div className="rounded-xl border border-slate-800/60 bg-slate-900/40 p-5">
              <HouseholdMembers
                members={household.members ?? []}
                activeMembersCount={household.active_members_count}
                onRemoveMember={setMemberToRemove}
                canRemove={household.status === 'ACTIVE' || household.status === 'HEAD_VACANT'}
              />
            </div>

            {/* Remove Member Dialog (from list action) */}
            <RemoveHouseholdMemberDialog
              open={Boolean(memberToRemove)}
              onClose={() => setMemberToRemove(null)}
              household={household}
              member={memberToRemove}
            />
          </div>
        )}
      </div>
    </div>
  );
}
