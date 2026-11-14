import React, { useEffect, useId, useMemo, useRef, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type { BudgetPlan, UpdateBudgetPlanPayload } from '../../../types/finance.ts';
import { useFinanceCategories, useUpdateBudgetPlan } from '../api/financeQueries.ts';
import { formatRupiah, parseIntegerRupiah } from '../utils/currency.ts';

interface EditBudgetPlanDialogProps {
  open: boolean;
  plan: BudgetPlan | null;
  onClose: () => void;
  onSuccess?: (plan: BudgetPlan) => void;
}

interface BudgetLineInput {
  category_id: string;
  planned_amount_input: string;
}

export const EditBudgetPlanDialog: React.FC<EditBudgetPlanDialogProps> = ({
  open,
  plan,
  onClose,
  onSuccess,
}) => {
  const notesId = useId();

  const { data: categoriesData } = useFinanceCategories(
    { type: 'EXPENSE', is_active: true },
    { enabled: open }
  );
  const categories = categoriesData?.data || [];

  const updateMutation = useUpdateBudgetPlan();
  const idempotencyKeyRef = useRef<string>(generateIdempotencyKey());

  const [notes, setNotes] = useState<string>('');
  const [lines, setLines] = useState<BudgetLineInput[]>([]);
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (open && plan) {
      setNotes(plan.notes || '');
      if (plan.lines && plan.lines.length > 0) {
        setLines(
          plan.lines.map((l) => ({
            category_id: l.category_id,
            planned_amount_input: String(l.planned_amount),
          }))
        );
      } else {
        setLines([{ category_id: '', planned_amount_input: '' }]);
      }
      setClientErrors({});
      idempotencyKeyRef.current = generateIdempotencyKey();
    }
  }, [open, plan]);

  const handleAddLine = () => {
    setLines((prev) => [...prev, { category_id: '', planned_amount_input: '' }]);
  };

  const handleRemoveLine = (index: number) => {
    setLines((prev) => prev.filter((_, i) => i !== index));
  };

  const handleLineChange = (index: number, field: keyof BudgetLineInput, value: string) => {
    setLines((prev) => {
      const copy = [...prev];
      copy[index] = { ...copy[index], [field]: value };
      return copy;
    });
  };

  const totalPlannedSum = useMemo(() => {
    return lines.reduce((acc, line) => {
      const res = parseIntegerRupiah(line.planned_amount_input, { allowZero: true });
      return acc + (res.valid ? (res.value ?? 0) : 0);
    }, 0);
  }, [lines]);

  const validate = (): boolean => {
    const errs: Record<string, string> = {};

    if (lines.length === 0) {
      errs.lines = 'Minimal harus memiliki 1 pos anggaran.';
    }

    const seenCategories = new Set<string>();
    lines.forEach((line, index) => {
      if (!line.category_id) {
        errs[`line_${index}_category`] = 'Pilih pos kategori belanja.';
      } else if (seenCategories.has(line.category_id)) {
        errs[`line_${index}_category`] = 'Pos kategori ini sudah dimasukkan.';
      } else {
        seenCategories.add(line.category_id);
      }

      const res = parseIntegerRupiah(line.planned_amount_input, { allowZero: true });
      if (!res.valid) {
        errs[`line_${index}_amount`] = res.error || 'Nominal harus berupa angka non-negatif.';
      }
    });

    setClientErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!plan) return;
    if (!validate()) return;

    const payload: UpdateBudgetPlanPayload = {
      notes: notes.trim() || null,
      lines: lines.map((l) => {
        const parsed = parseIntegerRupiah(l.planned_amount_input, { allowZero: true });
        return {
          category_id: l.category_id,
          planned_amount: parsed.valid ? (parsed.value ?? 0) : 0,
        };
      }),
    };

    try {
      const res = await updateMutation.mutateAsync({
        id: plan.id,
        payload,
        idempotencyKey: idempotencyKeyRef.current,
      });
      onSuccess?.(res.data);
      onClose();
    } catch {
      // Handled by mutation error state
    }
  };

  if (!plan) return null;

  const scopeLabel = plan.rt
    ? `RT ${plan.rt.number}`
    : `RW ${plan.rw?.number || ''}`;

  return (
    <Dialog open={open} onClose={onClose} title="Ubah Rencana Anggaran (Draft)" maxWidth="max-w-4xl">
      <form onSubmit={handleSubmit} className="space-y-5">
        {updateMutation.isError && (
          <Alert type="error" message={updateMutation.error?.message || 'Gagal mengubah rencana anggaran.'} />
        )}

        {/* Read-only Period & Scope info */}
        <div className="bg-slate-900/60 p-4 rounded-xl border border-slate-800 flex flex-wrap items-center justify-between gap-3 text-xs">
          <div>
            <span className="text-slate-400">Periode Anggaran:</span>{' '}
            <strong className="text-white font-mono">
              {String(plan.month).padStart(2, '0')}/{plan.year}
            </strong>
          </div>
          <div>
            <span className="text-slate-400">Tingkat:</span>{' '}
            <strong className="text-indigo-400">{scopeLabel}</strong>
          </div>
          <div>
            <span className="text-slate-400">Versi:</span>{' '}
            <strong className="text-white">v{plan.version}</strong>
          </div>
        </div>

        {/* Notes */}
        <div>
          <label htmlFor={notesId} className="block text-xs font-semibold text-slate-300 mb-1">
            Catatan / Justifikasi Anggaran
          </label>
          <textarea
            id={notesId}
            rows={2}
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            placeholder="Catatan tujuan atau latar belakang alokasi anggaran..."
            className="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500"
          />
        </div>

        {/* Line items section */}
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <h4 className="text-xs font-semibold text-slate-200">
              Pos Alokasi Belanja ({lines.length})
            </h4>
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={handleAddLine}
            >
              + Tambah Pos
            </Button>
          </div>

          {clientErrors.lines && <FieldError message={clientErrors.lines} />}

          <div className="space-y-2 max-h-72 overflow-y-auto pr-1">
            {lines.map((line, index) => {
              const catError = clientErrors[`line_${index}_category`];
              const amtError = clientErrors[`line_${index}_amount`];

              return (
                <div
                  key={index}
                  className="flex items-start gap-2 bg-slate-900/40 p-2.5 rounded-lg border border-slate-800/80"
                >
                  <div className="flex-1">
                    <select
                      value={line.category_id}
                      onChange={(e) => handleLineChange(index, 'category_id', e.target.value)}
                      className="w-full bg-slate-800 border border-slate-700 rounded-lg p-2 text-xs text-white focus:outline-none focus:border-indigo-500"
                    >
                      <option value="">-- Pilih Kategori Belanja --</option>
                      {categories.map((c) => (
                        <option key={c.id} value={c.id}>
                          {c.code} - {c.name}
                        </option>
                      ))}
                    </select>
                    {catError && <FieldError message={catError} />}
                  </div>

                  <div className="w-48">
                    <div className="relative">
                      <span className="absolute left-2.5 top-2 text-xs text-slate-400">Rp</span>
                      <input
                        type="text"
                        value={line.planned_amount_input}
                        onChange={(e) =>
                          handleLineChange(index, 'planned_amount_input', e.target.value)
                        }
                        placeholder="0"
                        className="w-full pl-8 pr-2.5 py-2 bg-slate-800 border border-slate-700 rounded-lg text-xs text-white font-mono text-right focus:outline-none focus:border-indigo-500"
                      />
                    </div>
                    {amtError && <FieldError message={amtError} />}
                  </div>

                  <button
                    type="button"
                    onClick={() => handleRemoveLine(index)}
                    disabled={lines.length === 1}
                    aria-label={`Hapus pos anggaran baris ${index + 1}`}
                    className="p-2 text-slate-400 hover:text-rose-400 disabled:opacity-30 disabled:hover:text-slate-400 transition-colors"
                  >
                    ✕
                  </button>
                </div>
              );
            })}
          </div>

          {/* Sum footer */}
          <div className="flex items-center justify-between p-3 bg-slate-900/80 rounded-lg border border-slate-700/80 text-xs">
            <span className="font-semibold text-slate-300">Total Rencana Anggaran:</span>
            <span className="font-mono font-bold text-sm text-emerald-400">
              {formatRupiah(totalPlannedSum)}
            </span>
          </div>
        </div>

        <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
          <Button
            type="button"
            variant="secondary"
            onClick={onClose}
            disabled={updateMutation.isPending}
          >
            Batal
          </Button>
          <Button
            type="submit"
            variant="primary"
            disabled={updateMutation.isPending}
          >
            {updateMutation.isPending ? 'Menyimpan...' : 'Simpan Perubahan'}
          </Button>
        </div>
      </form>
    </Dialog>
  );
};
