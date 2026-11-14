<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\ScopedQuery;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\FinancialTransaction;
use App\Domain\Identity\Models\User;
use App\Support\Api\Exceptions\ApiException;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FinanceCsvExportService
{
    private const MAX_EXPORT_DAYS = 366;
    private const MAX_EXPORT_ROWS = 10000;

    public function __construct(
        private readonly ScopedQuery $scopedQuery,
    ) {}

    /**
     * Generate a streamed CSV download of ledger transactions with formula injection protection.
     *
     * @param  array<string, mixed>  $filters
     */
    public function export(User $user, array $filters = []): StreamedResponse
    {
        $now = Carbon::now();
        $dateFromStr = ! empty($filters['date_from'])
            ? (string) $filters['date_from']
            : $now->copy()->startOfMonth()->toDateString();
        $dateToStr = ! empty($filters['date_to'])
            ? (string) $filters['date_to']
            : $now->copy()->endOfMonth()->toDateString();

        $carbonFrom = Carbon::parse($dateFromStr);
        $carbonTo = Carbon::parse($dateToStr);

        if ($carbonTo->lt($carbonFrom)) {
            throw new ApiException('Tanggal akhir laporan tidak boleh mendahului tanggal mulai.', 422, 'invalid_date_range');
        }

        if ($carbonFrom->diffInDays($carbonTo) > self::MAX_EXPORT_DAYS) {
            throw new ApiException(
                'Rentang tanggal ekspor melebihi batas maksimum 366 hari (1 tahun).',
                422,
                'export_range_too_large'
            );
        }

        // 1. Resolve permitted cash accounts
        $accountQuery = CashAccount::query();
        $accountQuery = $this->scopedQuery->constrain(
            $accountQuery,
            $user,
            PermissionKey::FinanceAccountView,
            [
                'organization' => 'organization_id',
                'rw' => 'rw_id',
                'rt' => 'rt_id',
            ]
        );

        if (! empty($filters['cash_account_id'])) {
            $accountQuery->where('id', $filters['cash_account_id']);
        }

        if (! empty($filters['rw_id'])) {
            $accountQuery->where('rw_id', $filters['rw_id']);
        }

        if (array_key_exists('rt_id', $filters)) {
            if ($filters['rt_id'] === null || $filters['rt_id'] === 'null' || $filters['rt_id'] === '') {
                $accountQuery->whereNull('rt_id');
            } else {
                $accountQuery->where('rt_id', $filters['rt_id']);
            }
        }

        $accountIds = $accountQuery->pluck('id')->all();

        // 2. Determine what to include
        $typeFilter = ! empty($filters['type']) ? strtoupper((string) $filters['type']) : null;
        $includeTransactions = empty($typeFilter) || in_array($typeFilter, ['INCOME', 'EXPENSE'], true);
        $includeTransfers = empty($filters['category_id']) && (empty($typeFilter) || $typeFilter === 'TRANSFER');

        // 3. Build transaction query
        $txQuery = FinancialTransaction::query()
            ->with(['account.rt', 'category', 'recorder:id,name', 'reversal:id,original_transaction_id'])
            ->whereIn('cash_account_id', $accountIds)
            ->where('transaction_date', '>=', $dateFromStr)
            ->where('transaction_date', '<=', $dateToStr);

        if (! empty($filters['category_id'])) {
            $txQuery->where('category_id', $filters['category_id']);
        }

        if (! empty($typeFilter) && $typeFilter !== 'TRANSFER') {
            $txQuery->where('type', $typeFilter);
        }

        if (isset($filters['is_reversal'])) {
            $txQuery->where('is_reversal', filter_var($filters['is_reversal'], FILTER_VALIDATE_BOOLEAN));
        }

        // 4. Build transfer query
        $transferQuery = \App\Domain\Finance\Models\CashTransfer::query()
            ->with(['sourceAccount.rt', 'destinationAccount.rt', 'recorder:id,name', 'reversal:id,original_transfer_id'])
            ->where(function ($q) use ($accountIds): void {
                $q->whereIn('source_cash_account_id', $accountIds)
                    ->orWhereIn('destination_cash_account_id', $accountIds);
            })
            ->where('transfer_date', '>=', $dateFromStr)
            ->where('transfer_date', '<=', $dateToStr);

        if (isset($filters['is_reversal'])) {
            $transferQuery->where('is_reversal', filter_var($filters['is_reversal'], FILTER_VALIDATE_BOOLEAN));
        }

        $txRowCount = $includeTransactions ? (clone $txQuery)->count() : 0;
        $transferRowCount = $includeTransfers ? (clone $transferQuery)->count() : 0;
        $totalRowCount = $txRowCount + $transferRowCount;

        if ($totalRowCount > self::MAX_EXPORT_ROWS) {
            throw new ApiException(
                "Jumlah baris ({$totalRowCount}) melebihi batas ekspor maksimum " . self::MAX_EXPORT_ROWS . " baris. Silakan persempit rentang tanggal.",
                422,
                'export_row_limit_exceeded'
            );
        }

        $txQuery->orderBy('transaction_date', 'desc')->orderBy('created_at', 'desc');
        $transferQuery->orderBy('transfer_date', 'desc')->orderBy('created_at', 'desc');

        $filename = sprintf('laporan-keuangan-%s-sd-%s.csv', $dateFromStr, $dateToStr);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($includeTransactions, $includeTransfers, $txQuery, $transferQuery, $accountIds): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Microsoft Excel on Windows
            fwrite($handle, "\xEF\xBB\xBF");

            // CSV Header
            fputcsv($handle, [
                'Tanggal',
                'Nomor Referensi',
                'Akun Kas',
                'Tingkat Kas',
                'Unit RT',
                'Kategori Pos',
                'Arah',
                'Nominal (Rp)',
                'Status Pembalikan',
                'Deskripsi',
                'Alasan Pembalikan',
                'Dicatat Oleh',
            ]);

            // Stream transactions if applicable
            if ($includeTransactions) {
                $txQuery->chunk(500, function ($transactions) use ($handle): void {
                    /** @var FinancialTransaction $tx */
                    foreach ($transactions as $tx) {
                        $account = $tx->account;
                        $scopeType = $account?->rt_id !== null ? 'RT' : 'RW';
                        $rtNumber = $account?->rt?->number !== null
                            ? 'RT ' . str_pad((string) $account->rt->number, 3, '0', STR_PAD_LEFT)
                            : '-';

                        $direction = $tx->type->value === 'INCOME' ? 'Pemasukan' : 'Pengeluaran';

                        if ($tx->is_reversal) {
                            $reversalStatus = 'Pembalikan (Reversal)';
                        } elseif ($tx->reversal !== null) {
                            $reversalStatus = 'Dibalikkan (Reversed)';
                        } else {
                            $reversalStatus = 'Normal';
                        }

                        $recorderName = $tx->recorder?->name ?? 'Sistem';

                        fputcsv($handle, [
                            $tx->transaction_date->format('Y-m-d'),
                            $this->sanitizeCell($tx->reference_number ?? '-'),
                            $this->sanitizeCell($account?->name ?? '-'),
                            $scopeType,
                            $rtNumber,
                            $this->sanitizeCell($tx->category?->name ?? '-'),
                            $direction,
                            (string) $tx->amount,
                            $reversalStatus,
                            $this->sanitizeCell($tx->description),
                            $this->sanitizeCell($tx->reversal_reason ?? '-'),
                            $this->sanitizeCell($recorderName),
                        ]);
                    }
                });
            }

            // Stream transfers if applicable
            if ($includeTransfers) {
                $transferQuery->chunk(500, function ($transfers) use ($handle, $accountIds): void {
                    /** @var \App\Domain\Finance\Models\CashTransfer $transfer */
                    foreach ($transfers as $transfer) {
                        $reversalStatus = $transfer->is_reversal
                            ? 'Pembalikan (Reversal)'
                            : ($transfer->reversal !== null ? 'Dibalikkan (Reversed)' : 'Normal');

                        $recorderName = $transfer->recorder?->name ?? 'Sistem';

                        // Check source account movement
                        if (in_array($transfer->source_cash_account_id, $accountIds, true)) {
                            $sourceAccount = $transfer->sourceAccount;
                            $scopeType = $sourceAccount?->rt_id !== null ? 'RT' : 'RW';
                            $rtNumber = $sourceAccount?->rt?->number !== null
                                ? 'RT ' . str_pad((string) $sourceAccount->rt->number, 3, '0', STR_PAD_LEFT)
                                : '-';

                            $destName = $transfer->destinationAccount?->name ?? 'Akun Tujuan';
                            $desc = "Transfer ke {$destName}: {$transfer->description}";

                            fputcsv($handle, [
                                $transfer->transfer_date->format('Y-m-d'),
                                $this->sanitizeCell($transfer->reference_number ?? '-'),
                                $this->sanitizeCell($sourceAccount?->name ?? '-'),
                                $scopeType,
                                $rtNumber,
                                'Transfer Internal',
                                'Transfer Keluar',
                                (string) $transfer->amount,
                                $reversalStatus,
                                $this->sanitizeCell($desc),
                                $this->sanitizeCell($transfer->reversal_reason ?? '-'),
                                $this->sanitizeCell($recorderName),
                            ]);
                        }

                        // Check destination account movement
                        if (in_array($transfer->destination_cash_account_id, $accountIds, true)) {
                            $destAccount = $transfer->destinationAccount;
                            $scopeType = $destAccount?->rt_id !== null ? 'RT' : 'RW';
                            $rtNumber = $destAccount?->rt?->number !== null
                                ? 'RT ' . str_pad((string) $destAccount->rt->number, 3, '0', STR_PAD_LEFT)
                                : '-';

                            $sourceName = $transfer->sourceAccount?->name ?? 'Akun Asal';
                            $desc = "Transfer dari {$sourceName}: {$transfer->description}";

                            fputcsv($handle, [
                                $transfer->transfer_date->format('Y-m-d'),
                                $this->sanitizeCell($transfer->reference_number ?? '-'),
                                $this->sanitizeCell($destAccount?->name ?? '-'),
                                $scopeType,
                                $rtNumber,
                                'Transfer Internal',
                                'Transfer Masuk',
                                (string) $transfer->amount,
                                $reversalStatus,
                                $this->sanitizeCell($desc),
                                $this->sanitizeCell($transfer->reversal_reason ?? '-'),
                                $this->sanitizeCell($recorderName),
                            ]);
                        }
                    }
                });
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Protect against CSV formula injection (DDE attacks) in Excel/LibreOffice.
     * Cells beginning with '=', '+', '-', '@', tab, or carriage return are prefixed with a single quote.
     */
    public function sanitizeCell(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $firstChar = $value[0];
        if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }
}
