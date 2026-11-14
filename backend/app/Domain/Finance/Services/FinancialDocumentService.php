<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\FinancialTransaction;
use App\Domain\Identity\Models\User;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;

final class FinancialDocumentService
{
    /**
     * Build view data array for the financial document template.
     *
     * @return array<string, mixed>
     */
    public function prepareData(FinancialTransaction $transaction, ?User $actor = null): array
    {
        $transaction->loadMissing([
            'organization',
            'rw',
            'rt',
            'account',
            'category',
            'recorder',
            'evidenceFile',
            'reversal',
            'originalTransaction',
        ]);

        $shortId = strtoupper(substr(str_replace('-', '', $transaction->id), 0, 8));
        $datePrefix = $transaction->transaction_date->format('Ym');

        if ($transaction->is_reversal) {
            $prefix = 'BKR';
            $documentTitle = 'Bukti Koreksi / Pembatalan Kas';
        } elseif ($transaction->type === TransactionType::Income) {
            $prefix = 'KWT';
            $documentTitle = 'Kwitansi Penerimaan Kas';
        } else {
            $prefix = 'BKK';
            $documentTitle = 'Voucher Pengeluaran Kas';
        }

        $documentNumber = "{$prefix}/{$datePrefix}/{$shortId}";

        $scopeParts = [];
        if ($transaction->rt?->number !== null) {
            $scopeParts[] = 'RT '.$transaction->rt->number;
        }
        if ($transaction->rw?->number !== null) {
            $scopeParts[] = 'RW '.$transaction->rw->number;
        }
        $scopeLabel = implode(' / ', $scopeParts);

        $evidenceSummary = null;
        if ($transaction->evidenceFile !== null) {
            $evidenceSummary = [
                'name' => $transaction->evidenceFile->original_name.'.'.$transaction->evidenceFile->extension,
                'size' => number_format($transaction->evidenceFile->size_bytes / 1024, 1).' KB',
                'mime' => $transaction->evidenceFile->mime_type,
            ];
        }

        $isReversed = $transaction->isReversed();
        $reversalTrx = $isReversed ? $transaction->reversal : null;

        $origTrx = $transaction->originalTransaction;

        $indonesianMonths = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $formatDate = function (Carbon|\DateTimeInterface|string|null $date) use ($indonesianMonths): string {
            if ($date === null) {
                return '-';
            }
            $c = $date instanceof Carbon ? $date : Carbon::parse($date);

            return $c->format('d').' '.$indonesianMonths[(int) $c->format('n')].' '.$c->format('Y');
        };

        $formatDateTime = function (Carbon|\DateTimeInterface|string|null $date) use ($indonesianMonths): string {
            if ($date === null) {
                return '-';
            }
            $c = $date instanceof Carbon ? $date : Carbon::parse($date);

            return $c->format('d').' '.$indonesianMonths[(int) $c->format('n')].' '.$c->format('Y H:i:s').' WIB';
        };

        return [
            'documentNumber' => $documentNumber,
            'documentTitle' => $documentTitle,
            'isReversal' => (bool) $transaction->is_reversal,
            'isIncome' => $transaction->isIncome(),
            'isReversed' => $isReversed,
            'reversalTransactionId' => $reversalTrx?->id,
            'reversalDate' => $formatDate($reversalTrx?->transaction_date),
            'organizationName' => $transaction->organization?->name ?? 'Pengurus Lingkungan',
            'scopeLabel' => $scopeLabel,
            'accountName' => $transaction->account?->name ?? 'Kas Umum',
            'transactionDateFormatted' => $formatDate($transaction->transaction_date),
            'amount' => (int) $transaction->amount,
            'amountWords' => Terbilang::rupiah($transaction->amount),
            'description' => $transaction->description,
            'categoryName' => $transaction->category?->name ?? '-',
            'referenceNumber' => $transaction->reference_number,
            'originalTransactionId' => $origTrx?->id,
            'originalDateFormatted' => $formatDate($origTrx?->transaction_date),
            'originalAmount' => (int) ($origTrx?->amount ?? 0),
            'originalDescription' => $origTrx?->description ?? '-',
            'reversalReason' => $transaction->reversal_reason,
            'evidenceSummary' => $evidenceSummary,
            'recorderName' => $transaction->recorder?->name ?? 'Petugas Sistem',
            'recorderEmail' => $transaction->recorder?->email ?? '-',
            'createdAtFormatted' => $formatDateTime($transaction->created_at),
            'printedAtFormatted' => $formatDateTime(Carbon::now()),
            'traceabilityId' => $transaction->id,
        ];
    }

    /**
     * Render HTML document for browser display or printing.
     */
    public function renderHtml(FinancialTransaction $transaction, ?User $actor = null): string
    {
        $data = $this->prepareData($transaction, $actor);

        return view('finance.document', $data)->render();
    }

    /**
     * Generate true PDF binary stream via Dompdf.
     */
    public function generatePdf(FinancialTransaction $transaction, ?User $actor = null): string
    {
        $html = $this->renderHtml($transaction, $actor);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false); // Block untrusted remote requests
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Deterministic, safe filename for document download.
     */
    public function getFilename(FinancialTransaction $transaction, string $extension = 'pdf'): string
    {
        $type = $transaction->is_reversal ? 'koreksi' : ($transaction->isIncome() ? 'kwitansi' : 'voucher');
        $date = $transaction->transaction_date->format('Ymd');
        $shortId = substr(str_replace('-', '', $transaction->id), 0, 8);

        return Str::slug("{$type}-{$date}-{$shortId}").".{$extension}";
    }
}
