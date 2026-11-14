import React, { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../../app/providers/useAuth.ts';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Badge } from '../../../components/ui/Badge.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import type {
  CashAccount,
  CashAccountQueryParams,
  FinancialTransaction,
  FinancialTransactionQueryParams,
} from '../../../types/finance.ts';
import { useCashAccounts, useFinancialTransactions } from '../api/financeQueries.ts';
import {
  AccountCard,
  CategoryManagerModal,
  CloseAccountDialog,
  CreateAccountDialog,
  EditAccountDialog,
  RecordTransactionDialog,
  ReverseTransactionDialog,
  TransactionLedgerTable,
} from '../components/index.ts';
import { formatRupiah } from '../utils/currency.ts';
import {
  canManageFinanceAccounts,
  canRecordFinanceTransactions,
  canReverseFinanceTransactions,
} from '../utils/permissions.ts';

export const FinanceOverviewPage: React.FC = () => {
  const { user, activeScope } = useAuth();

  const canManageAccounts = canManageFinanceAccounts(user);
  const canRecordTx = canRecordFinanceTransactions(user);
  const canReverseTx = canReverseFinanceTransactions(user);

  // Filters for Accounts list
  const [accountStatusFilter, setAccountStatusFilter] = useState<'active' | 'closed' | ''>('active');
  const [accountScopeFilter, setAccountScopeFilter] = useState<'ALL' | 'RW' | 'RT'>('ALL');
  const [accountSearch, setAccountSearch] = useState('');

  // Modals state
  const [isCreateAccountOpen, setIsCreateAccountOpen] = useState(false);
  const [editingAccount, setEditingAccount] = useState<CashAccount | null>(null);
  const [closingAccount, setClosingAccount] = useState<CashAccount | null>(null);
  const [isCategoriesModalOpen, setIsCategoriesModalOpen] = useState(false);
  const [isRecordModalOpen, setIsRecordModalOpen] = useState(false);
  const [selectedRecordAccount, setSelectedRecordAccount] = useState<CashAccount | null>(null);
  const [reversingTransaction, setReversingTransaction] = useState<FinancialTransaction | null>(null);

  // Ledger queries and pagination
  const [ledgerFilters, setLedgerFilters] = useState<FinancialTransactionQueryParams>({
    page: 1,
    per_page: 10,
  });

  const accountQueryParams: CashAccountQueryParams = useMemo(() => {
    const params: CashAccountQueryParams = {};
    if (accountStatusFilter) params.status = accountStatusFilter;
    if (accountSearch.trim()) params.q = accountSearch.trim();
    return params;
  }, [accountStatusFilter, accountSearch]);

  const {
    data: accountsResponse,
    isLoading: accountsLoading,
    error: accountsError,
  } = useCashAccounts(accountQueryParams);

  const {
    data: transactionsResponse,
    isLoading: transactionsLoading,
  } = useFinancialTransactions(ledgerFilters);

  const allAccounts = useMemo(
    () => accountsResponse?.data ?? [],
    [accountsResponse?.data]
  );

  // Filter accounts by scope in UI if requested
  const visibleAccounts = useMemo(() => {
    return allAccounts.filter((acc) => {
      if (accountScopeFilter === 'RW') return !acc.rt_id;
      if (accountScopeFilter === 'RT') return Boolean(acc.rt_id);
      return true;
    });
  }, [allAccounts, accountScopeFilter]);

  // Aggregate stats across accessible accounts
  const stats = useMemo(() => {
    let totalBalance = 0;
    let activeCount = 0;
    let closedCount = 0;

    for (const acc of allAccounts) {
      totalBalance += acc.current_balance;
      if (acc.status === 'ACTIVE') activeCount++;
      else if (acc.status === 'CLOSED') closedCount++;
    }

    return { totalBalance, activeCount, closedCount, totalAccounts: allAccounts.length };
  }, [allAccounts]);

  const handleOpenRecordForAccount = (acc: CashAccount) => {
    setSelectedRecordAccount(acc);
    setIsRecordModalOpen(true);
  };

  const handleOpenGeneralRecord = () => {
    setSelectedRecordAccount(null);
    setIsRecordModalOpen(true);
  };

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
        <div>
          <div className="flex items-center gap-2.5">
            <h1 className="text-xl font-bold text-white tracking-tight">Kas & Keuangan</h1>
            <Badge variant="emerald" size="sm">
              {activeScope?.scopeLevel === 'rt' ? 'Cakupan RT' : 'Cakupan RW'}
            </Badge>
          </div>
          <p className="text-xs text-slate-400 mt-1">
            Pengelolaan rekening kas operasional, pencatatan mutasi kas masuk/keluar, dan buku kas ledger.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Link
            to="/app/finance/budgets"
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white border border-slate-700/80 transition-colors"
          >
            <svg className="w-3.5 h-3.5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            <span>Rencana Anggaran (RAPB)</span>
          </Link>

          <Link
            to="/app/finance/expenditures"
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white border border-slate-700/80 transition-colors"
          >
            <svg className="w-3.5 h-3.5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span>Pengajuan Belanja</span>
          </Link>

          <Link
            to="/app/finance/reports"
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white border border-slate-700/80 transition-colors"
          >
            <svg className="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span>Laporan & Rekap</span>
          </Link>

          <Link
            to="/app/finance/periods"
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white border border-slate-700/80 transition-colors"
          >
            <svg className="w-3.5 h-3.5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            <span>Tutup Buku</span>
          </Link>

          <Link
            to="/app/finance/transfers"
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white border border-slate-700/80 transition-colors"
          >
            <svg className="w-3.5 h-3.5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
            </svg>
            <span>Transfer Kas</span>
          </Link>

          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={() => setIsCategoriesModalOpen(true)}
            className="text-xs"
          >
            Kategori Pos
          </Button>

          {canRecordTx && (
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={handleOpenGeneralRecord}
              className="text-xs text-emerald-400 border-emerald-800 hover:bg-emerald-950/40"
            >
              + Catat Mutasi
            </Button>
          )}

          {canManageAccounts && (
            <Button
              type="button"
              variant="primary"
              size="sm"
              onClick={() => setIsCreateAccountOpen(true)}
              className="text-xs"
            >
              + Buka Akun Kas
            </Button>
          )}
        </div>
      </div>

      {/* Aggregate Overview Card */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {/* Total Saldo Keseluruhan */}
        <div className="bg-gradient-to-br from-emerald-950/50 via-slate-900 to-slate-900 border border-emerald-800/40 rounded-xl p-4 shadow-lg shadow-emerald-950/20">
          <div className="text-xs font-medium text-emerald-400">Total Likuiditas Kas</div>
          <div className="mt-2 text-2xl font-bold text-white tracking-tight">
            {formatRupiah(stats.totalBalance)}
          </div>
          <div className="mt-1 text-[11px] text-slate-400">
            Akumulasi saldo seluruh akun kas aktif dalam wewenang Anda
          </div>
        </div>

        {/* Akun Kas Aktif */}
        <div className="bg-slate-900/70 border border-slate-800 rounded-xl p-4 shadow-md">
          <div className="text-xs font-medium text-slate-400">Akun Kas Aktif</div>
          <div className="mt-2 text-2xl font-bold text-slate-100">
            {stats.activeCount} <span className="text-xs font-normal text-slate-500">rekening</span>
          </div>
          <div className="mt-1 text-[11px] text-slate-500">
            Siap untuk menerima dan membukukan mutasi harian
          </div>
        </div>

        {/* Akun Kas Ditutup */}
        <div className="bg-slate-900/70 border border-slate-800 rounded-xl p-4 shadow-md">
          <div className="text-xs font-medium text-slate-400">Akun Kas Ditutup (Closed)</div>
          <div className="mt-2 text-2xl font-bold text-slate-400">
            {stats.closedCount} <span className="text-xs font-normal text-slate-600">rekening</span>
          </div>
          <div className="mt-1 text-[11px] text-slate-500">
            Tersimpan permanen untuk keperluan audit historis
          </div>
        </div>
      </div>

      {/* Accounts Section */}
      <div className="space-y-4">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-slate-100">Daftar Rekening Kas</h2>
            <p className="text-xs text-slate-400">
              Pilih rekening kas untuk melihat rincian saldo dan buku besar per akun.
            </p>
          </div>

          {/* Account Filters */}
          <div className="flex flex-wrap items-center gap-2">
            {/* Search */}
            <input
              type="text"
              placeholder="Cari nama akun..."
              value={accountSearch}
              onChange={(e) => setAccountSearch(e.target.value)}
              className="px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs placeholder-slate-500 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            />

            {/* Scope filter (if user has RW access) */}
            {activeScope?.scopeLevel !== 'rt' && (
              <select
                value={accountScopeFilter}
                onChange={(e) => setAccountScopeFilter(e.target.value as 'ALL' | 'RW' | 'RT')}
                className="px-2.5 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-200 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
              >
                <option value="ALL">Semua Cakupan</option>
                <option value="RW">Tingkat RW</option>
                <option value="RT">Tingkat RT</option>
              </select>
            )}

            {/* Status filter */}
            <select
              value={accountStatusFilter}
              onChange={(e) => setAccountStatusFilter(e.target.value as 'active' | 'closed' | '')}
              className="px-2.5 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-200 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            >
              <option value="active">Status: Aktif</option>
              <option value="closed">Status: Ditutup</option>
              <option value="">Semua Status</option>
            </select>
          </div>
        </div>

        {accountsError && (
          <Alert
            type="error"
            title="Gagal Memuat Akun Kas"
            message={accountsError.message}
            requestId={accountsError.requestId}
          />
        )}

        {accountsLoading ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {[1, 2, 3].map((i) => (
              <div key={i} className="h-44 rounded-xl bg-slate-900/60 border border-slate-800 animate-pulse" />
            ))}
          </div>
        ) : visibleAccounts.length === 0 ? (
          <div className="py-12 rounded-xl border border-slate-800 bg-slate-900/40 text-center">
            <div className="max-w-xs mx-auto space-y-2">
              <div className="w-10 h-10 rounded-full bg-slate-800 mx-auto flex items-center justify-center text-slate-500">
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
              </div>
              <p className="font-medium text-slate-300">Belum ada akun kas</p>
              <p className="text-[11px] text-slate-500">
                Belum ada rekening kas yang sesuai kriteria pencarian Anda.
              </p>
              {canManageAccounts && (
                <div className="pt-2">
                  <Button
                    type="button"
                    variant="primary"
                    size="sm"
                    onClick={() => setIsCreateAccountOpen(true)}
                    className="text-xs"
                  >
                    + Buka Akun Kas Pertama
                  </Button>
                </div>
              )}
            </div>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {visibleAccounts.map((acc) => (
              <AccountCard
                key={acc.id}
                account={acc}
                canManage={canManageAccounts}
                canRecord={canRecordTx}
                onEdit={(account) => setEditingAccount(account)}
                onCloseAccount={(account) => setClosingAccount(account)}
                onRecordTransaction={(account) => handleOpenRecordForAccount(account)}
              />
            ))}
          </div>
        )}
      </div>

      {/* Ledger Section across Accounts */}
      <div className="space-y-4 pt-4 border-t border-slate-800">
        <div>
          <h2 className="text-base font-semibold text-slate-100">Buku Kas & Mutasi Terkini</h2>
          <p className="text-xs text-slate-400">
            Jejak transaksi ledger komprehensif dari seluruh rekening kas yang Anda kelola.
          </p>
        </div>

        <TransactionLedgerTable
          transactions={transactionsResponse?.data ?? []}
          pagination={transactionsResponse?.meta?.pagination}
          isLoading={transactionsLoading}
          filters={ledgerFilters}
          onFilterChange={(newFilters) => setLedgerFilters(newFilters)}
          onPageChange={(page) => setLedgerFilters((prev) => ({ ...prev, page }))}
          canReverse={canReverseTx}
          onReverseClick={(tx) => setReversingTransaction(tx)}
        />
      </div>

      {/* Modals */}
      <CreateAccountDialog
        open={isCreateAccountOpen}
        onClose={() => setIsCreateAccountOpen(false)}
      />

      <EditAccountDialog
        account={editingAccount}
        open={Boolean(editingAccount)}
        onClose={() => setEditingAccount(null)}
      />

      <CloseAccountDialog
        account={closingAccount}
        open={Boolean(closingAccount)}
        onClose={() => setClosingAccount(null)}
      />

      <CategoryManagerModal
        open={isCategoriesModalOpen}
        onClose={() => setIsCategoriesModalOpen(false)}
      />

      <RecordTransactionDialog
        open={isRecordModalOpen}
        defaultAccountId={selectedRecordAccount?.id}
        onClose={() => {
          setIsRecordModalOpen(false);
          setSelectedRecordAccount(null);
        }}
      />

      <ReverseTransactionDialog
        transaction={reversingTransaction}
        open={Boolean(reversingTransaction)}
        onClose={() => setReversingTransaction(null)}
      />
    </div>
  );
};
