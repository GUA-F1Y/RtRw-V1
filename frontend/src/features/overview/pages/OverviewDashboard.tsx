import React from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Badge } from '../../../components/ui/Badge.tsx';
import { Card } from '../../../components/ui/Card.tsx';
import { useOverview } from '../api/overviewQueries.ts';
import { PopulationStatusBar } from '../components/PopulationStatusBar.tsx';
import { RtBreakdownTable } from '../components/RtBreakdownTable.tsx';
import { StatCard } from '../components/StatCard.tsx';

// --- Icons (inline SVG, zero dependencies) ---

const IconUsers = () => (
  <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.75} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
  </svg>
);

const IconUserCheck = () => (
  <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.75} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
  </svg>
);

const IconHome = () => (
  <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.75} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
  </svg>
);

const IconAlert = () => (
  <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.75} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.06 16.5c-.77.833.192 2.5 1.732 2.5z" />
  </svg>
);

const IconMap = () => (
  <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.75} d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
  </svg>
);

const IconRefresh = () => (
  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
  </svg>
);

// --- Helpers ---

function formatScopeLabel(level: string | undefined, name?: string): string {
  if (!level) return 'Platform';
  const upper = level.toUpperCase();
  if (name) return `${upper} — ${name}`;
  return upper;
}

function formatTime(iso: string): string {
  return new Date(iso).toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
}

// --- Main Dashboard ---

