import React from 'react';
import type { ResidentStatus, ResidencyType, ResidentGender, ResidentMaritalStatus } from '../../../types/resident.ts';

// ── Types ─────────────────────────────────────────────────────────────────────

export interface ResidentFilterState {
  q: string;
  status: ResidentStatus | '';
  gender: ResidentGender | '';
  residency_type: ResidencyType | '';
  marital_status: ResidentMaritalStatus | '';
  sort: 'name' | '-name' | 'created_at' | '-created_at' | 'status' | '-status' | '';
}

// ── Select helper ─────────────────────────────────────────────────────────────

interface FilterSelectProps {
  id: string;
  label: string;
  value: string;
  onChange: (val: string) => void;
  options: { value: string; label: string }[];
}

const FilterSelect: React.FC<FilterSelectProps> = ({ id, label, value, onChange, options }) => (
  <div className="flex flex-col gap-1">
    <label htmlFor={id} className="text-xs font-semibold text-slate-400 uppercase tracking-wider">
      {label}
    </label>
    <select
      id={id}
      value={value}
      onChange={(e) => onChange(e.target.value)}
      className="bg-slate-900/80 border border-slate-700/60 text-slate-200 text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-600/60 focus:border-emerald-700 transition-colors"
    >
      {options.map((opt) => (
        <option key={opt.value} value={opt.value}>
          {opt.label}
        </option>
      ))}
    </select>
  </div>
);

// ── Main Filter Component ─────────────────────────────────────────────────────

interface ResidentFiltersProps {
  filters: ResidentFilterState;
  searchInput: string;
  isFiltersOpen: boolean;
  isLoading: boolean;
  onSearchChange: (val: string) => void;
  onFilterChange: (key: keyof ResidentFilterState, val: string) => void;
  onToggleFilters: () => void;
  onReset: () => void;
}

const STATUS_OPTIONS = [
  { value: '', label: 'Semua Status' },
  { value: 'PROSPECTIVE', label: 'Belum Terverifikasi' },
  { value: 'ACTIVE', label: 'Aktif' },
  { value: 'INACTIVE', label: 'Tidak Aktif' },
  { value: 'MOVED', label: 'Pindah' },
  { value: 'DECEASED', label: 'Meninggal' },
];

const GENDER_OPTIONS = [
  { value: '', label: 'Semua Kelamin' },
  { value: 'MALE', label: 'Laki-laki' },
  { value: 'FEMALE', label: 'Perempuan' },
];

const RESIDENCY_OPTIONS = [
  { value: '', label: 'Semua Jenis Tinggal' },
  { value: 'PERMANENT', label: 'Tetap' },
  { value: 'TEMPORARY', label: 'Sementara' },
  { value: 'SEASONAL', label: 'Musiman' },
];

const MARITAL_OPTIONS = [
  { value: '', label: 'Semua Status Nikah' },
  { value: 'SINGLE', label: 'Belum Menikah' },
  { value: 'MARRIED', label: 'Menikah' },
  { value: 'DIVORCED', label: 'Cerai Hidup' },
  { value: 'WIDOWED', label: 'Cerai Mati' },
];

const SORT_OPTIONS = [
  { value: '', label: 'Urutan Default' },
  { value: 'name', label: 'Nama A–Z' },
  { value: '-name', label: 'Nama Z–A' },
  { value: '-created_at', label: 'Terbaru' },
  { value: 'created_at', label: 'Terlama' },
  { value: 'status', label: 'Status A–Z' },
];

const hasActiveFilters = (f: ResidentFilterState) =>
  f.status !== '' || f.gender !== '' || f.residency_type !== '' || f.marital_status !== '' || f.sort !== '';

