import React from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Badge } from '../../../components/ui/Badge.tsx';
import type { RtUnit } from '../../../types/organization.ts';
import { useOrganizations, useRwWithRts, useRws } from '../api/hierarchyQueries.ts';
import { HierarchyEmptyState } from '../components/HierarchyEmptyState.tsx';
import { OrganizationSummary } from '../components/OrganizationSummary.tsx';
import { RwUnitSection } from '../components/RwUnitSection.tsx';

// ── Skeletons ────────────────────────────────────────────────────────────────

function OrgSkeleton() {
  return (
    <div className="flex items-start gap-4 p-5 rounded-2xl border border-slate-800/60 bg-slate-900/50 animate-pulse">
      <div className="w-12 h-12 rounded-xl bg-slate-800 flex-shrink-0" />
      <div className="flex-1 space-y-2">
        <div className="h-3 w-20 bg-slate-800 rounded" />
        <div className="h-5 w-48 bg-slate-800 rounded" />
        <div className="h-3 w-32 bg-slate-800 rounded" />
      </div>
    </div>
  );
}

function RwSkeleton() {
  return (
    <div className="rounded-2xl border border-slate-800/60 bg-slate-900/40 overflow-hidden animate-pulse">
      <div className="px-5 py-4 border-b border-slate-800/60 bg-slate-900/60 flex items-center gap-3">
        <div className="w-10 h-10 rounded-xl bg-slate-800 flex-shrink-0" />
        <div className="space-y-2 flex-1">
          <div className="h-4 w-32 bg-slate-800 rounded" />
          <div className="h-3 w-24 bg-slate-700 rounded" />
        </div>
      </div>
      <div className="p-5 space-y-3">
        {[...Array(3)].map((_, i) => (
          <div key={i} className="h-16 rounded-xl bg-slate-800/30" />
        ))}
      </div>
    </div>
  );
}

// ── Summary Bar ───────────────────────────────────────────────────────────────

interface HierarchySummaryBarProps {
  orgCount: number;
  rwCount: number;
  rtCount: number;
  activeRtCount: number;
}

function HierarchySummaryBar({ orgCount, rwCount, rtCount, activeRtCount }: HierarchySummaryBarProps) {
  return (
    <div className="flex flex-wrap gap-3 text-xs">
      <div className="flex items-center gap-1.5 bg-slate-900/60 border border-slate-800/60 rounded-lg px-3 py-1.5">
        <span className="text-slate-400">Organisasi</span>
        <Badge variant="emerald" size="sm">{orgCount}</Badge>
      </div>
      <div className="flex items-center gap-1.5 bg-slate-900/60 border border-slate-800/60 rounded-lg px-3 py-1.5">
        <span className="text-slate-400">RW</span>
        <Badge variant="blue" size="sm">{rwCount}</Badge>
      </div>
      <div className="flex items-center gap-1.5 bg-slate-900/60 border border-slate-800/60 rounded-lg px-3 py-1.5">
        <span className="text-slate-400">RT Aktif</span>
        <Badge variant="indigo" size="sm">{activeRtCount}/{rtCount}</Badge>
      </div>
    </div>
  );
}

// ── RW Section with self-contained RT load ────────────────────────────────────

interface RwSectionContainerProps {
  rwId: string;
  rwNumber: number;
  rwName: string;
  rwStatus: string;
  rtsCount?: number;
  organizationId: string;
}

/**
 * Fetches a single RW (which includes embedded `rts`) and renders the section.
 * Using rw.show instead of a separate rts list avoids an extra round-trip.
 * This is O(# of RWs) — not N+1 because we already fetched the RW list and
 * we fetch each RW detail in parallel (enabled={true} for all).
 */
const RwSectionContainer: React.FC<RwSectionContainerProps> = ({
  rwId,
  rwNumber,
  rwName,
  rwStatus,
  rtsCount,
  organizationId: _organizationId,
}) => {
  const { data: rwDetail, isLoading, error } = useRwWithRts(rwId);

  // Extract RT list from embedded rts, or fall back to empty
  const rts: RtUnit[] = rwDetail?.rts ?? [];

  if (error) {
    return (
      <Alert
        type="error"
        title={`Gagal memuat RT untuk RW ${String(rwNumber).padStart(2, '0')}`}
        message={error.message ?? 'Terjadi kesalahan saat memuat data RT.'}
        requestId={error.requestId}
      />
    );
  }

  return (
    <RwUnitSection
      rw={{ id: rwId, organization_id: _organizationId, number: rwNumber, name: rwName, status: rwStatus as 'active' | 'inactive', rts_count: rtsCount, created_at: '' }}
      rts={rts}
      isLoadingRts={isLoading}
    />
  );
};

// ── Main Page ─────────────────────────────────────────────────────────────────

