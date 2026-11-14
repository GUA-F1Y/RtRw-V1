<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\File\Enums\FileVisibility;
use App\Domain\File\Models\Attachment;
use App\Domain\File\Models\StoredFile;
use App\Domain\File\Services\FileStorage;
use App\Domain\Finance\Enums\CashAccountStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\FinanceCategory;
use App\Domain\Finance\Models\FinancialTransaction;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinanceEvidenceAndDocumentTest extends TestCase
{
    private Organization $orgA;
    private Organization $orgB;
    private Rw $rwA1;
    private Rw $rwA2;
    private Rw $rwB1;
    private Rt $rtA1_1;
    private Rt $rtA1_2;
    private Rt $rtB1_1;

    private CashAccount $accountA;
    private CashAccount $accountB;
    private FinanceCategory $incomeCatA;
    private FinanceCategory $expenseCatA;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Storage::fake('public');

        $this->orgA = Organization::factory()->create(['name' => 'RW 01 Rukun Bersama']);
        $this->orgB = Organization::factory()->create(['name' => 'RW 02 Berbeda']);

        $this->rwA1 = Rw::factory()->create(['organization_id' => $this->orgA->id, 'number' => 1]);
        $this->rwA2 = Rw::factory()->create(['organization_id' => $this->orgA->id, 'number' => 2]);
        $this->rwB1 = Rw::factory()->create(['organization_id' => $this->orgB->id, 'number' => 1]);

        $this->rtA1_1 = Rt::factory()->create(['rw_id' => $this->rwA1->id, 'number' => 1]);
        $this->rtA1_2 = Rt::factory()->create(['rw_id' => $this->rwA1->id, 'number' => 2]);
        $this->rtB1_1 = Rt::factory()->create(['rw_id' => $this->rwB1->id, 'number' => 1]);

        $this->accountA = CashAccount::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'name' => 'Kas Operasional RT 01',
            'opening_balance' => 1000000,
            'status' => CashAccountStatus::Active,
        ]);

        $this->accountB = CashAccount::query()->create([
            'organization_id' => $this->orgB->id,
            'rw_id' => $this->rwB1->id,
            'rt_id' => $this->rtB1_1->id,
            'name' => 'Kas RT Organisasi Lain',
            'opening_balance' => 500000,
            'status' => CashAccountStatus::Active,
        ]);

        $this->incomeCatA = FinanceCategory::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'name' => 'Iuran Warga',
            'type' => TransactionType::Income,
            'is_active' => true,
        ]);

        $this->expenseCatA = FinanceCategory::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'name' => 'Perbaikan Lampu Jalan',
            'type' => TransactionType::Expense,
            'is_active' => true,
        ]);
    }

    private function createStoredFile(
        Organization $org,
        ?Rw $rw = null,
        ?Rt $rt = null,
        string $filename = 'receipt.pdf',
        string $mime = 'application/pdf',
        ?User $owner = null
    ): StoredFile {
        $storage = app(FileStorage::class);
        $uploaded = UploadedFile::fake()->create($filename, 150, $mime);

        $scope = $rt !== null
            ? \App\Domain\Authorization\ScopeRef::rt($org->id, $rw->id, $rt->id)
            : ($rw !== null ? \App\Domain\Authorization\ScopeRef::rw($org->id, $rw->id) : \App\Domain\Authorization\ScopeRef::organization($org->id));

        return $storage->store(
            file: $uploaded,
            scope: $scope,
            owner: $owner,
            visibility: FileVisibility::Private,
            category: 'finance_evidence'
        );
    }

    public function test_record_transaction_with_valid_evidence_file_id(): void
    {
        $actor = $this->actAsScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        $file = $this->createStoredFile($this->orgA, $this->rwA1, $this->rtA1_1, 'bukti_transfer.png', 'image/png', $actor);

        $response = $this->postJson('/api/v1/finance/transactions', [
            'cash_account_id' => $this->accountA->id,
            'category_id' => $this->incomeCatA->id,
            'type' => 'INCOME',
            'amount' => 250000,
            'transaction_date' => '2026-10-15',
            'description' => 'Iuran kebersihan bulan Oktober',
            'reference_number' => 'REF-2026-001',
            'evidence_file_id' => $file->id,
        ], [
            'Idempotency-Key' => 'test-idemp-evidence-001',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.evidence_file_id', $file->id)
            ->assertJsonPath('data.evidence_file.id', $file->id)
            ->assertJsonPath('data.evidence_file.mime_type', 'image/png');

        $this->assertDatabaseHas('financial_transactions', [
            'id' => $response->json('data.id'),
            'evidence_file_id' => $file->id,
            'amount' => 250000,
        ]);
    }

    public function test_record_transaction_rejects_cross_tenant_evidence_file(): void
    {
        $actor = $this->actAsScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        // File belongs to orgB
        $fileOrgB = $this->createStoredFile($this->orgB, $this->rwB1, $this->rtB1_1, 'foreign.pdf');

        $response = $this->postJson('/api/v1/finance/transactions', [
            'cash_account_id' => $this->accountA->id,
            'category_id' => $this->incomeCatA->id,
            'type' => 'INCOME',
            'amount' => 100000,
            'transaction_date' => '2026-10-15',
            'description' => 'Iuran ilegal dengan bukti org lain',
            'evidence_file_id' => $fileOrgB->id,
        ], [
            'Idempotency-Key' => 'test-idemp-cross-file',
        ]);

        $response->assertStatus(422);
    }

    public function test_record_transaction_rejects_cross_scope_evidence_file(): void
    {
        $actor = $this->actAsScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        // File belongs to RT 02, but account belongs to RT 01
        $fileRt2 = $this->createStoredFile($this->orgA, $this->rwA1, $this->rtA1_2, 'rt2_evidence.pdf');

        $response = $this->postJson('/api/v1/finance/transactions', [
            'cash_account_id' => $this->accountA->id,
            'category_id' => $this->incomeCatA->id,
            'type' => 'INCOME',
            'amount' => 100000,
            'transaction_date' => '2026-10-15',
            'description' => 'Iuran beda RT',
            'evidence_file_id' => $fileRt2->id,
        ], [
            'Idempotency-Key' => 'test-idemp-scope-mismatch',
        ]);

        $response->assertStatus(422);
    }

    public function test_attach_evidence_to_posted_transaction_via_file_id(): void
    {
        $actor = $this->actAsScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1);

        // Record initial transaction without evidence
        $trx = FinancialTransaction::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'cash_account_id' => $this->accountA->id,
            'category_id' => $this->expenseCatA->id,
            'type' => TransactionType::Expense,
            'amount' => 150000,
            'transaction_date' => '2026-10-15',
            'description' => 'Pembelian kabel penerangan',
            'recorded_by' => $actor->id,
        ]);

        $newFile = $this->createStoredFile($this->orgA, $this->rwA1, $this->rtA1_1, 'nota_toko_listrik.jpg', 'image/jpeg', $actor);

        $response = $this->postJson("/api/v1/finance/transactions/{$trx->id}/evidence", [
            'file_id' => $newFile->id,
        ], [
            'Idempotency-Key' => 'test-attach-001',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.id', $trx->id);

        $this->assertDatabaseHas('attachments', [
            'file_id' => $newFile->id,
            'attachable_type' => $trx->getMorphClass(),
            'attachable_id' => $trx->id,
            'collection' => 'evidence',
        ]);

        // Verify ledger row was not mutated
        $fresh = $trx->fresh();
        $this->assertNull($fresh->evidence_file_id);
    }

    public function test_attach_evidence_to_posted_transaction_via_direct_upload(): void
    {
        $actor = $this->actAsScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1);

        $trx = FinancialTransaction::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'cash_account_id' => $this->accountA->id,
            'category_id' => $this->expenseCatA->id,
            'type' => TransactionType::Expense,
            'amount' => 75000,
            'transaction_date' => '2026-10-15',
            'description' => 'Beli sapu dan serokan',
            'recorded_by' => $actor->id,
        ]);

        $uploaded = UploadedFile::fake()->create('struk_minimarket.pdf', 80, 'application/pdf');

        $response = $this->postJson("/api/v1/finance/transactions/{$trx->id}/evidence", [
            'file' => $uploaded,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('attachments', [
            'attachable_type' => $trx->getMorphClass(),
            'attachable_id' => $trx->id,
            'collection' => 'evidence',
        ]);
    }

    public function test_attach_evidence_rejects_duplicate_association(): void
    {
        $actor = $this->actAsScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        $file = $this->createStoredFile($this->orgA, $this->rwA1, $this->rtA1_1, 'primary.pdf', 'application/pdf', $actor);

        $trx = FinancialTransaction::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'cash_account_id' => $this->accountA->id,
            'category_id' => $this->incomeCatA->id,
            'type' => TransactionType::Income,
            'amount' => 500000,
            'transaction_date' => '2026-10-15',
            'description' => 'Iuran RW',
            'evidence_file_id' => $file->id,
            'recorded_by' => $actor->id,
        ]);

        // Attaching the file that is already primary evidence should be rejected
        $response = $this->postJson("/api/v1/finance/transactions/{$trx->id}/evidence", [
            'file_id' => $file->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_attach_evidence_denies_cross_scope_user(): void
    {
        // RT 02 user attempting to attach evidence to RT 01 transaction
        $this->actAsScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_2);

        $actor1 = User::factory()->create();
        $trx = FinancialTransaction::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'cash_account_id' => $this->accountA->id,
            'category_id' => $this->incomeCatA->id,
            'type' => TransactionType::Income,
            'amount' => 100000,
            'transaction_date' => '2026-10-15',
            'description' => 'Trx RT 01',
            'recorded_by' => $actor1->id,
        ]);

        $file = $this->createStoredFile($this->orgA, $this->rwA1, $this->rtA1_2, 'file.pdf');

        $response = $this->postJson("/api/v1/finance/transactions/{$trx->id}/evidence", [
            'file_id' => $file->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_list_evidence_returns_primary_and_appended_files(): void
    {
        $actor = $this->actAsScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        $file1 = $this->createStoredFile($this->orgA, $this->rwA1, $this->rtA1_1, 'nota_1.pdf', 'application/pdf', $actor);
        $file2 = $this->createStoredFile($this->orgA, $this->rwA1, $this->rtA1_1, 'nota_2.jpg', 'image/jpeg', $actor);

        $trx = FinancialTransaction::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'cash_account_id' => $this->accountA->id,
            'category_id' => $this->expenseCatA->id,
            'type' => TransactionType::Expense,
            'amount' => 300000,
            'transaction_date' => '2026-10-15',
            'description' => 'Belanja alat ronda',
            'evidence_file_id' => $file1->id,
            'recorded_by' => $actor->id,
        ]);

        $trx->attachments()->create([
            'file_id' => $file2->id,
            'collection' => 'evidence',
        ]);

        $response = $this->getJson("/api/v1/finance/transactions/{$trx->id}/evidence");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_download_evidence_verifies_association_and_scope(): void
    {
        $actor = $this->actAsScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        $file = $this->createStoredFile($this->orgA, $this->rwA1, $this->rtA1_1, 'kwitansi_asli.pdf', 'application/pdf', $actor);

        $trx = FinancialTransaction::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'cash_account_id' => $this->accountA->id,
            'category_id' => $this->incomeCatA->id,
            'type' => TransactionType::Income,
            'amount' => 500000,
            'transaction_date' => '2026-10-15',
            'description' => 'Iuran tahunan',
            'evidence_file_id' => $file->id,
            'recorded_by' => $actor->id,
        ]);

        $response = $this->get("/api/v1/finance/transactions/{$trx->id}/evidence/{$file->id}/download");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_download_evidence_rejects_unassociated_file(): void
    {
        $actor = $this->actAsScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        $unassociatedFile = $this->createStoredFile($this->orgA, $this->rwA1, $this->rtA1_1, 'unrelated.pdf', 'application/pdf', $actor);

        $trx = FinancialTransaction::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'cash_account_id' => $this->accountA->id,
            'category_id' => $this->incomeCatA->id,
            'type' => TransactionType::Income,
            'amount' => 50000,
            'transaction_date' => '2026-10-15',
            'description' => 'Trx tanpa bukti ini',
            'recorded_by' => $actor->id,
        ]);

        $response = $this->get("/api/v1/finance/transactions/{$trx->id}/evidence/{$unassociatedFile->id}/download");

        $response->assertStatus(404);
    }

    public function test_download_evidence_denies_cross_tenant_actor(): void
    {
        $actorA = User::factory()->create();
        $file = $this->createStoredFile($this->orgA, $this->rwA1, $this->rtA1_1, 'private_org_a.pdf', 'application/pdf', $actorA);

        $trx = FinancialTransaction::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'cash_account_id' => $this->accountA->id,
            'category_id' => $this->incomeCatA->id,
            'type' => TransactionType::Income,
            'amount' => 50000,
            'transaction_date' => '2026-10-15',
            'description' => 'Trx Org A',
            'evidence_file_id' => $file->id,
            'recorded_by' => $actorA->id,
        ]);

        // Org B actor attempts download
        $this->actAsScopedUser(RoleKey::KetuaRt, $this->orgB, $this->rwB1, $this->rtB1_1);

        $response = $this->get("/api/v1/finance/transactions/{$trx->id}/evidence/{$file->id}/download");

        $response->assertStatus(403);
    }

    public function test_download_primary_evidence(): void
    {
        $actor = $this->actAsScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        $file = $this->createStoredFile($this->orgA, $this->rwA1, $this->rtA1_1, 'primary.png', 'image/png', $actor);

        $trx = FinancialTransaction::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'cash_account_id' => $this->accountA->id,
            'category_id' => $this->incomeCatA->id,
            'type' => TransactionType::Income,
            'amount' => 120000,
            'transaction_date' => '2026-10-15',
            'description' => 'Iuran ronda',
            'evidence_file_id' => $file->id,
            'recorded_by' => $actor->id,
        ]);

        $response = $this->get("/api/v1/finance/transactions/{$trx->id}/evidence/download");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/png');
    }

    public function test_generate_income_receipt_pdf(): void
    {
        $actor = $this->actAsScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1);

        $trx = FinancialTransaction::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'cash_account_id' => $this->accountA->id,
            'category_id' => $this->incomeCatA->id,
            'type' => TransactionType::Income,
            'amount' => 1500000,
            'transaction_date' => '2026-10-15',
            'description' => 'Iuran sumbangan pembangunan portal jalan',
            'reference_number' => 'REF-KWT-01',
            'recorded_by' => $actor->id,
        ]);

        $response = $this->get("/api/v1/finance/transactions/{$trx->id}/document");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content, 'Output must be a valid PDF binary starting with %PDF-');
    }

    public function test_generate_expense_voucher_pdf(): void
    {
        $actor = $this->actAsScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1);

        $trx = FinancialTransaction::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'cash_account_id' => $this->accountA->id,
            'category_id' => $this->expenseCatA->id,
            'type' => TransactionType::Expense,
            'amount' => 750000,
            'transaction_date' => '2026-10-15',
            'description' => 'Honorarium penjaga malam 2 minggu',
            'reference_number' => 'VCH-EXP-02',
            'recorded_by' => $actor->id,
        ]);

        $response = $this->get("/api/v1/finance/transactions/{$trx->id}/document");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content);
    }

    public function test_generate_reversal_document_references_original_and_reason(): void
    {
        $actor = $this->actAsScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1);

        $original = FinancialTransaction::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'cash_account_id' => $this->accountA->id,
            'category_id' => $this->incomeCatA->id,
            'type' => TransactionType::Income,
            'amount' => 500000,
            'transaction_date' => '2026-10-10',
            'description' => 'Salah catat rekening',
            'recorded_by' => $actor->id,
        ]);

        $reversal = FinancialTransaction::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'cash_account_id' => $this->accountA->id,
            'category_id' => $this->incomeCatA->id,
            'type' => TransactionType::Expense,
            'amount' => 500000,
            'transaction_date' => '2026-10-15',
            'description' => 'Pembatalan transaksi salah rekening',
            'is_reversal' => true,
            'original_transaction_id' => $original->id,
            'reversal_reason' => 'Koreksi kesalahan input bendahara',
            'recorded_by' => $actor->id,
        ]);

        // Request HTML format to inspect exact contents
        $response = $this->get("/api/v1/finance/transactions/{$reversal->id}/document?format=html");

        $response->assertStatus(200);
        $html = $response->getContent();

        $this->assertStringContainsString('DOKUMEN KOREKSI / REVERSAL', $html);
        $this->assertStringContainsString($original->id, $html);
        $this->assertStringContainsString('Koreksi kesalahan input bendahara', $html);
        $this->assertStringContainsString('Lima Ratus Ribu Rupiah', $html);
    }

    public function test_original_document_remains_available_after_reversal_with_reversed_warning(): void
    {
        $actor = $this->actAsScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1);

        $original = FinancialTransaction::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'cash_account_id' => $this->accountA->id,
            'category_id' => $this->incomeCatA->id,
            'type' => TransactionType::Income,
            'amount' => 200000,
            'transaction_date' => '2026-10-01',
            'description' => 'Iuran warga',
            'recorded_by' => $actor->id,
        ]);

        $reversal = FinancialTransaction::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'cash_account_id' => $this->accountA->id,
            'category_id' => $this->incomeCatA->id,
            'type' => TransactionType::Expense,
            'amount' => 200000,
            'transaction_date' => '2026-10-05',
            'description' => 'Reversal iuran',
            'is_reversal' => true,
            'original_transaction_id' => $original->id,
            'reversal_reason' => 'Warga membatalkan setoran',
            'recorded_by' => $actor->id,
        ]);

        // Original transaction document should still be accessible
        $response = $this->get("/api/v1/finance/transactions/{$original->id}/document?format=html");

        $response->assertStatus(200);
        $html = $response->getContent();

        $this->assertStringContainsString('PERINGATAN: Transaksi ini telah DIBATALKAN / DIKOREKSI', $html);
        $this->assertStringContainsString($reversal->id, $html);
    }

    public function test_document_generation_escapes_untrusted_description(): void
    {
        $actor = $this->actAsScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1);

        $trx = FinancialTransaction::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'cash_account_id' => $this->accountA->id,
            'category_id' => $this->incomeCatA->id,
            'type' => TransactionType::Income,
            'amount' => 100000,
            'transaction_date' => '2026-10-15',
            'description' => '<script>alert("xss")</script> & <b>bold</b>',
            'recorded_by' => $actor->id,
        ]);

        $response = $this->get("/api/v1/finance/transactions/{$trx->id}/document?format=html");

        $response->assertStatus(200);
        $html = $response->getContent();

        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $html);
    }
}
