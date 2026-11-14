import React from 'react';
import { Link } from 'react-router-dom';
import { Badge } from '../../../components/ui/Badge.tsx';
import { Button } from '../../../components/ui/Button.tsx';
import type { CashAccount } from '../../../types/finance.ts';
import { formatRupiah } from '../utils/currency.ts';

interface AccountCardProps {
  account: CashAccount;
  onEdit?: (account: CashAccount) => void;
  onCloseAccount?: (account: CashAccount) => void;
  onRecordTransaction?: (account: CashAccount) => void;
  canManage?: boolean;
  canRecord?: boolean;
}

export const AccountCard: React.FC<AccountCardProps> = ({
  account,
  onEdit,
  onCloseAccount,
  onRecordTransaction,
  canManage = false,
  canRecord = false,
}) => {
  const isClosed = account.status === 'CLOSED';
  const isRtScope = Boolean(account.rt_id);

  // Format scope label
  const scopeLabel = isRtScope
    ? `RT ${account.rt?.rt_number ? String(account.rt.rt_number).padStart(3, '0') : account.rt?.name || 'Unit RT'}`
    : `RW ${account.rw?.rw_number ? String(account.rw.rw_number).padStart(3, '0') : account.rw?.name || 'Kas Utama'}`;

  return (
    <div
      data-testid={`account-card-${account.id}`}
      className={`rounded-xl border transition-all duration-200 p-5 flex flex-col justify-between ${
        isClosed
          ? 'bg-slate-900/40 border-slate-800/60 opacity-80'
          : 'bg-slate-900/80 border-slate-800 hover:border-slate-700 shadow-xl shadow-slate-950/40'
      }`}
    >
      <div>
        {/* Header badges */}
        <div className="flex items-center justify-between gap-2 mb-3">
          <div className="flex items-center gap-2">
            <Badge variant={isRtScope ? 'blue' : 'indigo'} size="sm">
              {scopeLabel}
            </Badge>
            {account.account_number && (
              <span className="text-[11px] font-mono text-slate-400 bg-slate-800 px-2 py-0.5 rounded border border-slate-700/60">
                {account.account_number}
              </span>
            )}
          </div>
          <Badge variant={isClosed ? 'slate' : 'emerald'} size="sm">
            {isClosed ? 'CLOSED' : 'ACTIVE'}
          </Badge>
        </div>

        {/* Title & Description */}
        <h3 className="text-base font-semibold text-white tracking-tight">
          <Link
            to={`/app/finance/accounts/${account.id}`}
            className="hover:text-emerald-400 transition-colors"
          >
            {account.name}
          </Link>
        </h3>
        {account.description && (
          <p className="mt-1 text-xs text-slate-400 line-clamp-2">
            {account.description}
          </p>
        )}

        {/* Balances Section */}
        <div className="mt-4 pt-4 border-t border-slate-800/80 grid grid-cols-2 gap-3">
          <div>
            <div className="text-[11px] text-slate-400 font-medium">Saldo Saat Ini</div>
            <div className={`text-lg font-bold tracking-tight mt-0.5 ${isClosed ? 'text-slate-400' : 'text-emerald-400'}`}>
              {formatRupiah(account.current_balance)}
            </div>
          </div>
          <div>
            <div className="text-[11px] text-slate-500 font-medium">Saldo Awal</div>
            <div className="text-sm font-semibold text-slate-300 mt-1">
              {formatRupiah(account.opening_balance)}
            </div>
          </div>
        </div>

        {/* Closed reason notice if closed */}
        {isClosed && account.closed_reason && (
          <div className="mt-3 p-2 rounded bg-rose-950/30 border border-rose-900/30 text-[11px] text-rose-300">
            <span className="font-medium">Alasan Penutupan:</span> {account.closed_reason}
          </div>
        )}
      </div>

      {/* Action Footer */}
      <div className="mt-5 pt-3 border-t border-slate-800/80 flex items-center justify-between gap-2">
        <Link
          to={`/app/finance/accounts/${account.id}`}
          className="text-xs font-medium text-emerald-400 hover:text-emerald-300 inline-flex items-center gap-1 transition-colors"
        >
          <span>Detail & Buku Kas</span>
          <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
          </svg>
        </Link>

        <div className="flex items-center gap-1.5">
          {!isClosed && canRecord && onRecordTransaction && (
            <Button
              type="button"
              variant="primary"
              size="sm"
              onClick={() => onRecordTransaction(account)}
              title="Catat Mutasi Kas"
              className="px-2.5 py-1 text-xs"
            >
              + Catat
            </Button>
          )}

          {canManage && (
            <>
              {onEdit && (
                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  onClick={() => onEdit(account)}
                  title="Ubah info akun"
                  className="px-2 py-1 text-xs text-slate-300"
                >
                  Edit
                </Button>
              )}
              {!isClosed && onCloseAccount && (
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => onCloseAccount(account)}
                  title="Tutup akun kas"
                  className="px-2 py-1 text-xs text-rose-400 hover:text-rose-300 border-rose-900/50 hover:bg-rose-950/40"
                >
                  Tutup
                </Button>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
};