export const HierarchyPage: React.FC = () => {
  // Step 1: Load organizations (scoped by backend to authenticated user's memberships)
  const {
    data: organizations,
    isLoading: isLoadingOrgs,
    isError: isOrgsError,
    error: orgsError,
    refetch: _refetchOrgs,
  } = useOrganizations();

  // Step 2: Load RWs for first (primary) organization
  // If the user belongs to multiple orgs, all are iterated below.
  // We still use the first org's RWs as the primary context.
  const primaryOrgId = organizations?.[0]?.id ?? null;

  const {
    data: rws,
    isLoading: isLoadingRws,
    isError: isRwsError,
    error: rwsError,
  } = useRws(primaryOrgId);

  // ── Derived summary stats (safe derivation from loaded data) ──
  // We only have RT counts from rwDetail responses. Summarize after they load.
  const totalRwCount = rws?.length ?? 0;

  return (
    <div className="space-y-7">
      {/* ── Page Header ─────────────────────────────────────────────────────── */}
      <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <h1 className="text-xl sm:text-2xl font-bold text-slate-100 tracking-tight mb-1.5">
            Hierarki &amp; Unit
          </h1>
          <p className="text-sm text-slate-400">
            Lihat struktur RW dan unit RT yang berada dalam cakupan akses Anda.
          </p>
        </div>

        {/* Summary badges (shown after load) */}
        {!isLoadingOrgs && !isLoadingRws && organizations && rws && (
          <HierarchySummaryBar
            orgCount={organizations.length}
            rwCount={totalRwCount}
            rtCount={rws.reduce((s, r) => s + (r.rts_count ?? 0), 0)}
            activeRtCount={rws.reduce((s, r) => s + (r.rts_count ?? 0), 0)} // refined after RT loads
          />
        )}
      </div>

      {/* ── Hierarchy Legend ─────────────────────────────────────────────────── */}
      <div
        className="flex flex-wrap gap-2 text-[10px] font-semibold uppercase tracking-wider"
        aria-label="Hierarki administratif"
      >
        {[
          { label: 'Organisasi', color: 'text-emerald-400 bg-emerald-950/40 border-emerald-800/40' },
          { label: '→', color: 'text-slate-600' },
          { label: 'RW (Koordinasi)', color: 'text-blue-400 bg-blue-950/40 border-blue-800/40' },
          { label: '→', color: 'text-slate-600' },
          { label: 'RT (Operasional)', color: 'text-indigo-400 bg-indigo-950/40 border-indigo-800/40' },
          { label: '→', color: 'text-slate-600' },
          { label: 'KK & Warga', color: 'text-slate-500 bg-slate-800/40 border-slate-700/40 opacity-60' },
        ].map((tier, i) => (
          tier.label === '→' ? (
            <span key={i} className={`self-center ${tier.color}`}>{tier.label}</span>
          ) : (
            <span
              key={i}
              className={`px-2.5 py-1 rounded-lg border ${tier.color}`}
            >
              {tier.label}
            </span>
          )
        ))}
      </div>

      {/* ── Error: Organizations ──────────────────────────────────────────────── */}
      {isOrgsError && (
        <Alert
          type="error"
          title="Gagal memuat data organisasi"
          message={orgsError?.message ?? 'Terjadi kesalahan saat memuat hierarki.'}
          requestId={orgsError?.requestId}
        />
      )}

      {/* ── Loading: Organizations ────────────────────────────────────────────── */}
      {isLoadingOrgs && (
        <div className="space-y-6">
          <OrgSkeleton />
          <RwSkeleton />
          <RwSkeleton />
        </div>
      )}

      {/* ── Empty: No Organizations ───────────────────────────────────────────── */}
      {!isLoadingOrgs && !isOrgsError && organizations?.length === 0 && (
        <HierarchyEmptyState variant="no-organizations" />
      )}

      {/* ── Organization Sections ─────────────────────────────────────────────── */}
      {!isLoadingOrgs && organizations?.map((org) => (
        <OrgSection
          key={org.id}
          orgId={org.id}
          orgName={org.name}
          orgSlug={org.slug}
          orgRwsCount={org.rws_count}
          rws={org.id === primaryOrgId ? rws : undefined}
          isLoadingRws={org.id === primaryOrgId ? isLoadingRws : false}
          isRwsError={org.id === primaryOrgId ? isRwsError : false}
          rwsError={org.id === primaryOrgId ? rwsError : null}
        />
      ))}
    </div>
  );
};

// ── Per-Organization Section ──────────────────────────────────────────────────

interface OrgSectionProps {
  orgId: string;
  orgName: string;
  orgSlug: string;
  orgRwsCount?: number;
  rws: ReturnType<typeof useRws>['data'];
  isLoadingRws: boolean;
  isRwsError: boolean;
  rwsError: ReturnType<typeof useRws>['error'];
}

function OrgSection({
  orgId,
  orgName,
  orgSlug,
  orgRwsCount,
  rws,
  isLoadingRws,
  isRwsError,
  rwsError,
}: OrgSectionProps) {
  return (
    <section className="space-y-5" aria-label={`Organisasi: ${orgName}`}>
      {/* Organization summary header */}
      <OrganizationSummary
        organization={{ id: orgId, name: orgName, slug: orgSlug, rws_count: orgRwsCount, created_at: '' }}
      />

      {/* RW Error */}
      {isRwsError && (
        <Alert
          type="error"
          title="Gagal memuat data RW"
          message={rwsError?.message ?? 'Terjadi kesalahan saat memuat unit RW.'}
          requestId={rwsError?.requestId}
        />
      )}

      {/* RW Loading */}
      {isLoadingRws && (
        <div className="space-y-4 pl-4">
          <RwSkeleton />
          <RwSkeleton />
        </div>
      )}

      {/* RW Empty */}
      {!isLoadingRws && !isRwsError && rws?.length === 0 && (
        <div className="pl-4">
          <HierarchyEmptyState variant="no-rws" />
        </div>
      )}

      {/* RW List */}
      {!isLoadingRws && rws && rws.length > 0 && (
        <div className="space-y-4 pl-4 border-l-2 border-slate-800/60">
          {rws.map((rw) => (
            <RwSectionContainer
              key={rw.id}
              rwId={rw.id}
              rwNumber={rw.number}
              rwName={rw.name}
              rwStatus={rw.status}
              rtsCount={rw.rts_count}
              organizationId={orgId}
            />
          ))}
        </div>
      )}
    </section>
  );
}