export const OverviewDashboard: React.FC = () => {
  const { data, isLoading, isError, error, dataUpdatedAt, refetch, isFetching } = useOverview({
    staleTime: 60_000,
    refetchInterval: 120_000,
  });

  const overview = data?.data;

  const householdVacantCount = overview?.households.head_vacant ?? 0;
  const hasVacantWarning = householdVacantCount > 0;

  const activeRate =
    overview && overview.population.total > 0
      ? ((overview.population.active / overview.population.total) * 100).toFixed(1)
      : null;

  return (
    <div className="space-y-7">
      {/* ── Page Header ─────────────────────────────────────────────────────── */}
      <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <div className="flex items-center gap-2.5 mb-1.5">
            <h1 className="text-xl sm:text-2xl font-bold text-slate-100 tracking-tight">
              Ringkasan RW
            </h1>
            {!isLoading && overview && (
              <Badge variant="emerald" size="sm">
                {formatScopeLabel(overview.scope.level)}
              </Badge>
            )}
          </div>
          <p className="text-sm text-slate-400">
            {overview?.scope.name
              ? `Cakupan: ${overview.scope.name}`
              : 'Data agregat populasi & hunian dalam wilayah Anda'}
          </p>
        </div>

        {/* Refresh + Last Updated */}
        <div className="flex items-center gap-3 flex-shrink-0">
          {dataUpdatedAt > 0 && (
            <span className="text-[11px] text-slate-500 font-mono hidden sm:block">
              Diperbarui {formatTime(new Date(dataUpdatedAt).toISOString())}
            </span>
          )}
          <button
            type="button"
            onClick={() => refetch()}
            disabled={isFetching}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border border-slate-700 text-slate-300 hover:bg-slate-800 hover:text-slate-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            aria-label="Refresh data"
          >
            <span className={isFetching ? 'animate-spin' : ''}>
              <IconRefresh />
            </span>
            Refresh
          </button>
        </div>
      </div>

      {/* ── Error State ──────────────────────────────────────────────────────── */}
      {isError && (
        <Alert
          type="error"
          title="Gagal memuat data ringkasan"
          message={error?.message ?? 'Terjadi kesalahan saat memuat data. Coba refresh halaman.'}
          requestId={error?.requestId}
        />
      )}

      {/* ── Vacant Household Warning ─────────────────────────────────────────── */}
      {!isLoading && hasVacantWarning && (
        <div className="flex items-start gap-3 rounded-xl border border-amber-800/50 bg-amber-950/20 p-4">
          <span className="text-amber-400 mt-0.5 flex-shrink-0">
            <IconAlert />
          </span>
          <div>
            <p className="text-sm font-semibold text-amber-300">
              {householdVacantCount} KK tanpa Kepala Keluarga
            </p>
            <p className="text-xs text-amber-400/70 mt-0.5">
              Terdapat KK yang belum memiliki kepala keluarga yang ditetapkan. Segera tindak lanjuti.
            </p>
          </div>
        </div>
      )}

      {/* ── KPI Stat Cards ───────────────────────────────────────────────────── */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          label="Total Warga"
          value={overview?.population.total ?? 0}
          sub="Seluruh status"
          accent="emerald"
          loading={isLoading}
          icon={<IconUsers />}
        />
        <StatCard
          label="Warga Aktif"
          value={overview?.population.active ?? 0}
          sub={activeRate ? `${activeRate}% dari total` : undefined}
          accent="blue"
          loading={isLoading}
          icon={<IconUserCheck />}
        />
        <StatCard
          label="KK Aktif"
          value={overview?.households.active ?? 0}
          sub="Kepala keluarga terdaftar"
          accent="indigo"
          loading={isLoading}
          icon={<IconHome />}
        />
        <StatCard
          label="KK Tanpa KK"
          value={overview?.households.head_vacant ?? 0}
          sub="Perlu penugasan kepala"
          accent={hasVacantWarning ? 'amber' : 'slate'}
          loading={isLoading}
          icon={<IconAlert />}
        />
      </div>

      {/* ── Secondary Stats ──────────────────────────────────────────────────── */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          label="Warga Calon"
          value={overview?.population.prospective ?? 0}
          sub="Pendaftaran diproses"
          accent="blue"
          loading={isLoading}
          icon={<IconUsers />}
        />
        <StatCard
          label="Warga Pindah"
          value={overview?.population.moved ?? 0}
          sub="Sudah tidak berdomisili"
          accent="slate"
          loading={isLoading}
          icon={<IconUsers />}
        />
        <StatCard
          label="KK Pindah"
          value={overview?.households.moved ?? 0}
          sub="Sudah tidak aktif"
          accent="slate"
          loading={isLoading}
          icon={<IconHome />}
        />
        <StatCard
          label="Cakupan RT"
          value={overview?.rt_breakdown.length ?? 0}
          sub="RT dalam wilayah ini"
          accent="indigo"
          loading={isLoading}
          icon={<IconMap />}
        />
      </div>

      {/* ── Bottom Row: Population Bar + RT Table ────────────────────────────── */}
      <div className="grid grid-cols-1 xl:grid-cols-5 gap-6">
        {/* Population Status Distribution */}
        <div className="xl:col-span-2">
          <Card
            title="Distribusi Status Warga"
            subtitle="Komposisi berdasarkan status kependudukan"
            badge={
              overview && (
                <Badge variant="slate" size="sm">
                  {overview.population.total.toLocaleString('id-ID')} jiwa
                </Badge>
              )
            }
          >
            {overview ? (
              <PopulationStatusBar population={overview.population} loading={isLoading} />
            ) : (
              <PopulationStatusBar
                population={{ total: 0, active: 0, prospective: 0, inactive: 0, moved: 0, deceased: 0 }}
                loading={isLoading}
              />
            )}
          </Card>
        </div>

        {/* RT Breakdown Table */}
        <div className="xl:col-span-3">
          <Card
            title="Rincian Per RT"
            subtitle="Perbandingan jumlah warga dan KK antar RT"
            badge={
              overview?.rt_breakdown.length ? (
                <Badge variant="indigo" size="sm">
                  {overview.rt_breakdown.length} RT
                </Badge>
              ) : undefined
            }
          >
            <RtBreakdownTable
              rtBreakdown={overview?.rt_breakdown ?? []}
              loading={isLoading}
            />
          </Card>
        </div>
      </div>

      {/* ── Household Metrics Summary Card ───────────────────────────────────── */}
      {!isLoading && overview && (
        <Card
          title="Ringkasan Data Hunian"
          subtitle="Status KK dan kepala keluarga secara keseluruhan"
        >
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
            {[
              {
                label: 'KK Aktif',
                value: overview.households.active,
                color: 'text-emerald-400',
                bg: 'bg-emerald-950/30 border-emerald-900/30',
              },
              {
                label: 'KK dengan KK',
                value: overview.households.head_assigned,
                color: 'text-blue-400',
                bg: 'bg-blue-950/30 border-blue-900/30',
              },
              {
                label: 'KK tanpa KK',
                value: overview.households.head_vacant,
                color: overview.households.head_vacant > 0 ? 'text-amber-400' : 'text-emerald-400',
                bg:
                  overview.households.head_vacant > 0
                    ? 'bg-amber-950/30 border-amber-900/30'
                    : 'bg-emerald-950/30 border-emerald-900/30',
              },
              {
                label: 'KK Bubar/Pindah',
                value: overview.households.moved + overview.households.dissolved,
                color: 'text-slate-400',
                bg: 'bg-slate-800/40 border-slate-700/30',
              },
            ].map((item) => (
              <div key={item.label} className={`rounded-xl border ${item.bg} p-4`}>
                <div className={`text-2xl font-bold tabular-nums ${item.color}`}>
                  {item.value.toLocaleString('id-ID')}
                </div>
                <div className="text-xs text-slate-500 mt-1">{item.label}</div>
              </div>
            ))}
          </div>
        </Card>
      )}
    </div>
  );
};
