import { Button } from '../../../components/ui/Button.tsx';
import type { BudgetLine } from '../../../types/finance.ts';
import { formatRupiah } from '../utils/currency.ts';

interface BudgetLinesTableProps {
  lines: BudgetLine[];
  onAddExpenditure?: (line: BudgetLine) => void;
  canRequest?: boolean;
  isPublished?: boolean;
}

export const BudgetLinesTable: React.FC<BudgetLinesTableProps> = ({
  lines,
  onAddExpenditure,
  canRequest = false,
  isPublished = false,
}) => {
  if (lines.length === 0) {
    return (
      <div className="text-center py-10 bg-slate-900/40 rounded-xl border border-slate-800 text-slate-400">
        <p className="text-sm">Belum ada pos anggaran dalam rencana ini.</p>
      </div>
    );
  }

  return (
    <div className="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900/60 shadow-lg">
      <table className="w-full text-left text-xs text-slate-300">
        <thead className="bg-slate-800/80 text-[11px] font-semibold uppercase tracking-wider text-slate-400 border-b border-slate-700">
          <tr>
            <th className="px-4 py-3">Pos Kategori</th>
            <th className="px-4 py-3 text-right">Pagu Rencana</th>
            <th className="px-4 py-3 text-right">Realisasi Efektif</th>
            <th className="px-4 py-3 text-right">Komitmen Berjalan</th>
            <th className="px-4 py-3 text-right">Sisa Tersedia</th>
            <th className="px-4 py-3 text-center">Realisasi (%)</th>
            {isPublished && canRequest && onAddExpenditure && (
              <th className="px-4 py-3 text-center">Aksi</th>
            )}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-800">
          {lines.map((line) => {
            const actual = line.effective_actual_expenses ?? 0;
            const commitments = line.outstanding_commitments ?? 0;
            const remaining = line.remaining_budget ?? (line.planned_amount - actual - commitments);
            const percentage = line.utilization_percentage ?? (line.planned_amount > 0 ? (actual / line.planned_amount) * 100 : 0);

            let barColor = 'bg-emerald-500';
            if (percentage >= 90) barColor = 'bg-rose-500';
            else if (percentage >= 75) barColor = 'bg-amber-500';

            return (
              <tr key={line.id} className="hover:bg-slate-800/40 transition-colors">
                <td className="px-4 py-3.5">
                  <div className="font-semibold text-white">
                    {line.category?.name || 'Kategori'}
                  </div>
                  <div className="text-[10px] text-slate-400 font-mono mt-0.5">
                    {line.category?.code || '-'}
                  </div>
                </td>
                <td className="px-4 py-3.5 text-right font-medium text-white">
                  {formatRupiah(line.planned_amount)}
                </td>
                <td className="px-4 py-3.5 text-right font-medium text-emerald-400">
                  {formatRupiah(actual)}
                  {line.posted_expenses !== undefined && line.reversed_expenses !== undefined && line.reversed_expenses > 0 && (
                    <div className="text-[10px] text-slate-400">
                      (Bruto: {formatRupiah(line.posted_expenses)} - Rev: {formatRupiah(line.reversed_expenses)})
                    </div>
                  )}
                </td>
                <td className="px-4 py-3.5 text-right font-medium text-cyan-400">
                  {formatRupiah(commitments)}
                  {line.request_counts && (
                    <div className="text-[10px] text-slate-400">
                      {line.request_counts.approved} disetujui, {line.request_counts.pending} diajukan
                    </div>
                  )}
                </td>
                <td className="px-4 py-3.5 text-right font-semibold">
                  <span className={remaining < 0 ? 'text-rose-400' : 'text-slate-200'}>
                    {formatRupiah(remaining)}
                  </span>
                </td>
                <td className="px-4 py-3.5">
                  <div className="flex flex-col items-center gap-1">
                    <span className="text-[11px] font-mono font-medium">
                      {percentage.toFixed(1)}%
                    </span>
                    <div className="w-16 bg-slate-800 rounded-full h-1.5 overflow-hidden">
                      <div
                        className={`h-1.5 rounded-full ${barColor}`}
                        style={{ width: `${Math.min(100, Math.max(0, percentage))}%` }}
                      />
                    </div>
                  </div>
                </td>
                {isPublished && canRequest && onAddExpenditure && (
                  <td className="px-4 py-3.5 text-center">
                    <Button
                      type="button"
                      variant="secondary"
                      size="sm"
                      onClick={() => onAddExpenditure(line)}
                      disabled={remaining <= 0}
                      className="text-xs py-1 px-2.5"
                    >
                      Ajukan Belanja
                    </Button>
                  </td>
                )}
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
};
