import React from 'react';
import type { PaginationMeta } from '../../../lib/api/types.ts';

interface HouseholdPaginationProps {
  pagination: PaginationMeta;
  onPageChange: (page: number) => void;
}

export const HouseholdPagination: React.FC<HouseholdPaginationProps> = ({
  pagination,
  onPageChange,
}) => {
  const { current_page, last_page, total, per_page } = pagination;
  const from = (current_page - 1) * per_page + 1;
  const to = Math.min(current_page * per_page, total);

  if (last_page <= 1) return null;

  const delta = 2;
  const range: number[] = [];
  for (
    let i = Math.max(1, current_page - delta);
    i <= Math.min(last_page, current_page + delta);
    i++
  ) {
    range.push(i);
  }

  const btnBase =
    'min-w-[2.25rem] h-9 px-2 rounded-lg text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-500';
  const btnActive = 'bg-emerald-950/80 text-emerald-300 border border-emerald-700/60';
  const btnInactive =
    'bg-slate-900/60 text-slate-400 border border-slate-700/60 hover:bg-slate-800/60 hover:text-slate-200';
  const btnDisabled =
    'opacity-30 cursor-not-allowed bg-slate-900/40 text-slate-600 border border-slate-800/40';

  return (
    <nav className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-2" aria-label="Navigasi halaman KK">
      <p className="text-xs text-slate-500 tabular-nums">
        Menampilkan {from}–{to} dari <strong className="text-slate-400">{total.toLocaleString('id-ID')}</strong> KK
      </p>

      <div className="flex items-center gap-1.5 flex-wrap">
        {/* Prev */}
        <button
          type="button"
          onClick={() => onPageChange(current_page - 1)}
          disabled={current_page === 1}
          aria-label="Halaman sebelumnya"
          className={`${btnBase} ${current_page === 1 ? btnDisabled : btnInactive}`}
        >
          ←
        </button>

        {range[0] > 1 && (
          <>
            <button type="button" onClick={() => onPageChange(1)} className={`${btnBase} ${btnInactive}`} aria-label="Halaman 1">1</button>
            {range[0] > 2 && <span className="text-slate-600 text-sm px-1">…</span>}
          </>
        )}

        {range.map((page) => (
          <button
            key={page}
            type="button"
            onClick={() => onPageChange(page)}
            aria-label={`Halaman ${page}`}
            aria-current={page === current_page ? 'page' : undefined}
            className={`${btnBase} ${page === current_page ? btnActive : btnInactive}`}
          >
            {page}
          </button>
        ))}

        {range[range.length - 1] < last_page && (
          <>
            {range[range.length - 1] < last_page - 1 && (
              <span className="text-slate-600 text-sm px-1">…</span>
            )}
            <button
              type="button"
              onClick={() => onPageChange(last_page)}
              className={`${btnBase} ${btnInactive}`}
              aria-label={`Halaman ${last_page}`}
            >
              {last_page}
            </button>
          </>
        )}

        {/* Next */}
        <button
          type="button"
          onClick={() => onPageChange(current_page + 1)}
          disabled={current_page === last_page}
          aria-label="Halaman berikutnya"
          className={`${btnBase} ${current_page === last_page ? btnDisabled : btnInactive}`}
        >
          →
        </button>
      </div>
    </nav>
  );
};
