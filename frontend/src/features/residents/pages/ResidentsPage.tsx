import { useCallback, useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import type {
  ResidentGender,
  ResidentMaritalStatus,
  ResidentQueryParams,
  ResidentStatus,
  ResidencyType,
} from '../../../types/resident.ts';
import { useResidents } from '../api/residentQueries.ts';
import { ResidentCard, ResidentCardSkeleton } from '../components/ResidentCard.tsx';
import {
  ResidentFilters,
  type ResidentFilterState,
} from '../components/ResidentFilters.tsx';
import { ResidentPagination } from '../components/ResidentPagination.tsx';
import { ResidentTable, ResidentTableSkeleton } from '../components/ResidentTable.tsx';

// ── URL params helpers ─────────────────────────────────────────────────────────

function paramsToFilters(params: URLSearchParams): ResidentFilterState {
  return {
    q: params.get('q') ?? '',
    status: (params.get('status') ?? '') as ResidentStatus | '',
    gender: (params.get('gender') ?? '') as ResidentGender | '',
    residency_type: (params.get('residency_type') ?? '') as ResidencyType | '',
    marital_status: (params.get('marital_status') ?? '') as ResidentMaritalStatus | '',
    sort: (params.get('sort') ?? '') as ResidentFilterState['sort'],
  };
}

function currentPage(params: URLSearchParams): number {
  const p = parseInt(params.get('page') ?? '1', 10);
  return isNaN(p) || p < 1 ? 1 : p;
}

// ── Page ───────────────────────────────────────────────────────────────────────

export function ResidentsPage() {
  const [searchParams, setSearchParams] = useSearchParams();

  // Filters are derived from URL (source of truth)
  const filters = paramsToFilters(searchParams);
  const page = currentPage(searchParams);

  // Local controlled search input — debounces into URL `q` param
  const [searchInput, setSearchInput] = useState<string>(filters.q);
  const [isFiltersOpen, setIsFiltersOpen] = useState<boolean>(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Sync searchInput when navigating back/forward
  useEffect(() => {
    setSearchInput(filters.q);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams]);

  // Debounce search → URL
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
            next.delete('page'); // reset pagination on search change
            return next;
          },
          { replace: true }
        );
      }, 300);
    },
    [setSearchParams]
  );

  // Filter select change
  const handleFilterChange = useCallback(
    (key: keyof ResidentFilterState, val: string) => {
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

  // Pagination
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

  // Reset all filters
  const handleReset = useCallback(() => {
    setSearchInput('');
    setSearchParams({}, { replace: true });
  }, [setSearchParams]);

  // Build query params for API
  const queryParams: ResidentQueryParams = {
    page,
    per_page: 20,
    ...(filters.q ? { q: filters.q } : {}),
    ...(filters.status ? { status: filters.status as ResidentStatus } : {}),
    ...(filters.gender ? { gender: filters.gender as ResidentGender } : {}),
    ...(filters.residency_type ? { residency_type: filters.residency_type as ResidencyType } : {}),
    ...(filters.marital_status
      ? { marital_status: filters.marital_status as ResidentMaritalStatus }
      : {}),
    ...(filters.sort ? { sort: filters.sort as ResidentQueryParams['sort'] } : {}),
  };

  const { data, isLoading, isError, error } = useResidents(queryParams);

  const residents = data?.data ?? [];
  const pagination = data?.meta?.pagination;
  const isEmpty = !isLoading && !isError && residents.length === 0;
  const hasFiltersOrSearch =
    filters.q || filters.status || filters.gender || filters.residency_type || filters.marital_status;

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="mb-8">
          <div className="flex items-center gap-3 mb-2">
            <div className="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center flex-shrink-0">
              <svg className="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
            <div>
              <h1 className="text-2xl font-bold text-white tracking-tight">Direktori Warga</h1>
              <p className="text-sm text-slate-400 mt-0.5">
                Daftar seluruh warga dalam wilayah RW
              </p>
            </div>
          </div>
          {pagination && (
            <p className="text-xs text-slate-500 tabular-nums mt-1">
              Total:{' '}
              <strong className="text-slate-400">{pagination.total.toLocaleString('id-ID')}</strong>{' '}
              warga terdaftar
            </p>
          )}
        </div>

        {/* Filters */}
        <div className="mb-5">
          <ResidentFilters
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

        {/* Error state */}
        {isError && (
          <div
            role="alert"
            className="rounded-xl border border-rose-500/30 bg-rose-500/10 px-5 py-4 mb-5"
          >
            <p className="text-sm font-semibold text-rose-400">Gagal memuat data warga</p>
            <p className="text-xs text-rose-500 mt-1">
              {error?.message ?? 'Terjadi kesalahan. Silakan coba lagi.'}
            </p>
          </div>
        )}

        {/* Loading state */}
        {isLoading && (
          <>
            {/* Desktop table skeleton */}
            <div className="hidden md:block">
              <ResidentTableSkeleton rows={10} />
            </div>
            {/* Mobile card skeletons */}
            <div className="md:hidden space-y-3">
              {Array.from({ length: 6 }).map((_, i) => (
                <ResidentCardSkeleton key={i} />
              ))}
            </div>
          </>
        )}

        {/* Empty state */}
        {isEmpty && (
          <div className="text-center py-16 rounded-xl border border-slate-800/60 bg-slate-900/30">
            <svg
              className="w-12 h-12 text-slate-700 mx-auto mb-4"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.5}
                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
              />
            </svg>
            {hasFiltersOrSearch ? (
              <>
                <p className="text-slate-400 font-medium text-sm">Tidak ada warga yang cocok</p>
                <p className="text-slate-600 text-xs mt-1">
                  Coba ubah kata kunci atau filter pencarian
                </p>
              </>
            ) : (
              <>
                <p className="text-slate-400 font-medium text-sm">Belum ada warga terdaftar</p>
                <p className="text-slate-600 text-xs mt-1">Data warga akan tampil di sini</p>
              </>
            )}
          </div>
        )}

        {/* Data */}
        {!isLoading && !isError && residents.length > 0 && (
          <>
            {/* Desktop table */}
            <div className="hidden md:block">
              <ResidentTable residents={residents} />
            </div>

            {/* Mobile card list */}
            <div className="md:hidden space-y-3">
              {residents.map((r) => (
                <ResidentCard key={r.id} resident={r} />
              ))}
            </div>

            {/* Pagination */}
            {pagination && pagination.last_page > 1 && (
              <div className="mt-5">
                <ResidentPagination pagination={pagination} onPageChange={handlePageChange} />
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}