export const ResidentFilters: React.FC<ResidentFiltersProps> = ({
  filters,
  searchInput,
  isFiltersOpen,
  isLoading,
  onSearchChange,
  onFilterChange,
  onToggleFilters,
  onReset,
}) => {
  const activeFilterCount = [filters.status, filters.gender, filters.residency_type, filters.marital_status].filter(
    Boolean
  ).length;

  return (
    <div className="space-y-3">
      {/* Search + filter toggle row */}
      <div className="flex gap-2">
        {/* Search field */}
        <div className="flex-1 relative">
          <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" aria-hidden="true">
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </span>
          <input
            id="resident-search"
            type="search"
            role="searchbox"
            aria-label="Cari warga berdasarkan nama"
            placeholder="Cari nama warga…"
            value={searchInput}
            onChange={(e) => onSearchChange(e.target.value)}
            className="w-full bg-slate-900/80 border border-slate-700/60 text-slate-200 text-sm rounded-lg pl-9 pr-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-600/60 focus:border-emerald-700 placeholder-slate-600 transition-colors"
          />
          {isLoading && (
            <span className="absolute right-3 top-1/2 -translate-y-1/2" aria-label="Memuat...">
              <svg className="w-4 h-4 animate-spin text-emerald-400" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
            </span>
          )}
        </div>

        {/* Filter toggle */}
        <button
          type="button"
          id="resident-filter-toggle"
          onClick={onToggleFilters}
          aria-expanded={isFiltersOpen}
          aria-controls="resident-filter-panel"
          className={`flex items-center gap-2 px-3 py-2.5 rounded-lg border text-sm font-medium transition-all focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-500 ${
            isFiltersOpen || activeFilterCount > 0
              ? 'bg-emerald-950/60 border-emerald-700/60 text-emerald-300'
              : 'bg-slate-900/60 border-slate-700/60 text-slate-400 hover:text-slate-300 hover:border-slate-600'
          }`}
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z" />
          </svg>
          <span className="hidden sm:inline">Filter</span>
          {activeFilterCount > 0 && (
            <span className="bg-emerald-500 text-white text-xs font-bold rounded-full w-4 h-4 flex items-center justify-center">
              {activeFilterCount}
            </span>
          )}
        </button>

        {/* Reset */}
        {(hasActiveFilters(filters) || searchInput) && (
          <button
            type="button"
            onClick={onReset}
            className="px-3 py-2.5 rounded-lg border border-slate-700/60 text-sm text-slate-500 hover:text-slate-300 hover:border-slate-600 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-500"
            aria-label="Reset semua filter"
            title="Reset filter"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        )}
      </div>

      {/* Expandable filter panel */}
      {isFiltersOpen && (
        <div
          id="resident-filter-panel"
          role="group"
          aria-label="Panel filter warga"
          className="grid grid-cols-2 sm:grid-cols-4 gap-3 p-4 rounded-xl border border-slate-800/60 bg-slate-900/50"
        >
          <FilterSelect
            id="filter-status"
            label="Status"
            value={filters.status}
            onChange={(v) => onFilterChange('status', v)}
            options={STATUS_OPTIONS}
          />
          <FilterSelect
            id="filter-gender"
            label="Kelamin"
            value={filters.gender}
            onChange={(v) => onFilterChange('gender', v)}
            options={GENDER_OPTIONS}
          />
          <FilterSelect
            id="filter-residency"
            label="Jenis Tinggal"
            value={filters.residency_type}
            onChange={(v) => onFilterChange('residency_type', v)}
            options={RESIDENCY_OPTIONS}
          />
          <FilterSelect
            id="filter-marital"
            label="Status Nikah"
            value={filters.marital_status}
            onChange={(v) => onFilterChange('marital_status', v)}
            options={MARITAL_OPTIONS}
          />
          <div className="col-span-2 sm:col-span-4">
            <FilterSelect
              id="filter-sort"
              label="Urutan"
              value={filters.sort}
              onChange={(v) => onFilterChange('sort', v)}
              options={SORT_OPTIONS}
            />
          </div>
        </div>
      )}
    </div>
  );
};
