import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../../app/providers/useAuth.ts';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import {
  useCashAccounts,
  usePeriodClosure,
  usePeriodClosurePreview,
  usePeriodClosures,
} from '../api/financeQueries.ts';
import { ClosePeriodDialog } from '../components/ClosePeriodDialog.tsx';
import { ClosureDetailModal } from '../components/ClosureDetailModal.tsx';
import { formatRupiah } from '../utils/currency.ts';
import { canCloseFinancePeriod } from '../utils/permissions.ts';

const MONTHS = [
  { value: 1, label: 'Januari' },
  { value: 2, label: 'Februari' },
  { value: 3, label: 'Maret' },
  { value: 4, label: 'April' },
  { value: 5, label: 'Mei' },
  { value: 6, label: 'Juni' },
  { value: 7, label: 'Juli' },
  { value: 8, label: 'Agustus' },
  { value: 9, label: 'September' },
  { value: 10, label: 'Oktober' },
  { value: 11, label: 'November' },
  { value: 12, label: 'Desember' },
];

function getDefaultPreviousMonth() {
  const now = new Date();
  const prevMonthDate = new Date(now.getFullYear(), now.getMonth() - 1, 1);
  return {
    year: prevMonthDate.getFullYear(),
    month: prevMonthDate.getMonth() + 1,
  };
}

const defaultPeriod = getDefaultPreviousMonth();

