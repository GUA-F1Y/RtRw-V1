<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\File\Enums\FileVisibility;
use App\Domain\File\Models\StoredFile;
use App\Domain\File\Services\FileStorage;
use App\Domain\Finance\Actions\AttachTransactionEvidenceAction;
use App\Domain\Finance\Actions\RecordFinancialTransactionAction;
use App\Domain\Finance\Actions\ReverseFinancialTransactionAction;
use App\Domain\Finance\DTOs\RecordFinancialTransactionInput;
use App\Domain\Finance\DTOs\ReverseFinancialTransactionInput;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\FinancialTransaction;
use App\Domain\Finance\Queries\GetFinancialTransactionQuery;
use App\Domain\Finance\Queries\ListFinancialTransactionsQuery;
use App\Domain\Finance\Services\FinancialDocumentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\AttachTransactionEvidenceRequest;
use App\Http\Requests\Finance\ListFinancialTransactionsRequest;
use App\Http\Requests\Finance\RecordFinancialTransactionRequest;
use App\Http\Requests\Finance\ReverseFinancialTransactionRequest;
use App\Http\Resources\FinancialTransactionResource;
use App\Http\Resources\StoredFileResource;
use App\Support\Api\ApiResponse;
use App\Support\Api\Contracts\QueryContract;
use App\Support\Api\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialTransactionController extends Controller
{
    public function __construct(
        private readonly ListFinancialTransactionsQuery $listQuery,
        private readonly GetFinancialTransactionQuery $getQuery,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(ListFinancialTransactionsRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('viewAny', FinancialTransaction::class);

        $sort = QueryContract::parseSort($request->query('sort'), QueryContract::FINANCE_TRANSACTION);
        $perPage = QueryContract::resolvePerPage($request->query('per_page'));

        $paginator = $this->listQuery->execute(
            $user,
            $request->validated(),
            $sort,
            $perPage
        );

        return ApiResponse::paginated($paginator, fn (FinancialTransaction $trx) => new FinancialTransactionResource($trx));
    }

    public function show(FinancialTransaction $financialTransaction): JsonResponse
    {
        $this->authorize('view', $financialTransaction);

        $transaction = $this->getQuery->execute($financialTransaction);

        return ApiResponse::success(new FinancialTransactionResource($transaction));
    }

    public function store(
        RecordFinancialTransactionRequest $request,
        RecordFinancialTransactionAction $action
    ): JsonResponse {
        $validated = $request->validated();

        /** @var CashAccount|null $account */
        $account = CashAccount::find($validated['cash_account_id']);
        if ($account === null) {
            throw new ApiException("Cash account '{$validated['cash_account_id']}' not found.", 422, 'cash_account_not_found');
        }

        $this->authorize('create', [FinancialTransaction::class, $account]);

        $input = new RecordFinancialTransactionInput(
            cashAccountId: $validated['cash_account_id'],
            categoryId: $validated['category_id'],
            type: TransactionType::from($validated['type']),
            amount: (int) $validated['amount'],
            transactionDate: $validated['transaction_date'],
            description: $validated['description'],
            referenceNumber: $validated['reference_number'] ?? null,
            evidenceFileId: $validated['evidence_file_id'] ?? null,
        );

        $transaction = $action->execute($input, $request->user());
        $loaded = $this->getQuery->execute($transaction);

        return ApiResponse::success(new FinancialTransactionResource($loaded), status: 201);
    }

    public function reverse(
        FinancialTransaction $financialTransaction,
        ReverseFinancialTransactionRequest $request,
        ReverseFinancialTransactionAction $action
    ): JsonResponse {
        $this->authorize('reverse', $financialTransaction);

        $validated = $request->validated();
        $input = new ReverseFinancialTransactionInput(
            transactionId: $financialTransaction->id,
            reversalReason: $validated['reversal_reason'],
            transactionDate: $validated['transaction_date'] ?? null,
        );

        $reversal = $action->execute($input, $request->user());
        $loaded = $this->getQuery->execute($reversal);

        return ApiResponse::success(new FinancialTransactionResource($loaded), status: 201);
    }

    /**
     * Generate and download transaction document (Receipt / Voucher / Reversal).
     */
    public function document(
        FinancialTransaction $financialTransaction,
        Request $request,
        FinancialDocumentService $documentService
    ): Response {
        $this->authorize('downloadDocument', $financialTransaction);

        $format = mb_strtolower((string) $request->query('format', 'pdf'));

        if ($format === 'html') {
            $html = $documentService->renderHtml($financialTransaction, $request->user());

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $pdf = $documentService->generatePdf($financialTransaction, $request->user());
        $filename = $documentService->getFilename($financialTransaction, 'pdf');
        $disposition = $request->query('disposition') === 'attachment' ? 'attachment' : 'inline';

        $this->auditLogger->record(
            actor: $request->user(),
            event: 'finance.transaction.document_downloaded',
            description: "Downloaded financial document {$filename} for transaction {$financialTransaction->id}",
            subject: $financialTransaction,
            scope: $financialTransaction->scopeRef(),
            properties: [
                'transaction_id' => $financialTransaction->id,
                'filename' => $filename,
                'format' => 'pdf',
            ]
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * List all evidence files associated with the transaction.
     */
    public function listEvidence(FinancialTransaction $financialTransaction): JsonResponse
    {
        $this->authorize('viewEvidence', $financialTransaction);

        $evidenceFiles = $financialTransaction->allEvidenceFiles();

        return ApiResponse::success(StoredFileResource::collection($evidenceFiles));
    }

    /**
     * Attach extra evidence file to an existing transaction without mutating the ledger row.
     */
    public function attachEvidence(
        FinancialTransaction $financialTransaction,
        AttachTransactionEvidenceRequest $request,
        AttachTransactionEvidenceAction $action,
        FileStorage $fileStorage
    ): JsonResponse {
        $this->authorize('attachEvidence', $financialTransaction);

        if ($request->hasFile('file')) {
            $storedFile = $fileStorage->store(
                file: $request->file('file'),
                scope: $financialTransaction->scopeRef(),
                owner: $request->user(),
                visibility: FileVisibility::Private,
                category: 'finance_evidence'
            );
        } else {
            /** @var StoredFile $storedFile */
            $storedFile = StoredFile::query()->findOrFail((string) $request->validated('file_id'));
            $this->authorize('view', $storedFile);
        }

        $action->execute($financialTransaction, $storedFile, $request->user());

        $loaded = $this->getQuery->execute($financialTransaction);

        return ApiResponse::success(new FinancialTransactionResource($loaded), status: 201);
    }

    /**
     * Download or view a specific evidence file associated with the transaction.
     */
    public function downloadEvidence(
        FinancialTransaction $financialTransaction,
        StoredFile $file,
        Request $request
    ): StreamedResponse {
        $this->authorize('viewEvidence', $financialTransaction);

        // Verify the file is actually associated with this transaction
        $isAssociated = ($financialTransaction->evidence_file_id === $file->id)
            || $financialTransaction->attachments()->where('file_id', $file->id)->exists();

        if (! $isAssociated) {
            throw new ApiException('The requested file is not associated with this transaction.', 404, 'evidence_not_found');
        }

        // Verify tenant scope boundaries
        if ($file->organization_id !== $financialTransaction->organization_id) {
            throw new ApiException('Tenant boundary violation.', 403, 'organization_mismatch');
        }

        $disk = Storage::disk($file->disk);
        if (! $disk->exists($file->path)) {
            throw new ApiException('File not found in storage.', 404, 'file_not_found');
        }

        // Sanitize download filename
        $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $file->original_name) ?: 'evidence';
        $safeFilename = "{$safeBase}.{$file->extension}";

        $inlineAllowed = in_array($file->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true);
        $disposition = ($request->query('disposition') === 'inline' && $inlineAllowed) ? 'inline' : 'attachment';

        $this->auditLogger->record(
            actor: $request->user(),
            event: 'finance.transaction.evidence_downloaded',
            description: "Downloaded evidence {$safeFilename} for transaction {$financialTransaction->id}",
            subject: $financialTransaction,
            scope: $financialTransaction->scopeRef(),
            properties: [
                'transaction_id' => $financialTransaction->id,
                'file_id' => $file->id,
                'filename' => $safeFilename,
            ]
        );

        return response()->stream(
            function () use ($disk, $file): void {
                $stream = $disk->readStream($file->path);
                if ($stream === null || $stream === false) {
                    return;
                }
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => $file->mime_type,
                'Content-Disposition' => "{$disposition}; filename=\"{$safeFilename}\"",
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /**
     * Download or view the primary evidence file for this transaction.
     */
    public function downloadPrimaryEvidence(
        FinancialTransaction $financialTransaction,
        Request $request
    ): StreamedResponse {
        $this->authorize('viewEvidence', $financialTransaction);

        if ($financialTransaction->evidence_file_id === null || $financialTransaction->evidenceFile === null) {
            throw new ApiException('This transaction has no primary evidence file attached.', 404, 'evidence_not_found');
        }

        return $this->downloadEvidence($financialTransaction, $financialTransaction->evidenceFile, $request);
    }
}
