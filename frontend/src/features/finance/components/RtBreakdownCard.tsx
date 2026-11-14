import React from 'react';
import type { RtBreakdownData } from '../../../types/financeReports.ts';
import { formatRupiah } from '../utils/currency.ts';

interface RtBreakdownCardProps {
  data?: RtBreakdownData | null;
  isLoading?: boolean;
}

export const RtBreakdownCard: React.FC<RtBreakdownCardProps> = ({
  data,
  isLoading = false,
}) => {
  if (isLoading) {
    return (
      <div className="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
        <div className="h-6 w-48 bg-slate-800 rounded animate-pulse" />
        <div className="h-44 bg-slate-950/60 rounded-xl animate-pulse" />
      </div>
    );
  }

  const items = data?.items ?? [];
  const summary = data?.summary ?? {
    total_accounts: 0,
    total_opening_balance: 0,
    total_effective_income: 0,
    total_effective_expense: 0,
    total_net_movement: 0,
    total_closing_balance: 0,
  };

  if (items.length === 0) {
    return null;
  }

  return (
    <div className="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
      <div>
        <h3 className="text-sm font-semibold text-slate-100 flex items-center gap-2">
          <span>Rekapitulasi Keuangan per Unit RT & RW</span>
          <span className="text-[11px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 font-normal">
            {items.length} Entitas
          </span>
        </h3>
        <p className="text-xs text-slate-400 mt-0.5">
          Perbandingan kinerja keuangan antar unit RT dan kas induk RW dalam cakupan kewenangan Anda
        </p>
      </div>

      <div className="overflow-x-auto border border-slate-800 rounded-xl">
        <table className="w-full text-left text-xs text-slate-300">
          <thead className="bg-slate-950 text-slate-400 uppercase tracking-wider text-[10px] border-b border-slate-800">
            <tr>
              <th scope="col" className="px-3.5 py-2.5">Unit / Lingkup</th>
              <th scope="col" className="px-3.5 py-2.5 text-center">Akun</th>
              <th scope="col" className="px-3.5 py-2.5 text-right">Saldo Awal</th>
              <th scope="col" className="px-3.5 py-2.5 text-right">Pemasukan</th>
              <th scope="col" className="px-3.5 py-2.5 text-right">Pengeluaran</th>
              <th scope="col" className="px-3.5 py-2.5 text-right">Arus Bersih (Net)</th>
              <th scope="col" className="px-3.5 py-2.5 text-right">Saldo Akhir</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-800/60 font-mono">
            {items.map((item) => {
              const isRw = item.scope_type === 'RW';

              return (
                <tr
                  key={item.rt_id || 'rw'}
                  className={`hover:bg-slate-800/30 transition-colors ${
                    isRw ? 'bg-slate-900/40 font-medium' : ''
                  }`}
                >
                  <td className="px-3.5 py-2 font-sans text-slate-200">
                    <div className="flex items-center gap-2">
                      <span
                        className={`w-2 h-2 rounded-full ${
                          isRw ? 'bg-blue-400' : 'bg-emerald-400'
                        }`}
                      />
                      <span className="font-semibold">{item.label}</span>
                      {isRw && (
                        <span className="text-[10px] px-1.5 py-0.2 rounded bg-blue-950 text-blue-300 border border-blue-800/60 font-normal">
                          Induk RW
                        </span>
                      )}
                    </div>
                  </td>
                  <td className="px-3.5 py-2 text-center text-slate-400">
                    {item.account_count}
                  </td>
                  <td className="px-3.5 py-2 text-right text-slate-300">
                    {formatRupiah(item.opening_balance)}
                  </td>
                  <td className="px-3.5 py-2 text-right text-emerald-400">
                    {formatRupiah(item.effective_income)}
                  </td>
                  <td className="px-3.5 py-2 text-right text-rose-400">
                    {formatRupiah(item.effective_expense)}
                  </td>
                  <td
                    className={`px-3.5 py-2 text-right font-semibold ${
                      item.net_movement >= 0 ? 'text-emerald-300' : 'text-rose-300'
                    }`}
                  >
                    {formatRupiah(item.net_movement)}
                  </td>
                  <td className="px-3.5 py-2 text-right font-bold text-white">
                    {formatRupiah(item.closing_balance)}
                  </td>
                </tr>
              );
            })}
          </tbody>
          <tfoot className="bg-slate-950/90 text-slate-200 font-mono font-bold border-t-2 border-slate-700">
            <tr>
              <td className="px-3.5 py-2.5 font-sans">
                Total Agregat ({summary.total_accounts} Akun)
              </td>
              <td className="px-3.5 py-2.5 text-center text-slate-400">
                {summary.total_accounts}
              </td>
              <td className="px-3.5 py-2.5 text-right">
                {formatRupiah(summary.total_opening_balance)}
              </td>
              <td className="px-3.5 py-2.5 text-right text-emerald-400">
                {formatRupiah(summary.total_effective_income)}
              </td>
              <td className="px-3.5 py-2.5 text-right text-rose-400">
                {formatRupiah(summary.total_effective_expense)}
              </td>
              <td
                className={`px-3.5 py-2.5 text-right ${
                  summary.total_net_movement >= 0 ? 'text-emerald-300' : 'text-rose-300'
                }`}
              >
                {formatRupiah(summary.total_net_movement)}
              </td>
              <td className="px-3.5 py-2.5 text-right text-white font-extrabold">
                {formatRupiah(summary.total_closing_balance)}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  );
};
