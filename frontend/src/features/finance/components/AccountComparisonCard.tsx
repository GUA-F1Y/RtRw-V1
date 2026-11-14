import React from 'react';
import { Link } from 'react-router-dom';
import { Badge } from '../../../components/ui/Badge.tsx';
import type { AccountComparisonData } from '../../../types/financeReports.ts';
import { formatRupiah } from '../utils/currency.ts';

interface AccountComparisonCardProps {
  data?: AccountComparisonData | null;
  isLoading?: boolean;
}

export const AccountComparisonCard: React.FC<AccountComparisonCardProps> = ({
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

  const accounts = data?.accounts ?? [];
  const summary = data?.summary ?? {
    total_accounts: 0,
    total_opening_balance: 0,
    total_effective_income: 0,
    total_effective_expense: 0,
    total_net_movement: 0,
    total_closing_balance: 0,
    total_transactions: 0,
  };

  if (accounts.length === 0) {
    return null;
  }

  return (
    <div className="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
      <div>
        <h3 className="text-sm font-semibold text-slate-100 flex items-center gap-2">
          <span>Komparasi Akun Kas Terbuka & Ditutup</span>
          <span className="text-[11px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 font-normal">
            {accounts.length} Rekening
          </span>
        </h3>
        <p className="text-xs text-slate-400 mt-0.5">
          Tinjauan komparatif mutasi kas dan posisi saldo setiap rekening buku kas
        </p>
      </div>

      <div className="overflow-x-auto border border-slate-800 rounded-xl">
        <table className="w-full text-left text-xs text-slate-300">
          <thead className="bg-slate-950 text-slate-400 uppercase tracking-wider text-[10px] border-b border-slate-800">
            <tr>
              <th scope="col" className="px-3.5 py-2.5">Nama Akun Kas</th>
              <th scope="col" className="px-3.5 py-2.5">Lingkup</th>
              <th scope="col" className="px-3.5 py-2.5 text-center">Status</th>
              <th scope="col" className="px-3.5 py-2.5 text-right">Saldo Awal</th>
              <th scope="col" className="px-3.5 py-2.5 text-right">Pemasukan</th>
              <th scope="col" className="px-3.5 py-2.5 text-right">Pengeluaran</th>
              <th scope="col" className="px-3.5 py-2.5 text-right">Arus Bersih (Net)</th>
              <th scope="col" className="px-3.5 py-2.5 text-right">Saldo Akhir</th>
              <th scope="col" className="px-3.5 py-2.5 text-center">Trx</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-800/60 font-mono">
            {accounts.map((acc) => {
              const isClosed = acc.status === 'CLOSED';
              const isRw = acc.scope_type === 'RW';

              return (
                <tr key={acc.account_id} className="hover:bg-slate-800/30 transition-colors">
                  <td className="px-3.5 py-2 font-sans">
                    <Link
                      to={`/app/finance/accounts/${acc.account_id}`}
                      className="font-medium text-slate-200 hover:text-emerald-400 transition-colors"
                    >
                      {acc.name}
                    </Link>
                    {acc.account_number && (
                      <span className="text-[10px] text-slate-500 font-mono ml-1.5">
                        ({acc.account_number})
                      </span>
                    )}
                  </td>
                  <td className="px-3.5 py-2 font-sans text-slate-300">
                    <span className={`inline-flex items-center gap-1 text-[11px] ${
                      isRw ? 'text-blue-300' : 'text-emerald-300'
                    }`}>
                      <span className={`w-1.5 h-1.5 rounded-full ${
                        isRw ? 'bg-blue-400' : 'bg-emerald-400'
                      }`} />
                      {isRw ? 'Kas RW' : `RT ${acc.rt_number || '-'}`}
                    </span>
                  </td>
                  <td className="px-3.5 py-2 text-center font-sans">
                    <Badge
                      variant={isClosed ? 'rose' : 'emerald'}
                      size="sm"
                    >
                      {isClosed ? 'Ditutup' : 'Aktif'}
                    </Badge>
                  </td>
                  <td className="px-3.5 py-2 text-right text-slate-300">
                    {formatRupiah(acc.opening_balance)}
                  </td>
                  <td className="px-3.5 py-2 text-right text-emerald-400">
                    {formatRupiah(acc.effective_income)}
                  </td>
                  <td className="px-3.5 py-2 text-right text-rose-400">
                    {formatRupiah(acc.effective_expense)}
                  </td>
                  <td
                    className={`px-3.5 py-2 text-right font-semibold ${
                      acc.net_movement >= 0 ? 'text-emerald-300' : 'text-rose-300'
                    }`}
                  >
                    {formatRupiah(acc.net_movement)}
                  </td>
                  <td className="px-3.5 py-2 text-right font-bold text-white">
                    {formatRupiah(acc.closing_balance)}
                  </td>
                  <td className="px-3.5 py-2 text-center text-slate-400">
                    {acc.transaction_count}
                  </td>
                </tr>
              );
            })}
          </tbody>
          <tfoot className="bg-slate-950/90 text-slate-200 font-mono font-bold border-t-2 border-slate-700">
            <tr>
              <td colSpan={3} className="px-3.5 py-2.5 font-sans">
                Total Agregat ({summary.total_accounts} Rekening)
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
              <td className="px-3.5 py-2.5 text-center text-slate-400">
                {summary.total_transactions}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  );
};
