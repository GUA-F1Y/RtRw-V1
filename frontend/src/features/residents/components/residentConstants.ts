import type { ResidentStatus } from '../../../types/resident.ts';

/**
 * Canonical resident status presentation mapping.
 * Single source of truth — no component duplicates these labels or colors.
 */
export const RESIDENT_STATUS_MAP: Record<
  ResidentStatus,
  { label: string; variant: string; bg: string; text: string; border: string; dot: string }
> = {
  PROSPECTIVE: {
    label: 'Belum Terverifikasi',
    variant: 'amber',
    bg: 'bg-amber-950/50',
    text: 'text-amber-300',
    border: 'border-amber-700/50',
    dot: 'bg-amber-400',
  },
  ACTIVE: {
    label: 'Aktif',
    variant: 'emerald',
    bg: 'bg-emerald-950/50',
    text: 'text-emerald-300',
    border: 'border-emerald-700/50',
    dot: 'bg-emerald-400',
  },
  INACTIVE: {
    label: 'Tidak Aktif',
    variant: 'slate',
    bg: 'bg-slate-800/60',
    text: 'text-slate-400',
    border: 'border-slate-700/50',
    dot: 'bg-slate-500',
  },
  MOVED: {
    label: 'Pindah',
    variant: 'blue',
    bg: 'bg-blue-950/50',
    text: 'text-blue-300',
    border: 'border-blue-700/50',
    dot: 'bg-blue-400',
  },
  DECEASED: {
    label: 'Meninggal',
    variant: 'rose',
    bg: 'bg-rose-950/50',
    text: 'text-rose-300',
    border: 'border-rose-700/50',
    dot: 'bg-rose-400',
  },
};
