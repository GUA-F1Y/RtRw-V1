import { Link, useParams } from 'react-router-dom';
import { useResident } from '../api/residentQueries.ts';
import { ResidentActionsPanel } from '../components/ResidentActionsPanel.tsx';
import { ResidentHouseholdSection } from '../components/ResidentHouseholdSection.tsx';
import { ResidentIdentitySection } from '../components/ResidentIdentitySection.tsx';
import { ResidentMovementHistory } from '../components/ResidentMovementHistory.tsx';
import { ResidentStatusBadge } from '../components/ResidentStatusBadge.tsx';
import { ResidentStatusHistorySection } from '../components/ResidentStatusHistory.tsx';

// ── Helpers ───────────────────────────────────────────────────────────────────

const GENDER_LABEL: Record<string, string> = { MALE: 'Laki-laki', FEMALE: 'Perempuan' };
const RESIDENCY_LABEL: Record<string, string> = {
  PERMANENT: 'Tetap',
  TEMPORARY: 'Sementara',
  SEASONAL: 'Musiman',
};

// ── Detail skeleton ───────────────────────────────────────────────────────────

function DetailSkeleton() {
  return (
    <div className="animate-pulse space-y-6">
      {/* Header */}
      <div className="rounded-2xl border border-slate-800/60 bg-slate-900/50 p-6 space-y-4">
        <div className="flex items-center gap-4">
          <div className="w-16 h-16 rounded-full bg-slate-800 flex-shrink-0" />
          <div className="space-y-2 flex-1">
            <div className="h-6 bg-slate-800 rounded w-48" />
            <div className="h-4 bg-slate-800 rounded w-32" />
          </div>
        </div>
      </div>
      {/* Sections */}
      {[1, 2, 3, 4].map((i) => (
        <div key={i} className="rounded-xl border border-slate-800/60 bg-slate-900/40 p-5 space-y-3">
          <div className="h-3 bg-slate-800 rounded w-28" />
          <div className="space-y-2">
            {[1, 2, 3].map((j) => (
              <div key={j} className="h-4 bg-slate-800 rounded w-full" />
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}

// ── Page ───────────────────────────────────────────────────────────────────────

export function ResidentDetailPage() {
  const { residentId } = useParams<{ residentId: string }>();

  const { data, isLoading, isError, error } = useResident(
    residentId ?? '',
    { include: ['status_history', 'movements'] },
    { enabled: Boolean(residentId) }
  );

  const resident = data?.data;

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Back link */}
        <div className="mb-6">
          <Link
            to="/app/residents"
            className="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-emerald-400 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-500 rounded"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
            </svg>
            Kembali ke Direktori
          </Link>
        </div>

        {/* Loading */}
        {isLoading && <DetailSkeleton />}

        {/* Error / not found */}
        {isError && (
          <div
            role="alert"
            className="rounded-xl border border-rose-500/30 bg-rose-500/10 px-6 py-5"
          >
            <p className="text-base font-semibold text-rose-400">
              {error?.status === 404 ? 'Data warga tidak ditemukan' : 'Gagal memuat data warga'}
            </p>
            <p className="text-sm text-rose-500 mt-1">
              {error?.status === 404
                ? 'Warga dengan ID ini tidak ada atau Anda tidak memiliki akses.'
                : (error?.message ?? 'Terjadi kesalahan. Silakan coba lagi.')}
            </p>
            <Link
              to="/app/residents"
              className="inline-flex items-center gap-1 mt-4 text-sm text-rose-400 hover:text-rose-300 transition-colors"
            >
              ← Kembali ke Direktori
            </Link>
          </div>
        )}

        {/* Main content */}
        {!isLoading && !isError && resident && (
          <div className="space-y-5">
            {/* Header card */}
            <div className="rounded-2xl border border-slate-800/60 bg-slate-900/50 p-6">
              <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                {/* Avatar */}
                <div
                  className="w-16 h-16 rounded-full bg-gradient-to-br from-emerald-900/60 to-slate-800 border border-emerald-700/30 flex items-center justify-center text-2xl font-bold text-emerald-400 flex-shrink-0"
                  aria-hidden="true"
                >
                  {resident.name.charAt(0).toUpperCase()}
                </div>

                {/* Name + metadata */}
                <div className="flex-1 min-w-0">
                  <div className="flex flex-wrap items-center gap-3 mb-1">
                    <h1 className="text-xl font-bold text-white tracking-tight">
                      {resident.name}
                    </h1>
                    <ResidentStatusBadge status={resident.status} />
                  </div>
                  <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-400">
                    <span>{GENDER_LABEL[resident.gender] ?? resident.gender}</span>
                    <span>{resident.age} tahun</span>
                    <span>{RESIDENCY_LABEL[resident.residency_type] ?? resident.residency_type}</span>
                  </div>
                </div>
              </div>
            </div>

            {/* Operational actions */}
            <div className="rounded-xl border border-slate-800/60 bg-slate-900/40 p-5">
              <ResidentActionsPanel resident={resident} />
            </div>

            {/* Identity section */}
            <div className="rounded-xl border border-slate-800/60 bg-slate-900/40 p-5">
              <ResidentIdentitySection resident={resident} />
            </div>

            {/* Household section */}
            <div className="rounded-xl border border-slate-800/60 bg-slate-900/40 p-5">
              <ResidentHouseholdSection resident={resident} />
            </div>

            {/* Status history */}
            <div className="rounded-xl border border-slate-800/60 bg-slate-900/40 p-5">
              <ResidentStatusHistorySection history={resident.status_history ?? []} />
            </div>

            {/* Movement history */}
            <div className="rounded-xl border border-slate-800/60 bg-slate-900/40 p-5">
              <ResidentMovementHistory movements={resident.movements ?? []} />
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
