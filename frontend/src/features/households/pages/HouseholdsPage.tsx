import { useCallback, useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Button } from '../../../components/ui/Button.tsx';
import type { HouseholdHeadAvailability, HouseholdQueryParams, HouseholdStatus } from '../../../types/household.ts';
import { useHouseholds } from '../api/householdQueries.ts';
import { CreateHouseholdDialog } from '../components/CreateHouseholdDialog.tsx';
import { HouseholdCard, HouseholdCardSkeleton } from '../components/HouseholdCard.tsx';
import { HouseholdFilters, type HouseholdFilterState } from '../components/HouseholdFilters.tsx';
import { HouseholdPagination } from '../components/HouseholdPagination.tsx';
import { HouseholdTable, HouseholdTableSkeleton } from '../components/HouseholdTable.tsx';

// ── URL helpers ────────────────────────────────────────────────────────────────

function paramsToFilters(p: URLSearchParams): HouseholdFilterState {
  return {
    q: p.get('q') ?? '',
    status: (p.get('status') ?? '') as HouseholdStatus | '',
    head_availability: (p.get('head_availability') ?? '') as HouseholdHeadAvailability | '',
    sort: (p.get('sort') ?? '') as HouseholdFilterState['sort'],
  };
}

function currentPage(p: URLSearchParams): number {
  const n = parseInt(p.get('page') ?? '1', 10);
  return isNaN(n) || n < 1 ? 1 : n;
}

// ── Page ───────────────────────────────────────────────────────────────────────

