import type { HouseholdStatus } from '../../../types/household.ts';

/**
 * Canonical household status presentation mapping.
 * Single source of truth — labels, colors, and dot for all status values.
 */
export const HOUSEHOLD_STATUS_MAP: Record<
  HouseholdStatus,
  { label: string; bg: string; text: string; border: string; dot: string }
> = {
  ACTIVE: {
    label: 'Aktif',
    bg: 'bg-emerald-950/50',
    text: 'text-emerald-300',
    border: 'border-emerald-700/50',
    dot: 'bg-emerald-400',
  },
  HEAD_VACANT: {
    label: 'Kepala Keluarga Kosong',
    bg: 'bg-amber-950/50',
    text: 'text-amber-300',
    border: 'border-amber-700/50',
    dot: 'bg-amber-400',
  },
  MOVED: {
    label: 'Pindah',
    bg: 'bg-blue-950/50',
    text: 'text-blue-300',
    border: 'border-blue-700/50',
    dot: 'bg-blue-400',
  },
  DISSOLVED: {
    label: 'Dibubarkan',
    bg: 'bg-rose-950/50',
    text: 'text-rose-300',
    border: 'border-rose-700/50',
    dot: 'bg-rose-400',
  },
  ARCHIVED: {
    label: 'Diarsipkan',
    bg: 'bg-slate-800/60',
    text: 'text-slate-400',
    border: 'border-slate-700/50',
    dot: 'bg-slate-500',
  },
};

/**
 * Relationship label mapping (Indonesian).
 */
export const RELATIONSHIP_LABEL: Record<string, string> = {
  KEPALA_KELUARGA: 'Kepala Keluarga',
  SUAMI: 'Suami',
  ISTRI: 'Istri',
  ANAK: 'Anak',
  MENANTU: 'Menantu',
  CUCU: 'Cucu',
  ORANG_TUA: 'Orang Tua',
  MERTUA: 'Mertua',
  FAMILI_LAIN: 'Famili Lain',
  PEMBANTU: 'Pembantu',
  LAINNYA: 'Lainnya',
};
