import React from 'react';
import type { Resident } from '../../../types/resident.ts';

// ── Helpers ───────────────────────────────────────────────────────────────────

const GENDER_LABEL: Record<string, string> = { MALE: 'Laki-laki', FEMALE: 'Perempuan' };
const RESIDENCY_LABEL: Record<string, string> = {
  PERMANENT: 'Tetap',
  TEMPORARY: 'Sementara',
  SEASONAL: 'Musiman',
};
const MARITAL_LABEL: Record<string, string> = {
  SINGLE: 'Belum Menikah',
  MARRIED: 'Menikah',
  DIVORCED: 'Cerai Hidup',
  WIDOWED: 'Cerai Mati',
};
const EDUCATION_LABEL: Record<string, string> = {
  SD: 'SD',
  SMP: 'SMP',
  SMA: 'SMA/SMK',
  DIPLOMA: 'Diploma',
  SARJANA: 'Sarjana (S1)',
  MAGISTER: 'Magister (S2)',
  DOKTOR: 'Doktor (S3)',
  OTHER: 'Lainnya',
};

// ── Field Row ─────────────────────────────────────────────────────────────────

interface FieldRowProps {
  label: string;
  value?: string | number | null;
  isSensitive?: boolean;
}

const FieldRow: React.FC<FieldRowProps> = ({ label, value, isSensitive = false }) => {
  if (value === undefined || value === null || value === '') return null;
  return (
    <div className="flex flex-col sm:flex-row sm:items-baseline gap-0.5 sm:gap-4 py-2.5 border-b border-slate-800/40 last:border-0">
      <dt className="text-xs font-semibold uppercase tracking-wider text-slate-500 sm:w-44 flex-shrink-0">
        {label}
      </dt>
      <dd
        className={`text-sm ${isSensitive ? 'font-mono text-slate-300' : 'text-slate-200'}`}
        aria-label={`${label}: ${value}`}
      >
        {String(value)}
      </dd>
    </div>
  );
};

// ── Identity Section ──────────────────────────────────────────────────────────

interface ResidentIdentitySectionProps {
  resident: Resident;
}

export const ResidentIdentitySection: React.FC<ResidentIdentitySectionProps> = ({ resident }) => {
  const birthDate = resident.birth_date
    ? new Date(resident.birth_date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      })
    : undefined;

  return (
    <section aria-labelledby="section-identity" className="space-y-1">
      <h2 id="section-identity" className="text-sm font-bold uppercase tracking-widest text-slate-400 pb-2">
        Informasi Dasar
      </h2>
      <dl>
        <FieldRow label="Nama Lengkap" value={resident.name} />
        <FieldRow label="Jenis Kelamin" value={GENDER_LABEL[resident.gender] ?? resident.gender} />
        <FieldRow label="Umur" value={`${resident.age} tahun`} />
        <FieldRow label="Tempat Lahir" value={resident.birth_place} />
        <FieldRow label="Tanggal Lahir" value={birthDate} />
        <FieldRow label="Status Nikah" value={resident.marital_status ? MARITAL_LABEL[resident.marital_status] ?? resident.marital_status : undefined} />
        <FieldRow label="Pekerjaan" value={resident.occupation ?? undefined} />
        <FieldRow label="Pendidikan" value={resident.education ? EDUCATION_LABEL[resident.education] ?? resident.education : undefined} />
        <FieldRow label="Jenis Tinggal" value={RESIDENCY_LABEL[resident.residency_type] ?? resident.residency_type} />
        {/* NIK — shown as-is from server (may be masked) */}
        {resident.nik && (
          <FieldRow label="NIK" value={resident.nik} isSensitive />
        )}
        {/* Phone — shown as-is from server (may be masked) */}
        {resident.phone && (
          <FieldRow label="Telepon" value={resident.phone} isSensitive />
        )}
        {/* Sensitive fields only if server provides them */}
        {resident.religion && (
          <FieldRow label="Agama" value={resident.religion} />
        )}
        {resident.blood_type && (
          <FieldRow label="Gol. Darah" value={resident.blood_type} />
        )}
      </dl>
    </section>
  );
};