export const PeriodClosurePage: React.FC = () => {
  const { user } = useAuth();
  const canClose = canCloseFinancePeriod(user);

  const [selectedYear, setSelectedYear] = useState<number>(defaultPeriod.year);
  const [selectedMonth, setSelectedMonth] = useState<number>(defaultPeriod.month);
  const [selectedAccountId, setSelectedAccountId] = useState<string>('');

  // Dialog & Modal State
  const [isCloseDialogOpen, setIsCloseDialogOpen] = useState(false);
  const [activeClosureId, setActiveClosureId] = useState<string | null>(null);

  // Load Accounts for Selector
  const { data: accountsResponse, isLoading: isLoadingAccounts } = useCashAccounts({ per_page: 100 });
  const accounts = accountsResponse?.data ?? [];

  // Initialize selected account once accounts are loaded
  const currentAccountId = selectedAccountId || (accounts.length > 0 ? accounts[0].id : '');
  const currentAccount = accounts.find((a) => a.id === currentAccountId) || null;

  // Load Period Closure Preview
  const previewQuery = usePeriodClosurePreview(currentAccountId, selectedYear, selectedMonth, {
    enabled: Boolean(currentAccountId),
  });
  const preview = previewQuery.data?.data ?? null;

  // Load Historical Closures for the Selected Account
  const closuresQuery = usePeriodClosures(
    currentAccountId ? { cash_account_id: currentAccountId, per_page: 24 } : undefined,
    { enabled: Boolean(currentAccountId) }
  );
  const closures = closuresQuery.data?.data ?? [];

  // Active closure detail query for modal
  const closureDetailQuery = usePeriodClosure(activeClosureId ?? '', {
    enabled: Boolean(activeClosureId),
  });
  const activeClosure = closureDetailQuery.data?.data ?? null;

  const monthLabel = MONTHS.find((m) => m.value === selectedMonth)?.label || `Bulan ${selectedMonth}`;

  return (
    <div className="space-y-6">
      {/* Breadcrumb Navigation */}
      <nav className="flex items-center gap-2 text-xs text-slate-400">
        <Link to="/app/finance" className="hover:text-slate-200 transition-colors">
          Keuangan
        </Link>
        <span>/</span>
        <span className="text-slate-200 font-medium">Tutup Buku & Rekonsiliasi</span>
      </nav>

      {/* Page Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-100">
            Tutup Buku & Rekonsiliasi Kas
          </h1>
          <p className="text-sm text-slate-400 mt-1">
            Penutupan saldo kas bulanan, rekonsiliasi fisik, dan penguncian periode akuntansi yang tidak dapat diubah (immutable).
          </p>
        </div>
        {/* Navigation Tabs */}
        <div className="flex items-center gap-1.5 p-1 bg-slate-900 border border-slate-800 rounded-xl text-xs">
          <Link
            to="/app/finance"
            className="px-3 py-1.5 rounded-lg text-slate-400 hover:text-slate-200 transition-colors"
          >
            Buku Kas & Akun
          </Link>
          <Link
            to="/app/finance/reports"
            className="px-3 py-1.5 rounded-lg text-slate-400 hover:text-slate-200 transition-colors"
          >
            Laporan & Rekap
          </Link>
          <span className="px-3 py-1.5 rounded-lg bg-slate-800 text-white font-medium shadow-sm">
            Tutup Periode
          </span>
          <Link
            to="/app/finance/transfers"
            className="px-3 py-1.5 rounded-lg text-slate-400 hover:text-slate-200 transition-colors"
          >
            Transfer Kas
          </Link>
        </div>
      </div>

      {/* Selectors Bar */}
      <div className="p-4 bg-slate-900 border border-slate-800 rounded-2xl flex flex-wrap items-center justify-between gap-4">
        <div className="flex flex-wrap items-center gap-4">
          {/* Account Selector */}
          <div className="space-y-1">
            <label htmlFor="closure-account-select" className="block text-xs font-semibold text-slate-300">
              Pilih Akun Kas
            </label>
            <select
              id="closure-account-select"
              value={currentAccountId}
              onChange={(e) => setSelectedAccountId(e.target.value)}
              disabled={isLoadingAccounts || accounts.length === 0}
              className="bg-slate-950 border border-slate-700 text-slate-200 text-sm rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-sky-500"
            >
              {accounts.map((acc) => (
                <option key={acc.id} value={acc.id}>
                  {acc.name} {acc.rt ? `(RT ${acc.rt.rt_number || acc.rt.name})` : '(Kas RW)'}
                </option>
              ))}
            </select>
          </div>

          {/* Year Selector */}
          <div className="space-y-1">
            <label htmlFor="closure-year-select" className="block text-xs font-semibold text-slate-300">
              Tahun
            </label>
            <select
              id="closure-year-select"
              value={selectedYear}
              onChange={(e) => setSelectedYear(parseInt(e.target.value, 10))}
              className="bg-slate-950 border border-slate-700 text-slate-200 text-sm rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-sky-500"
            >
              {[defaultPeriod.year - 1, defaultPeriod.year, defaultPeriod.year + 1].map((yr) => (
                <option key={yr} value={yr}>
                  {yr}
                </option>
              ))}
            </select>
          </div>

          {/* Month Selector */}
          <div className="space-y-1">
            <label htmlFor="closure-month-select" className="block text-xs font-semibold text-slate-300">
              Bulan
            </label>
            <select
              id="closure-month-select"
              value={selectedMonth}
              onChange={(e) => setSelectedMonth(parseInt(e.target.value, 10))}
              className="bg-slate-950 border border-slate-700 text-slate-200 text-sm rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-sky-500"
            >
              {MONTHS.map((m) => (
                <option key={m.value} value={m.value}>
                  {m.label}
                </option>
              ))}
            </select>
          </div>
        </div>

        {/* Quick info */}
        {currentAccount && (
          <div className="text-right text-xs text-slate-400">
            <div>Saldo Berjalan Akun: <span className="font-semibold text-slate-200">{formatRupiah(currentAccount.current_balance)}</span></div>
            <div>Status Akun: <span className="font-semibold text-emerald-400">{currentAccount.status}</span></div>
          </div>
        )}
      </div>

      {/* Main Preview & Closure Status Card */}
      {previewQuery.isLoading ? (
        <div className="p-8 bg-slate-900 border border-slate-800 rounded-2xl flex items-center justify-center text-slate-400 text-sm">
          <svg className="animate-spin h-5 w-5 mr-3 text-sky-400" viewBox="0 0 24 24">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
          </svg>
          Memuat kalkulasi buku kas untuk {monthLabel} {selectedYear}...
        </div>
      ) : previewQuery.isError ? (
        <Alert
          type="error"
          title="Gagal Memuat Pratinjau Periode"
          message={previewQuery.error?.message || 'Terjadi kesalahan saat memuat kalkulasi periode.'}
        />
      ) : preview ? (
        <div className="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
          {/* Status Header Banner */}
          <div className={`p-4 border-b flex flex-col md:flex-row md:items-center justify-between gap-3 ${
            preview.is_already_closed
              ? 'bg-sky-950/40 border-sky-800/60'
              : !preview.is_completed_month
              ? 'bg-slate-950/80 border-slate-800'
              : 'bg-emerald-950/30 border-emerald-800/50'
          }`}>
            <div className="flex items-center gap-3">
              {preview.is_already_closed ? (
                <span className="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-sky-900 text-sky-300 border border-sky-700">
                  SUDAH DITUTUP
                </span>
              ) : !preview.is_completed_month ? (
                <span className="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-slate-800 text-slate-300 border border-slate-700">
                  BELUM DAPAT DITUTUP
                </span>
              ) : (
                <span className="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-emerald-900 text-emerald-300 border border-emerald-700">
                  SIAP DITUTUP
                </span>
              )}
              <div className="text-xs text-slate-300">
                {preview.is_already_closed ? (
                  <span>Periode {monthLabel} {selectedYear} telah resmi ditutup dan dikunci. Transaksi pada tanggal ini dilindungi dari perubahan.</span>
                ) : !preview.is_completed_month ? (
                  <span>Periode {monthLabel} {selectedYear} adalah bulan berjalan atau masa depan. Hanya bulan yang telah berakhir penuh yang dapat ditutup.</span>
                ) : (
                  <span>Bulan {monthLabel} {selectedYear} telah berakhir penuh ({preview.period_start} s/d {preview.period_end}) dan memenuhi syarat penutupan buku.</span>
                )}
              </div>
            </div>

            <div className="flex items-center gap-2">
              {preview.is_already_closed && preview.existing_closure_id && (
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={() => setActiveClosureId(preview.existing_closure_id)}
                >
                  Lihat Arsip Penutupan
                </Button>
              )}
              {preview.is_eligible_for_closure && canClose && (
                <Button
                  variant="primary"
                  size="sm"
                  onClick={() => setIsCloseDialogOpen(true)}
                >
                  Tutup Buku Periode Ini
                </Button>
              )}
            </div>
          </div>

          {/* Metric Cards Grid */}
          <div className="p-6">
            <div className="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-4">
              Kalkulasi Pembukuan Periode {monthLabel} {selectedYear} ({preview.period_start} s/d {preview.period_end})
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              {/* Saldo Awal */}
              <div className="p-4 bg-slate-950/80 border border-slate-800 rounded-xl space-y-1">
                <span className="text-xs text-slate-400 block">Saldo Awal Buku:</span>
                <span className="text-xl font-bold text-slate-100">{formatRupiah(preview.opening_balance)}</span>
                <span className="text-[11px] text-slate-500 block">
                  Akumulasi kas efektif sebelum {preview.period_start}
                </span>
              </div>

              {/* Pemasukan Efektif */}
              <div className="p-4 bg-slate-950/80 border border-slate-800 rounded-xl space-y-1">
                <span className="text-xs text-slate-400 block">Pemasukan Efektif:</span>
                <span className="text-xl font-bold text-emerald-400">+{formatRupiah(preview.effective_income)}</span>
                <span className="text-[11px] text-slate-500 block">
                  Dicatat {formatRupiah(preview.posted_income)}, dibalik {formatRupiah(preview.reversed_income)}
                </span>
              </div>

              {/* Pengeluaran Efektif */}
              <div className="p-4 bg-slate-950/80 border border-slate-800 rounded-xl space-y-1">
                <span className="text-xs text-slate-400 block">Pengeluaran Efektif:</span>
                <span className="text-xl font-bold text-rose-400">-{formatRupiah(preview.effective_expense)}</span>
                <span className="text-[11px] text-slate-500 block">
                  Dicatat {formatRupiah(preview.posted_expense)}, dibalik {formatRupiah(preview.reversed_expense)}
                </span>
              </div>

              {/* Saldo Akhir Kalkulasi */}
              <div className="p-4 bg-slate-950/80 border border-sky-900/50 rounded-xl space-y-1 bg-gradient-to-br from-slate-950 via-slate-950 to-sky-950/30">
                <span className="text-xs text-sky-400 font-semibold block">Ekspektasi Saldo Akhir:</span>
                <span className="text-xl font-extrabold text-slate-100">{formatRupiah(preview.closing_balance)}</span>
                <span className="text-[11px] text-slate-400 block">
                  Pergerakan bersih {preview.net_movement >= 0 ? '+' : ''}{formatRupiah(preview.net_movement)}
                </span>
              </div>
            </div>

            {/* Additional Counts & Disclaimer */}
            <div className="mt-4 pt-4 border-t border-slate-800/80 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 text-xs text-slate-400">
              <div className="flex gap-4">
                <span>Total Mutasi Periode: <strong className="text-slate-200">{preview.total_transactions_count}</strong></span>
                <span>Transaksi Dibalik: <strong className="text-slate-200">{preview.reversed_transactions_count}</strong></span>
              </div>
              <div className="text-[11px] text-slate-500 italic">
                * Angka di atas bersifat pratinjau kalkulasi buku kas hingga dikonfirmasi dan dikunci secara atomik.
              </div>
            </div>
          </div>
        </div>
      ) : null}

      {/* Historical Period Closures Table */}
      <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
          <div>
            <h2 className="text-base font-bold text-slate-100">
              Riwayat Arsip Penutupan Buku
            </h2>
            <p className="text-xs text-slate-400">
              Daftar snapshot pembukuan resmi yang telah disetujui dan dikunci untuk akun {currentAccount?.name || 'ini'}.
            </p>
          </div>
          <span className="text-xs text-slate-400">
            Total {closures.length} periode ditutup
          </span>
        </div>

        {closuresQuery.isLoading ? (
          <div className="p-8 text-center text-slate-400 text-sm">
            Memuat riwayat arsip tutup buku...
          </div>
        ) : closures.length === 0 ? (
          <div className="p-8 text-center text-slate-500 text-sm bg-slate-950/40 rounded-xl border border-dashed border-slate-800">
            Belum ada periode akuntansi yang ditutup untuk akun ini.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-slate-300">
              <thead className="bg-slate-950 text-slate-400 font-semibold border-b border-slate-800 uppercase tracking-wider">
                <tr>
                  <th className="py-3 px-3">Periode</th>
                  <th className="py-3 px-3">Rentang Tanggal</th>
                  <th className="py-3 px-3">Saldo Akhir Buku</th>
                  <th className="py-3 px-3">Fisik Kas Dihitung</th>
                  <th className="py-3 px-3">Selisih</th>
                  <th className="py-3 px-3">Ditutup Oleh</th>
                  <th className="py-3 px-3">Waktu Tutup</th>
                  <th className="py-3 px-3 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60">
                {closures.map((c) => {
                  const mName = MONTHS.find((m) => m.value === c.period_month)?.label || `Bulan ${c.period_month}`;
                  const diff = c.reconciliation_difference;

                  return (
                    <tr key={c.id} className="hover:bg-slate-800/30 transition-colors">
                      <td className="py-3 px-3 font-semibold text-slate-100">
                        {mName} {c.period_year}
                      </td>
                      <td className="py-3 px-3 text-slate-400 text-[11px]">
                        {c.period_start} s/d {c.period_end}
                      </td>
                      <td className="py-3 px-3 font-bold text-slate-200">
                        {formatRupiah(c.closing_balance)}
                      </td>
                      <td className="py-3 px-3 text-slate-300">
                        {c.counted_cash_balance !== null ? formatRupiah(c.counted_cash_balance) : '-'}
                      </td>
                      <td className="py-3 px-3">
                        {diff === null ? (
                          <span className="text-slate-500">-</span>
                        ) : diff === 0 ? (
                          <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-950 text-emerald-400 border border-emerald-800">
                            Cocok
                          </span>
                        ) : diff > 0 ? (
                          <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-950 text-amber-300 border border-amber-800">
                            +{formatRupiah(diff)}
                          </span>
                        ) : (
                          <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-rose-950 text-rose-300 border border-rose-800">
                            -{formatRupiah(Math.abs(diff))}
                          </span>
                        )}
                      </td>
                      <td className="py-3 px-3 text-slate-300">
                        {c.closed_by.name || c.closed_by.email || 'Pengurus'}
                      </td>
                      <td className="py-3 px-3 text-slate-400 text-[11px]">
                        {new Date(c.closed_at).toLocaleDateString('id-ID', { dateStyle: 'medium' })}
                      </td>
                      <td className="py-3 px-3 text-right">
                        <Button
                          variant="secondary"
                          size="sm"
                          onClick={() => setActiveClosureId(c.id)}
                        >
                          Lihat Arsip
                        </Button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Close Period Dialog */}
      <ClosePeriodDialog
        account={currentAccount}
        year={selectedYear}
        month={selectedMonth}
        preview={preview}
        open={isCloseDialogOpen}
        onClose={() => setIsCloseDialogOpen(false)}
        onSuccess={(closure) => {
          setActiveClosureId(closure.id);
        }}
      />

      {/* Historical Closure Detail Modal */}
      <ClosureDetailModal
        closure={activeClosure}
        open={Boolean(activeClosureId)}
        onClose={() => setActiveClosureId(null)}
      />
    </div>
  );
};
