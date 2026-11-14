import React, { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useAuth } from '../../../app/providers/useAuth.ts';
import { Alert } from '../../../components/feedback/Alert.tsx';
import { Badge } from '../../../components/ui/Badge.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import type { FinancialTransaction, FinancialTransactionQueryParams } from '../../../types/finance.ts';
import {
  useAccountSummary,
  useCashAccount,
  useFinancialTransactions,
} from '../api/financeQueries.ts';
import {
  AccountSummaryHeader,
  CloseAccountDialog,
  EditAccountDialog,
  RecordTransactionDialog,
  ReverseTransactionDialog,
  TransactionLedgerTable,
} from '../components/index.ts';
import {
  canManageFinanceAccounts,
  canRecordFinanceTransactions,
  canReverseFinanceTransactions,
} from '../utils/permissions.ts';

export const CashAccountDetailPage: React.FC = () => {
  const { accountId = '' } = useParams<{ accountId: string }>();
  const { user } = useAuth();

  const canManageAccounts = canManageFinanceAccounts(user);
  const canRecordTx = canRecordFinanceTransactions(user);
  const canReverseTx = canReverseFinanceTransactions(user);

  // Modals state
  const [isEditOpen, setIsEditOpen] = useState(false);
  const [isCloseOpen, setIsCloseOpen] = useState(false);
  const [isRecordOpen, setIsRecordOpen] = useState(false);
  const [reversingTx, setReversingTx] = useState<FinancialTransaction | null>(null);

  // Ledger filters scoped to this account
  const [ledgerFilters, setLedgerFilters] = useState<FinancialTransactionQueryParams>({
    cash_account_id: accountId,
    page: 1,
    per_page: 15,
  });

  const {
    data: accountResponse,
    isLoading: accountLoading,
    error: accountError,
  } = useCashAccount(accountId);

  const {
    data: summaryResponse,
    isLoading: summaryLoading,
  } = useAccountSummary(accountId);

  const {
    data: transactionsResponse,
    isLoading: transactionsLoading,
  } = useFinancialTransactions(ledgerFilters, { enabled: Boolean(accountId) });

  const account = accountResponse?.data;
  const isClosed = account?.status === 'CLOSED';
  const isRtScope = Boolean(account?.rt_id);

  const scopeLabel = isRtScope
    ? `RT ${account?.rt?.rt_number ? String(account.rt.rt_number).padStart(3, '0') : account?.rt?.name || 'Unit RT'}`
    : `RW ${account?.rw?.rw_number ? String(account.rw.rw_number).padStart(3, '0') : account?.rw?.name || 'Kas Utama'}`;

  if (accountError) {
    return (
      <div className="space-y-4">
        <Link
          to="/app/finance"
          className="text-xs text-slate-400 hover:text-slate-200 inline-flex items-center gap-1.5"
        >
          ← Kembali ke Kas & Keuangan
        </Link>
        <Alert
          type="error"
          title="Akun Kas Tidak Ditemukan"
          message={accountError.message || 'Akun kas yang Anda tuju tidak ditemukan atau berada di luar cakupan izin Anda.'}
          requestId={accountError.requestId}
        />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Back button */}
      <div>
        <Link
          to="/app/finance"
          className="text-xs text-slate-400 hover:text-slate-200 inline-flex items-center gap-1.5 transition-colors"
        >
          <span>← Kembali ke Daftar Kas & Keuangan</span>
        </Link>
      </div>

      {/* Account Header Banner */}
      {accountLoading ? (
        <div className="h-32 rounded-xl bg-slate-900/60 border border-slate-800 animate-pulse" />
      ) : account ? (
        <div className="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div className="space-y-1.5">
              <div className="flex flex-wrap items-center gap-2">
                <Badge variant={isRtScope ? 'blue' : 'indigo'} size="sm">
                  {scopeLabel}
                </Badge>
                <Badge variant={isClosed ? 'slate' : 'emerald'} size="sm">
                  {isClosed ? 'CLOSED' : 'ACTIVE'}
                </Badge>
                {account.account_number && (
                  <span className="text-xs font-mono text-slate-400 bg-slate-800 px-2 py-0.5 rounded border border-slate-700/60">
                    No. Rek: {account.account_number}
                  </span>
                )}
              </div>

              <h1 className="text-2xl font-bold text-white tracking-tight">
                {account.name}
              </h1>

              {account.description && (
                <p className="text-xs text-slate-400 max-w-2xl leading-relaxed">
                  {account.description}
                </p>
              )}
            </div>

            {/* Quick Actions */}
            <div className="flex flex-wrap items-center gap-2 self-start">
              {!isClosed && canRecordTx && (
                <Button
                  type="button"
                  variant="primary"
                  size="sm"
                  onClick={() => setIsRecordOpen(true)}
                  className="text-xs"
                >
                  + Catat Mutasi
                </Button>
              )}

              <Link
                to={`/app/finance/periods?accountId=${account.id}`}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white border border-slate-700/80 transition-colors"
              >
                <svg className="w-3.5 h-3.5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <span>Tutup Periode</span>
              </Link>

              {canManageAccounts && (
                <>
                  <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    onClick={() => setIsEditOpen(true)}
                    className="text-xs"
                  >
                    Edit Info
                  </Button>
                  {!isClosed && (
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => setIsCloseOpen(true)}
                      className="text-xs text-rose-400 border-rose-900/50 hover:bg-rose-950/40"
                    >
                      Tutup Akun
                    </Button>
                  )}
                </>
              )}
            </div>
          </div>

          {/* Closed Alert Notice */}
          {isClosed && (
            <div className="p-3 rounded-xl bg-rose-950/30 border border-rose-900/40 text-xs text-rose-300 flex items-start gap-2.5">
              <svg className="w-4 h-4 text-rose-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <div>
                <span className="font-semibold text-rose-200">Akun Kas Ini Telah Ditutup.</span> Tidak dapat menerima pencatatan transaksi baru.
                {account.closed_reason && (
                  <div className="mt-1 text-slate-300">
                    <span className="text-slate-400">Alasan Penutupan:</span> {account.closed_reason}
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      ) : null}

      {/* Account Financial Summary Breakdown */}
      <AccountSummaryHeader
        summary={summaryResponse?.data}
        isLoading={summaryLoading}
        title="Ringkasan Neraca Akun"
        subtitle="Analisis saldo awal, mutasi masuk/keluar kotor, pembalikan (reversal), dan saldo bersih mutakhir."
      />

      {/* Ledger Table Section */}
      <div className="space-y-4 pt-4 border-t border-slate-800">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
          <div>
            <h2 className="text-base font-semibold text-slate-100">Buku Besar Kas (Ledger)</h2>
            <p className="text-xs text-slate-400">
              Riwayat lengkap pencatatan mutasi kas masuk, kas keluar, dan penyesuaian pembalikan.
            </p>
          </div>

          {!isClosed && canRecordTx && (
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => setIsRecordOpen(true)}
              className="text-xs text-emerald-400 border-emerald-800 hover:bg-emerald-950/40"
            >
              + Catat Mutasi
            </Button>
          )}
        </div>

        <TransactionLedgerTable
          transactions={transactionsResponse?.data ?? []}
          pagination={transactionsResponse?.meta?.pagination}
          isLoading={transactionsLoading}
          filters={ledgerFilters}
          hideAccountColumn={true}
          onFilterChange={(newFilters) => setLedgerFilters(newFilters)}
          onPageChange={(page) => setLedgerFilters((prev) => ({ ...prev, page }))}
          canReverse={canReverseTx}
          onReverseClick={(tx) => setReversingTx(tx)}
        />
      </div>

      {/* Dialogs */}
      {account && (
        <>
          <EditAccountDialog
            account={account}
            open={isEditOpen}
            onClose={() => setIsEditOpen(false)}
          />

          <CloseAccountDialog
            account={account}
            open={isCloseOpen}
            onClose={() => setIsCloseOpen(false)}
            onSuccess={() => {
              // Refresh page or let query invalidation handle it
            }}
          />

          <RecordTransactionDialog
            open={isRecordOpen}
            defaultAccountId={account.id}
            onClose={() => setIsRecordOpen(false)}
          />

          <ReverseTransactionDialog
            transaction={reversingTx}
            open={Boolean(reversingTx)}
            onClose={() => setReversingTx(null)}
          />
        </>
      )}
    </div>
  );
};
