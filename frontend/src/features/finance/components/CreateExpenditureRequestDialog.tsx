import React, { useEffect, useId, useRef, useState } from 'react';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import { Dialog } from '../../../components/ui/Dialog.tsx';
import { FieldError } from '../../../components/ui/FieldError.tsx';
import { generateIdempotencyKey } from '../../../lib/api/idempotency.ts';
import type { BudgetLine, BudgetPlan, CreateExpenditureRequestPayload } from '../../../types/finance.ts';
import { useCashAccounts, useCreateExpenditureRequest } from '../api/financeQueries.ts';
import { formatRupiah, parseIntegerRupiah } from '../utils/currency.ts';

interface CreateExpenditureRequestDialogProps {
  open: boolean;
  plan?: BudgetPlan | null;
  initialLine?: BudgetLine | null;
  onClose: () => void;
  onSuccess?: (requestId: string) => void;
}

export const CreateExpenditureRequestDialog: React.FC<CreateExpenditureRequestDialogProps> = ({
  open,
  plan,
  initialLine,
  onClose,
  onSuccess,
}) => {
  const lineIdField = useId();
  const accountIdField = useId();
  const amountIdField = useId();
  const dateIdField = useId();
  const titleIdField = useId();
  const descIdField = useId();

  const { data: accountsData } = useCashAccounts({ status: 'active' }, { enabled: open });
  const accounts = accountsData?.data || [];

  const createMutation = useCreateExpenditureRequest();
  const idempotencyKeyRef = useRef<string>(generateIdempotencyKey());

  const [selectedLineId, setSelectedLineId] = useState<string>('');
  const [selectedAccountId, setSelectedAccountId] = useState<string>('');
  const [amountInput, setAmountInput] = useState<string>('');
  const [plannedDate, setPlannedDate] = useState<string>('');
  const [title, setTitle] = useState<string>('');
  const [description, setDescription] = useState<string>('');
  const [referenceNumber, setReferenceNumber] = useState<string>('');
  const [submitImmediately, setSubmitImmediately] = useState<boolean>(true);
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  const availableLines = plan?.lines || (initialLine ? [initialLine] : []);

  useEffect(() => {
    if (open) {
      const defaultLine = initialLine || (availableLines.length > 0 ? availableLines[0] : null);
      setSelectedLineId(defaultLine?.id || '');
      setSelectedAccountId(accounts.length > 0 ? accounts[0].id : '');
      setAmountInput('');
      setTitle('');
      setDescription('');
      setReferenceNumber('');
      setSubmitImmediately(true);
      setClientErrors({});
      idempotencyKeyRef.current = generateIdempotencyKey();

      if (plan) {
        const y = plan.year;
        const m = String(plan.month).padStart(2, '0');
        setPlannedDate(`${y}-${m}-15`);
      } else {
        const today = new Date().toISOString().slice(0, 10);
        setPlannedDate(today);
      }
    }
  }, [open, initialLine, plan, accounts, availableLines]);

  const activeLine = availableLines.find((l) => l.id === selectedLineId);
  const remainingBudget = activeLine
    ? (activeLine.remaining_budget ?? (activeLine.planned_amount - (activeLine.effective_actual_expenses ?? 0) - (activeLine.outstanding_commitments ?? 0)))
    : 0;

  const amountParsed = parseIntegerRupiah(amountInput);
  const parsedAmount = amountParsed.valid ? (amountParsed.value ?? 0) : 0;

  const validate = (): boolean => {
    const errs: Record<string, string> = {};

    if (!selectedLineId) {
      errs.budget_line_id = 'Pilih pos anggaran.';
    }

    if (!selectedAccountId) {
      errs.cash_account_id = 'Pilih rekening kas pengeluaran.';
    }

    if (!amountParsed.valid || parsedAmount <= 0) {
      errs.proposed_amount = amountParsed.error || 'Nominal harus lebih dari 0.';
    } else if (parsedAmount > remainingBudget) {
      errs.proposed_amount = `Nominal melebihi sisa pagu tersedia (${formatRupiah(remainingBudget)}).`;
    }

    if (!plannedDate) {
      errs.planned_date = 'Tanggal rencana belanja wajib diisi.';
    } else if (plan) {
      const [pYear, pMonth] = plannedDate.split('-').map(Number);
      if (pYear !== plan.year || pMonth !== plan.month) {
        errs.planned_date = `Tanggal harus berada dalam periode anggaran (${plan.month}/${plan.year}).`;
      }
    }

    if (!title.trim() || title.trim().length < 3) {
      errs.title = 'Judul keperluan belanja minimal 3 karakter.';
    }

    setClientErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validate()) return;

    const payload: CreateExpenditureRequestPayload = {
      budget_line_id: selectedLineId,
      cash_account_id: selectedAccountId,
      proposed_amount: parsedAmount,
      planned_date: plannedDate,
      title: title.trim(),
      description: description.trim() || null,
      reference_number: referenceNumber.trim() || null,
      submit: submitImmediately,
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
    <Dialog open={open} onClose={onClose} title="Buat Pengajuan Belanja (Expenditure Request)" maxWidth="max-w-2xl">
      <form onSubmit={handleSubmit} className="space-y-4">
        {createMutation.isError && (
          <Alert type="error" message={createMutation.error?.message || 'Gagal membuat pengajuan belanja.'} />
        )}

        {/* Pos Anggaran Selection */}
        <div>
          <label htmlFor={lineIdField} className="block text-xs font-semibold text-slate-300 mb-1">
            Pilih Pos Anggaran (Budget Line) *
          </label>
          <select
            id={lineIdField}
            value={selectedLineId}
            onChange={(e) => setSelectedLineId(e.target.value)}
            className="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
          >
            <option value="">-- Pilih Pos Anggaran --</option>
            {availableLines.map((l) => (
              <option key={l.id} value={l.id}>
                [{l.category?.code || 'EXP'}] {l.category?.name || 'Kategori'} (Pagu: {formatRupiah(l.planned_amount)})
              </option>
            ))}
          </select>
          {clientErrors.budget_line_id && <FieldError message={clientErrors.budget_line_id} />}

          {activeLine && (
            <div className="mt-2 bg-slate-900/60 border border-slate-800 rounded-lg p-2.5 text-xs flex items-center justify-between">
              <span className="text-slate-400">Sisa Pagu Tersedia Saat Ini:</span>
              <span className={`font-mono font-bold ${remainingBudget <= 0 ? 'text-rose-400' : 'text-emerald-400'}`}>
                {formatRupiah(remainingBudget)}
              </span>
            </div>
          )}
        </div>

        {/* Cash Account Selection */}
        <div>
          <label htmlFor={accountIdField} className="block text-xs font-semibold text-slate-300 mb-1">
            Rekening Kas Sumber Dana *
          </label>
          <select
            id={accountIdField}
            value={selectedAccountId}
            onChange={(e) => setSelectedAccountId(e.target.value)}
            className="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
          >
            <option value="">-- Pilih Rekening Kas --</option>
            {accounts.map((acc) => (
              <option key={acc.id} value={acc.id}>
                {acc.name} ({acc.account_number || 'Tanpa No'}) - Saldo: {formatRupiah(acc.current_balance)}
              </option>
            ))}
          </select>
          {clientErrors.cash_account_id && <FieldError message={clientErrors.cash_account_id} />}
        </div>

        {/* Proposed Amount & Planned Date */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label htmlFor={amountIdField} className="block text-xs font-semibold text-slate-300 mb-1">
              Nominal Diajukan (Rupiah) *
            </label>
            <div className="relative">
              <span className="absolute left-3 top-2 text-xs text-slate-400">Rp</span>
              <input
                id={amountIdField}
                type="text"
                placeholder="0"
                value={amountInput}
                onChange={(e) => setAmountInput(e.target.value)}
                className="w-full bg-slate-800 border border-slate-700 rounded-lg pl-8 pr-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-emerald-500"
              />
            </div>
            {clientErrors.proposed_amount && <FieldError message={clientErrors.proposed_amount} />}
          </div>

          <div>
            <label htmlFor={dateIdField} className="block text-xs font-semibold text-slate-300 mb-1">
              Tanggal Rencana Belanja *
            </label>
            <input
              id={dateIdField}
              type="date"
              value={plannedDate}
              onChange={(e) => setPlannedDate(e.target.value)}
              className="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
            />
            {clientErrors.planned_date && <FieldError message={clientErrors.planned_date} />}
          </div>
        </div>

        {/* Title & Reference */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div className="sm:col-span-2">
            <label htmlFor={titleIdField} className="block text-xs font-semibold text-slate-300 mb-1">
              Keperluan / Judul Pengajuan *
            </label>
            <input
              id={titleIdField}
              type="text"
              placeholder="Contoh: Pengadaan 10 karung semen perbaikan selokan"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              className="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
            />
            {clientErrors.title && <FieldError message={clientErrors.title} />}
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1">
              No. Referensi / Nota (Opsional)
            </label>
            <input
              type="text"
              placeholder="Contoh: REQ-2026-001"
              value={referenceNumber}
              onChange={(e) => setReferenceNumber(e.target.value)}
              className="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-emerald-500"
            />
          </div>
        </div>

        {/* Description */}
        <div>
          <label htmlFor={descIdField} className="block text-xs font-semibold text-slate-300 mb-1">
            Keterangan & Rincian Pengeluaran (Opsional)
          </label>
          <textarea
            id={descIdField}
            rows={2}
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder="Jelaskan kebutuhan pembelian, vendor yang digunakan, atau spesifikasi barang..."
            className="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-xs text-white focus:outline-none focus:border-emerald-500"
          />
        </div>

        {/* Submit Immediately Toggle */}
        <div className="bg-slate-900/60 border border-slate-800 rounded-lg p-3 flex items-center justify-between">
          <div>
            <p className="text-xs font-semibold text-white">Ajukan Langsung (SUBMITTED)</p>
            <p className="text-[11px] text-slate-400 mt-0.5">
              Jika aktif, pengajuan langsung masuk antrean persetujuan Ketua RW / RT.
            </p>
          </div>
          <input
            type="checkbox"
            checked={submitImmediately}
            onChange={(e) => setSubmitImmediately(e.target.checked)}
            className="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 bg-slate-800 border-slate-700"
          />
        </div>

        {/* Actions */}
        <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
          <Button type="button" variant="secondary" onClick={onClose} disabled={createMutation.isPending}>
            Batal
          </Button>
          <Button type="submit" variant="primary" disabled={createMutation.isPending}>
            {createMutation.isPending
              ? 'Menyimpan...'
              : submitImmediately
                ? 'Simpan & Ajukan Persetujuan'
                : 'Simpan Sebagai Draf'}
          </Button>
        </div>
      </form>
    </Dialog>
  );
};