export function HouseholdsPage() {
  const [searchParams, setSearchParams] = useSearchParams();

  const filters = paramsToFilters(searchParams);
  const page = currentPage(searchParams);

  const [searchInput, setSearchInput] = useState<string>(filters.q);
  const [isFiltersOpen, setIsFiltersOpen] = useState<boolean>(false);
  const [isCreateOpen, setIsCreateOpen] = useState<boolean>(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Sync searchInput on back/forward navigation
  useEffect(() => {
    setSearchInput(paramsToFilters(searchParams).q);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams]);

  const handleSearchChange = useCallback(
    (val: string) => {
      setSearchInput(val);
      if (debounceRef.current) clearTimeout(debounceRef.current);
      debounceRef.current = setTimeout(() => {
        setSearchParams(
          (prev) => {
            const next = new URLSearchParams(prev);
            if (val) {
              next.set('q', val);
            } else {
              next.delete('q');
            }
            next.delete('page');
            return next;
          },
          { replace: true }
        );
      }, 300);
    },
    [setSearchParams]
  );

  const handleFilterChange = useCallback(
    (key: keyof HouseholdFilterState, val: string) => {
      setSearchParams(
        (prev) => {
          const next = new URLSearchParams(prev);
          if (val) {
            next.set(key, val);
          } else {
            next.delete(key);
          }
          next.delete('page');
          return next;
        },
        { replace: true }
      );
    },
    [setSearchParams]
  );

  const handlePageChange = useCallback(
    (newPage: number) => {
      setSearchParams(
        (prev) => {
          const next = new URLSearchParams(prev);
          next.set('page', String(newPage));
          return next;
        },
        { replace: true }
      );
    },
    [setSearchParams]
  );

  const handleReset = useCallback(() => {
    setSearchInput('');
    setSearchParams({}, { replace: true });
  }, [setSearchParams]);

  // Build query params
  const queryParams: HouseholdQueryParams = {
    page,
    per_page: 20,
    // List query includes head info for display
    include: 'head.resident',
    ...(filters.q ? { q: filters.q } : {}),
    ...(filters.status ? { status: filters.status as HouseholdStatus } : {}),
    ...(filters.head_availability
      ? { head_availability: filters.head_availability as HouseholdHeadAvailability }
      : {}),
    ...(filters.sort ? { sort: filters.sort as HouseholdQueryParams['sort'] } : {}),
  };

  const { data, isLoading, isError, error } = useHouseholds(queryParams);

  const households = data?.data ?? [];
  const pagination = data?.meta?.pagination;
  const isEmpty = !isLoading && !isError && households.length === 0;
  const hasFiltersOrSearch =
    filters.q || filters.status || filters.head_availability;

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
          <div>
            <div className="flex items-center gap-3 mb-2">
              <div className="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center flex-shrink-0">
                <svg className="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
              </div>
              <div>
                <h1 className="text-2xl font-bold text-white tracking-tight">Registri Kartu Keluarga</h1>
                <p className="text-sm text-slate-400 mt-0.5">Daftar seluruh KK dalam wilayah RW</p>
              </div>
            </div>
            {pagination && (
              <p className="text-xs text-slate-500 tabular-nums mt-1">
                Total:{' '}
                <strong className="text-slate-400">{pagination.total.toLocaleString('id-ID')}</strong>{' '}
                KK terdaftar
              </p>
            )}
          </div>

          <Button
            type="button"
            variant="primary"
            onClick={() => setIsCreateOpen(true)}
            id="btn-create-household"
            className="self-start sm:self-auto"
          >
            <svg className="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Tambah KK Baru
          </Button>
        </div>

        {/* Filters */}
        <div className="mb-5">
          <HouseholdFilters
            filters={filters}
            searchInput={searchInput}
            isFiltersOpen={isFiltersOpen}
            isLoading={isLoading}
            onSearchChange={handleSearchChange}
            onFilterChange={handleFilterChange}
            onToggleFilters={() => setIsFiltersOpen((o) => !o)}
            onReset={handleReset}
          />
        </div>

        {/* Error */}
        {isError && (
          <div role="alert" className="rounded-xl border border-rose-500/30 bg-rose-500/10 px-5 py-4 mb-5">
            <p className="text-sm font-semibold text-rose-400">Gagal memuat data KK</p>
            <p className="text-xs text-rose-500 mt-1">
              {error?.message ?? 'Terjadi kesalahan. Silakan coba lagi.'}
            </p>
          </div>
        )}

        {/* Loading skeleton */}
        {isLoading && (
          <>
            <div className="hidden md:block">
              <HouseholdTableSkeleton rows={10} />
            </div>
            <div className="md:hidden space-y-3">
              {Array.from({ length: 6 }).map((_, i) => (
                <HouseholdCardSkeleton key={i} />
              ))}
            </div>
          </>
        )}

        {/* Empty state */}
        {isEmpty && (
          <div className="text-center py-16 rounded-xl border border-slate-800/60 bg-slate-900/30">
            <svg className="w-12 h-12 text-slate-700 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            {hasFiltersOrSearch ? (
              <>
                <p className="text-slate-400 font-medium text-sm">Tidak ada KK yang sesuai dengan pencarian atau filter</p>
                <p className="text-slate-600 text-xs mt-1">Coba ubah kata kunci atau filter pencarian</p>
              </>
            ) : (
              <>
                <p className="text-slate-400 font-medium text-sm">Belum ada data KK pada cakupan ini</p>
                <p className="text-slate-600 text-xs mt-1">Data KK akan tampil di sini setelah didaftarkan</p>
              </>
            )}
          </div>
        )}

        {/* Data */}
        {!isLoading && !isError && households.length > 0 && (
          <>
            <div className="hidden md:block">
              <HouseholdTable households={households} />
            </div>
            <div className="md:hidden space-y-3">
              {households.map((hh) => (
                <HouseholdCard key={hh.id} household={hh} />
              ))}
            </div>
            {pagination && pagination.last_page > 1 && (
              <div className="mt-5">
                <HouseholdPagination pagination={pagination} onPageChange={handlePageChange} />
              </div>
            )}
          </>
        )}

        {/* Create Household Dialog */}
        <CreateHouseholdDialog
          open={isCreateOpen}
          onClose={() => setIsCreateOpen(false)}
        />
      </div>
    </div>
  );
}
