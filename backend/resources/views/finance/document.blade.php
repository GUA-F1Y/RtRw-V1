<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $documentTitle }} - {{ $documentNumber }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1f2937;
            background: #ffffff;
            font-size: 13px;
            line-height: 1.5;
            padding: 30px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #e5e7eb;
            padding: 32px;
            border-radius: 8px;
            position: relative;
        }
        .header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .org-header {
            display: table;
            width: 100%;
        }
        .org-info {
            display: table-cell;
            vertical-align: top;
            width: 60%;
        }
        .org-name {
            font-size: 18px;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .org-scope {
            font-size: 14px;
            font-weight: 600;
            color: #4b5563;
            margin-top: 2px;
        }
        .doc-badge {
            display: table-cell;
            vertical-align: top;
            text-align: right;
            width: 40%;
        }
        .doc-title {
            font-size: 16px;
            font-weight: bold;
            color: {{ $isReversal ? '#dc2626' : ($isIncome ? '#059669' : '#d97706') }};
            text-transform: uppercase;
        }
        .doc-number {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            font-weight: bold;
            color: #374151;
            margin-top: 4px;
        }
        .status-banner {
            background-color: #fee2e2;
            border: 1px solid #ef4444;
            color: #991b1b;
            padding: 10px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .details-table th, .details-table td {
            padding: 8px 12px;
            vertical-align: top;
            border-bottom: 1px solid #f3f4f6;
        }
        .details-table th {
            width: 25%;
            color: #6b7280;
            font-weight: 500;
            text-align: left;
            background-color: #f9fafb;
        }
        .details-table td {
            width: 75%;
            color: #111827;
        }
        .amount-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .amount-label {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: bold;
            color: #64748b;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .amount-numeral {
            font-size: 22px;
            font-weight: bold;
            color: {{ $isReversal ? '#dc2626' : ($isIncome ? '#059669' : '#b45309') }};
        }
        .amount-words {
            font-size: 13px;
            font-style: italic;
            color: #334155;
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px dashed #cbd5e1;
        }
        .signatures {
            margin-top: 40px;
            display: table;
            width: 100%;
        }
        .sig-col {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
        .sig-title {
            font-size: 12px;
            color: #4b5563;
            margin-bottom: 60px;
        }
        .sig-line {
            font-weight: 600;
            color: #111827;
            border-top: 1px solid #9ca3af;
            display: inline-block;
            min-width: 180px;
            padding-top: 4px;
        }
        .sig-sub {
            font-size: 11px;
            color: #6b7280;
        }
        .footer {
            margin-top: 36px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            font-size: 10px;
            color: #9ca3af;
            text-align: center;
            line-height: 1.4;
        }
        @media print {
            body {
                padding: 0;
            }
            .container {
                border: none;
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        @if ($isReversed)
            <div class="status-banner">
                PERINGATAN: Transaksi ini telah DIBATALKAN / DIKOREKSI melalui transaksi pembalik ID: {{ $reversalTransactionId }} pada tanggal {{ $reversalDate }}. Dokumen ini dicetak untuk keperluan arsip historis.
            </div>
        @elseif ($isReversal)
            <div class="status-banner" style="background-color: #fef2f2; border-color: #f87171; color: #991b1b;">
                DOKUMEN KOREKSI / REVERSAL: Dokumen ini diterbitkan sebagai pembatalan sah atas Transaksi Asli ID: {{ $originalTransactionId }}.
            </div>
        @endif

        <div class="header">
            <div class="org-header">
                <div class="org-info">
                    <div class="org-name">{{ $organizationName }}</div>
                    @if ($scopeLabel)
                        <div class="org-scope">{{ $scopeLabel }}</div>
                    @endif
                    <div style="font-size: 11px; color: #6b7280; margin-top: 4px;">
                        Buku Kas: {{ $accountName }}
                    </div>
                </div>
                <div class="doc-badge">
                    <div class="doc-title">{{ $documentTitle }}</div>
                    <div class="doc-number">No: {{ $documentNumber }}</div>
                    <div style="font-size: 11px; color: #6b7280; margin-top: 2px;">
                        Tanggal Trx: {{ $transactionDateFormatted }}
                    </div>
                </div>
            </div>
        </div>

        <div class="amount-box">
            <div class="amount-label">Jumlah Uang:</div>
            <div class="amount-numeral">Rp {{ number_format($amount, 0, ',', '.') }}</div>
            <div class="amount-words">Terbilang: <strong>{{ $amountWords }}</strong></div>
        </div>

        <table class="details-table">
            <tr>
                <th>Uraian / Deskripsi</th>
                <td><strong>{{ $description }}</strong></td>
            </tr>
            <tr>
                <th>Kategori Keuangan</th>
                <td>{{ $categoryName }}</td>
            </tr>
            @if ($referenceNumber)
                <tr>
                    <th>Nomor Referensi</th>
                    <td>{{ $referenceNumber }}</td>
                </tr>
            @endif
            @if ($isReversal)
                <tr>
                    <th>Transaksi Asli</th>
                    <td>
                        ID: {{ $originalTransactionId }}<br>
                        Tanggal: {{ $originalDateFormatted }} | Jumlah: Rp {{ number_format($originalAmount, 0, ',', '.') }}<br>
                        Uraian Asli: {{ $originalDescription }}
                    </td>
                </tr>
                <tr>
                    <th>Alasan Koreksi</th>
                    <td><span style="color: #b91c1c; font-weight: 500;">{{ $reversalReason }}</span></td>
                </tr>
            @endif
            @if ($evidenceSummary)
                <tr>
                    <th>Bukti Pendukung</th>
                    <td>
                        {{ $evidenceSummary['name'] }} ({{ $evidenceSummary['size'] }})
                    </td>
                </tr>
            @endif
            <tr>
                <th>Pencatat Transaksi</th>
                <td>{{ $recorderName }} ({{ $recorderEmail }})</td>
            </tr>
            <tr>
                <th>Waktu Pencatatan</th>
                <td>{{ $createdAtFormatted }}</td>
            </tr>
        </table>

        <div class="signatures">
            <div class="sig-col">
                <div class="sig-title">Dicatat Oleh:</div>
                <div class="sig-line">{{ $recorderName }}</div>
                <div class="sig-sub">Petugas Pencatat</div>
            </div>
            <div class="sig-col">
                <div class="sig-title">Mengetahui / Memverifikasi:</div>
                <div class="sig-line">&nbsp;</div>
                <div class="sig-sub">Pengurus Keuangan / Bendahara</div>
            </div>
        </div>

        <div class="footer">
            Dokumen ini merupakan catatan transaksi internal aplikasi RTRW yang tercatat secara sah pada buku kas digital dan bukan merupakan bukti transfer bank atau konfirmasi pihak ketiga.<br>
            Dicetak pada: {{ $printedAtFormatted }} &bull; Traceability ID: {{ $traceabilityId }}
        </div>
    </div>
</body>
</html>
