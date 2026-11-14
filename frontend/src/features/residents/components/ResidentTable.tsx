import React from 'react';
import { Link } from 'react-router-dom';
import type { Resident } from '../../../types/resident.ts';
import { ResidentStatusBadge } from './ResidentStatusBadge.tsx';

// ── Helpers ───────────────────────────────────────────────────────────────────

const GENDER_LABEL: Record<string, string> = { MALE: 'Laki-laki', FEMALE: 'Perempuan' };
const RESIDENCY_LABEL: Record<string, string> = {
  PERMANENT: 'Tetap',
  TEMPORARY: 'Sementara',
  SEASONAL: 'Musiman',
};

// ── Table Row (desktop) ───────────────────────────────────────────────────────

interface ResidentTableRowProps {
  resident: Resident;
}

export const ResidentTableRow: React.FC<ResidentTableRowProps> = ({ resident }) => (
  <tr className="border-b border-slate-800/60 hover:bg-slate-800/30 transition-colors group">
    {/* Name */}
    <td className="px-4 py-3">
      <div className="font-medium text-slate-100 group-hover:text-emerald-300 transition-colors leading-tight">
        {resident.name}
      </div>
      {resident.birth_date && (
        <div className="text-xs text-slate-500 mt-0.5">
          {new Date(resident.birth_date).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
          })}
        </div>
      )}
    </td>
    {/* Gender */}
    <td className="px-4 py-3 text-sm text-slate-400">
      {GENDER_LABEL[resident.gender] ?? resident.gender}
    </td>
    {/* Age */}
    <td className="px-4 py-3 text-sm text-slate-300 tabular-nums">
      {resident.age} th
    </td>
    {/* Status */}
    <td className="px-4 py-3">
      <ResidentStatusBadge status={resident.status} />
    </td>
    {/* Residency type */}
    <td className="px-4 py-3 text-sm text-slate-400">
      {RESIDENCY_LABEL[resident.residency_type] ?? resident.residency_type}
    </td>
    {/* RT */}
    <td className="px-4 py-3 text-sm text-slate-400 tabular-nums">
      RT {String(resident.rt_id).slice(-3)}
    </td>
    {/* Detail link */}
    <td className="px-4 py-3 text-right">
      <Link
        to={`/app/residents/${resident.id}`}
        className="text-xs font-medium text-emerald-400 hover:text-emerald-300 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-500 rounded"
        aria-label={`Lihat detail ${resident.name}`}
      >
        Detail →
      </Link>
    </td>
  </tr>
);

// ── Table (desktop) ───────────────────────────────────────────────────────────

interface ResidentTableProps {
  residents: Resident[];
}

export const ResidentTable: React.FC<ResidentTableProps> = ({ residents }) => (
  <div className="overflow-x-auto rounded-xl border border-slate-800/60 bg-slate-900/40">
    <table className="w-full text-left" aria-label="Daftar warga">
      <thead>
        <tr className="border-b border-slate-800/80 bg-slate-900/60">
          <th scope="col" className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
            Nama
          </th>
          <th scope="col" className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
            Kelamin
          </th>
          <th scope="col" className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
            Umur
          </th>
          <th scope="col" className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
            Status
          </th>
          <th scope="col" className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
            Jenis
          </th>
          <th scope="col" className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
            RT
          </th>
          <th scope="col" className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400 text-right">
            Aksi
          </th>
        </tr>
      </thead>
      <tbody>
        {residents.map((r) => (
          <ResidentTableRow key={r.id} resident={r} />
        ))}
      </tbody>
    </table>
  </div>
);

// ── Skeleton rows ─────────────────────────────────────────────────────────────

export function ResidentTableSkeleton({ rows = 8 }: { rows?: number }) {
  return (
    <div className="overflow-x-auto rounded-xl border border-slate-800/60 bg-slate-900/40 animate-pulse">
      <table className="w-full">
        <thead>
          <tr className="border-b border-slate-800/80 bg-slate-900/60">
            {['Nama', 'Kelamin', 'Umur', 'Status', 'Jenis', 'RT', ''].map((_h, i) => (
              <th key={i} className="px-4 py-3">
                <div className="h-3 bg-slate-800 rounded w-12" />
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {Array.from({ length: rows }).map((_, i) => (
            <tr key={i} className="border-b border-slate-800/40">
              <td className="px-4 py-3"><div className="h-4 bg-slate-800 rounded w-36" /></td>
              <td className="px-4 py-3"><div className="h-4 bg-slate-800 rounded w-16" /></td>
              <td className="px-4 py-3"><div className="h-4 bg-slate-800 rounded w-10" /></td>
              <td className="px-4 py-3"><div className="h-5 bg-slate-800 rounded-full w-24" /></td>
              <td className="px-4 py-3"><div className="h-4 bg-slate-800 rounded w-14" /></td>
              <td className="px-4 py-3"><div className="h-4 bg-slate-800 rounded w-10" /></td>
              <td className="px-4 py-3"><div className="h-4 bg-slate-800 rounded w-12 ml-auto" /></td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
