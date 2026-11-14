import React, { useEffect, useId, useMemo, useRef, useState } from 'react';
import { useAuth } from '../../../app/providers/useAuth.ts';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type { CreateBudgetPlanPayload } from '../../../types/finance.ts';
import { useRwWithRts } from '../../hierarchy/api/hierarchyQueries.ts';
import { useCreateBudgetPlan, useFinanceCategories } from '../api/financeQueries.ts';
import { formatRupiah, parseIntegerRupiah } from '../utils/currency.ts';

interface CreateBudgetPlanDialogProps {
  open: boolean;
  onClose: () => void;
  onSuccess?: (budgetId: string) => void;
}

interface BudgetLineInput {
  category_id: string;
  planned_amount_input: string;
}

const CURRENT_YEAR = new Date().getFullYear();
const CURRENT_MONTH = new Date().getMonth() + 1;

export const CreateBudgetPlanDialog: React.FC<CreateBudgetPlanDialogProps> = ({
  open,
  onClose,
  onSuccess,
}) => {
  const { activeScope } = useAuth();
  const yearId = useId();
  const monthId = useId();
  const scopeId = useId();
  const notesId = useId();

  const isRtUser = activeScope?.scopeLevel === 'rt';
  const rwId = activeScope?.rw?.id;
  const { data: rwData } = useRwWithRts(open && rwId ? rwId : null);

  const { data: categoriesData } = useFinanceCategories(
    { type: 'EXPENSE', is_active: true },
    { enabled: open }
  );
  const categories = categoriesData?.data || [];

  const createMutation = useCreateBudgetPlan();
  const idempotencyKeyRef = useRef<string>(generateIdempotencyKey());

  const [year, setYear] = useState<number>(CURRENT_YEAR);
  const [month, setMonth] = useState<number>(CURRENT_MONTH);
  const [scopeType, setScopeType] = useState<'RW' | 'RT'>(isRtUser ? 'RT' : 'RW');
  const [rtId, setRtId] = useState<string>(isRtUser && activeScope?.rt?.id ? activeScope.rt.id : '');
  const [notes, setNotes] = useState<string>('');
  const [lines, setLines] = useState<BudgetLineInput[]>([
    { category_id: '', planned_amount_input: '' },
  ]);
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (open) {
      setYear(CURRENT_YEAR);
      setMonth(CURRENT_MONTH);
      setScopeType(isRtUser ? 'RT' : 'RW');
      setRtId(isRtUser && activeScope?.rt?.id ? activeScope.rt.id : '');
      setNotes('');
      setLines([{ category_id: '', planned_amount_input: '' }]);
      setClientErrors({});
      idempotencyKeyRef.current = generateIdempotencyKey();
    }
  }, [open, isRtUser, activeScope]);

  const rtUnits = rwData?.rts || [];

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

    if (!year || year < 2020 || year > 2100) {
      errs.year = 'Tahun anggaran tidak valid.';
    }

    if (!month || month < 1 || month > 12) {
      errs.month = 'Bulan anggaran tidak valid (1-12).';
    }

    if (scopeType === 'RT' && !rtId) {
      errs.rt_id = 'Pilih RT unit untuk anggaran tingkat RT.';
    }

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
    if (!validate()) return;

    const payload: CreateBudgetPlanPayload = {
      year,
      month,
      rt_id: scopeType === 'RT' ? rtId : null,
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
      const res = await createMutation.mutateAsync({
        payload,
        idempotencyKey: idempotencyKeyRef.current,
      });
      onSuccess?.(res.data.id);
      onClose();
    } catch {
      // Handled by mutation error state
    }
  };

  return (
    <Dialog open={open} onClose={onClose} title="Buat Rencana Anggaran (RAPB)" maxWidth="max-w-4xl">
      <form onSubmit={handleSubmit} className="space-y-5">
        {createMutation.isError && (
          <Alert type="error" message={createMutation.error?.message || 'Gagal membuat rencana anggaran.'} />
        )}

        {/* Period & Scope Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-slate-900/60 p-4 rounded-xl border border-slate-800">
          <div>
            <label htmlFor={yearId} className="block text-xs font-semibold text-slate-300 mb-1">
              Tahun Anggaran *
            </label>
            <input
              id={yearId}
              type="number"
              min="2020"
              max="2100"
              value={year}
              onChange={(e) => setYear(parseInt(e.target.value, 10) || CURRENT_YEAR)}
              className="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-emerald-500"
            />
            {clientErrors.year && <FieldError message={clientErrors.year} />}
          </div>

          <div>
            <label htmlFor={monthId} className="block text-xs font-semibold text-slate-300 mb-1">
              Bulan Anggaran *
            </label>
            <select
              id={monthId}
              value={month}
              onChange={(e) => setMonth(parseInt(e.target.value, 10))}
              className="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-emerald-500"
            >
              {[
                'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
              ].map((name, i) => (
                <option key={i + 1} value={i + 1}>
                  {name}
                </option>
              ))}
            </select>
            {clientErrors.month && <FieldError message={clientErrors.month} />}
          </div>

          <div>
            <label htmlFor={scopeId} className="block text-xs font-semibold text-slate-300 mb-1">
              Tingkat Cakupan *
            </label>
            {isRtUser ? (
              <input
                type="text"
                disabled
                value={`RT ${activeScope?.rt?.number || ''}`}
                className="w-full bg-slate-800/50 border border-slate-700/50 rounded-lg px-3 py-2 text-sm text-slate-400"
              />
            ) : (
              <div className="space-y-2">
                <select
                  id={scopeId}
                  value={scopeType}
                  onChange={(e) => setScopeType(e.target.value as 'RW' | 'RT')}
                  className="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-emerald-500"
                >
                  <option value="RW">Tingkat RW (Kolektif)</option>
                  <option value="RT">Tingkat RT Tertentu</option>
                </select>
                {scopeType === 'RT' && (
                  <select
                    value={rtId}
                    onChange={(e) => setRtId(e.target.value)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-emerald-500"
                  >
                    <option value="">-- Pilih RT --</option>
                    {rtUnits.map((rt: { id: string; number: number; name?: string }) => (
                      <option key={rt.id} value={rt.id}>
                        RT {rt.number} {rt.name ? `- ${rt.name}` : ''}
                      </option>
                    ))}
                  </select>
                )}
              </div>
            )}
            {clientErrors.rt_id && <FieldError message={clientErrors.rt_id} />}
          </div>
        </div>

        {/* Dynamic Budget Lines */}
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-semibold text-white">Rincian Pos Anggaran Belanja (Expense)</h3>
            <span className="text-xs text-slate-400">Total: {lines.length} Pos</span>
          </div>

          {clientErrors.lines && <FieldError message={clientErrors.lines} />}

          <div className="space-y-2.5 max-h-72 overflow-y-auto pr-1">
            {lines.map((line, idx) => (
              <div
                key={idx}
                className="flex items-start gap-2.5 bg-slate-900/40 p-2.5 rounded-lg border border-slate-800"
              >
                <div className="flex-1">
                  <select
                    value={line.category_id}
                    onChange={(e) => handleLineChange(idx, 'category_id', e.target.value)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
                  >
                    <option value="">-- Pilih Kategori Pengeluaran --</option>
                    {categories.map((cat) => (
                      <option key={cat.id} value={cat.id}>
                        [{cat.code}] {cat.name}
                      </option>
                    ))}
                  </select>
                  {clientErrors[`line_${idx}_category`] && (
                    <FieldError message={clientErrors[`line_${idx}_category`]} />
                  )}
                </div>

                <div className="w-48">
                  <div className="relative">
                    <span className="absolute left-3 top-2 text-xs text-slate-400">Rp</span>
                    <input
                      type="text"
                      placeholder="0"
                      value={line.planned_amount_input}
                      onChange={(e) => handleLineChange(idx, 'planned_amount_input', e.target.value)}
                      className="w-full bg-slate-800 border border-slate-700 rounded-lg pl-8 pr-3 py-2 text-xs text-white text-right font-mono focus:outline-none focus:border-emerald-500"
                    />
                  </div>
                  {clientErrors[`line_${idx}_amount`] && (
                    <FieldError message={clientErrors[`line_${idx}_amount`]} />
                  )}
                </div>

                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => handleRemoveLine(idx)}
                  disabled={lines.length === 1}
                  className="text-slate-400 hover:text-rose-400 p-2"
                >
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </Button>
              </div>
            ))}
          </div>

          <div className="flex items-center justify-between pt-2">
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={handleAddLine}
              className="text-xs"
            >
              + Tambah Pos Belanja
            </Button>

            <div className="text-right">
              <span className="text-xs text-slate-400 mr-2">Total Rencana Pagu:</span>
              <span className="text-base font-bold text-emerald-400 font-mono">
                {formatRupiah(totalPlannedSum)}
              </span>
            </div>
          </div>
        </div>

        {/* Notes */}
        <div>
          <label htmlFor={notesId} className="block text-xs font-semibold text-slate-300 mb-1">
            Catatan / Justifikasi Anggaran (Opsional)
          </label>
          <textarea
            id={notesId}
            rows={2}
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            placeholder="Keterangan singkat mengenai dasar penyusunan anggaran periode ini..."
            className="w-full bg-slate-800 border border-slate-700 rounded-lg p-3 text-xs text-white focus:outline-none focus:border-emerald-500"
          />
        </div>

        {/* Actions */}
        <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
          <Button type="button" variant="secondary" onClick={onClose} disabled={createMutation.isPending}>
            Batal
          </Button>
          <Button type="submit" variant="primary" disabled={createMutation.isPending}>
            {createMutation.isPending ? 'Menyimpan...' : 'Simpan Rencana (Draft)'}
          </Button>
        </div>
      </form>
    </Dialog>
  );
};
