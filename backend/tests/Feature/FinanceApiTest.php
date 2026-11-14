<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\Finance\Enums\CashAccountStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\FinanceCategory;
use App\Domain\Finance\Models\FinancialTransaction;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinanceApiTest extends TestCase
{
    private Organization $orgA;
    private Organization $orgB;
    private Rw $rwA1;
    private Rw $rwA2;
    private Rw $rwB1;
    private Rt $rtA1_1;
    private Rt $rtA1_2;
    private Rt $rtB1_1;

    private CashAccount $rwAccountA1;
    private CashAccount $rtAccountA1_1;
    private CashAccount $rtAccountA1_2;
    private CashAccount $rtAccountB1_1;

    private FinanceCategory $incomeCategoryA;
    private FinanceCategory $expenseCategoryA;
    private FinanceCategory $incomeCategoryB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::factory()->create(['name' => 'Organisasi A']);
        $this->orgB = Organization::factory()->create(['name' => 'Organisasi B']);

        $this->rwA1 = Rw::factory()->create(['organization_id' => $this->orgA->id, 'number' => 1]);
        $this->rwA2 = Rw::factory()->create(['organization_id' => $this->orgA->id, 'number' => 2]);
        $this->rwB1 = Rw::factory()->create(['organization_id' => $this->orgB->id, 'number' => 1]);

        $this->rtA1_1 = Rt::factory()->create(['rw_id' => $this->rwA1->id, 'number' => 1]);
        $this->rtA1_2 = Rt::factory()->create(['rw_id' => $this->rwA1->id, 'number' => 2]);
        $this->rtB1_1 = Rt::factory()->create(['rw_id' => $this->rwB1->id, 'number' => 1]);

        // Accounts
        $this->rwAccountA1 = CashAccount::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => null,
            'name' => 'Kas RW 01 Induk',
            'account_number' => 'ACC-RW01',
            'description' => 'Rekening Operasional RW 01',
            'opening_balance' => 1000000,
            'status' => CashAccountStatus::Active,
        ]);

        $this->rtAccountA1_1 = CashAccount::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'name' => 'Kas RT 01 / RW 01',
            'account_number' => 'ACC-RT01-01',
            'description' => 'Rekening Kas Operasional RT 01',
            'opening_balance' => 500000,
            'status' => CashAccountStatus::Active,
        ]);

        $this->rtAccountA1_2 = CashAccount::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_2->id,
            'name' => 'Kas RT 02 / RW 01',
            'account_number' => 'ACC-RT01-02',
            'description' => 'Rekening Kas Operasional RT 02',
            'opening_balance' => 0,
            'status' => CashAccountStatus::Active,
        ]);

        $this->rtAccountB1_1 = CashAccount::query()->create([
            'organization_id' => $this->orgB->id,
            'rw_id' => $this->rwB1->id,
            'rt_id' => $this->rtB1_1->id,
            'name' => 'Kas RT 01 Org B',
            'account_number' => 'ACC-RT-B',
            'opening_balance' => 200000,
            'status' => CashAccountStatus::Active,
        ]);

        // Categories
        $this->incomeCategoryA = FinanceCategory::query()->create([
            'organization_id' => $this->orgA->id,
            'name' => 'Iuran Kebersihan',
            'type' => TransactionType::Income,
            'code' => 'INC-01',
            'is_active' => true,
        ]);

        $this->expenseCategoryA = FinanceCategory::query()->create([
            'organization_id' => $this->orgA->id,
            'name' => 'Perbaikan Lampu Jalan',
            'type' => TransactionType::Expense,
            'code' => 'EXP-01',
            'is_active' => true,
        ]);

        $this->incomeCategoryB = FinanceCategory::query()->create([
            'organization_id' => $this->orgB->id,
            'name' => 'Iuran Org B',
            'type' => TransactionType::Income,
            'code' => 'INC-B',
            'is_active' => true,
        ]);
    }

    public function test_cash_account_listing_respects_user_scope(): void
    {
        // 1. RT-A1-1 Bendahara should only see RT-A1-1 account
        $this->actAsScopedUser(RoleKey::BendaharaRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        $res = $this->getJson('/api/v1/finance/accounts');
        $res->assertOk();
        $data = $res->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($this->rtAccountA1_1->id, $data[0]['id']);
        $this->assertSame(500000, $data[0]['current_balance']);

        // 2. RW-A1 Bendahara should see RW-A1 account and all its child RT accounts (total 3)
        $this->actAsScopedUser(RoleKey::BendaharaRw, $this->orgA, $this->rwA1);
        $resRw = $this->getJson('/api/v1/finance/accounts');
        $resRw->assertOk();
        $dataRw = $resRw->json('data');
        $this->assertCount(3, $dataRw);
        $ids = array_column($dataRw, 'id');
        $this->assertContains($this->rwAccountA1->id, $ids);
        $this->assertContains($this->rtAccountA1_1->id, $ids);
        $this->assertContains($this->rtAccountA1_2->id, $ids);
        $this->assertNotContains($this->rtAccountB1_1->id, $ids);
    }

    public function test_cash_account_detail_and_summary_return_correct_breakdown(): void
    {
        $this->actAsScopedUser(RoleKey::KetuaRw, $this->orgA, $this->rwA1);

        // Record a transaction first
        FinancialTransaction::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => null,
            'cash_account_id' => $this->rwAccountA1->id,
            'category_id' => $this->incomeCategoryA->id,
            'type' => TransactionType::Income,
            'amount' => 250000,
            'transaction_date' => '2026-10-14',
            'description' => 'Iuran Sampah Warga',
            'recorded_by' => auth()->id(),
        ]);

        // Detail
        $resDetail = $this->getJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}");
        $resDetail->assertOk()
            ->assertJsonPath('data.id', $this->rwAccountA1->id)
            ->assertJsonPath('data.current_balance', 1250000);

        // Summary
        $resSummary = $this->getJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/summary");
        $resSummary->assertOk()
            ->assertJsonPath('data.opening_balance', 1000000)
            ->assertJsonPath('data.total_income', 250000)
            ->assertJsonPath('data.active_income', 250000)
            ->assertJsonPath('data.total_expense', 0)
            ->assertJsonPath('data.current_balance', 1250000)
            ->assertJsonPath('data.transactions_count', 1);
    }

    public function test_cash_account_detail_rejects_unauthorized_scope(): void
    {
        // RT-A1-1 trying to access RT-A1-2 account
        $this->actAsScopedUser(RoleKey::BendaharaRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        $res = $this->getJson("/api/v1/finance/accounts/{$this->rtAccountA1_2->id}");
        $res->assertForbidden();

        // RT-A1-1 trying to access Org B account
        $resB = $this->getJson("/api/v1/finance/accounts/{$this->rtAccountB1_1->id}");
        $resB->assertForbidden();
    }

    public function test_create_cash_account_enforces_idempotency_and_contract(): void
    {
        $this->actAsScopedUser(RoleKey::BendaharaRw, $this->orgA, $this->rwA1);

        $payload = [
            'rt_id' => null,
            'name' => 'Kas Pembangunan RW 01',
            'account_number' => 'ACC-RW01-BANGUN',
            'description' => 'Rekening Kas Khusus Pembangunan',
            'opening_balance' => 2000000,
        ];

        // 1. Missing Idempotency-Key returns 400
        $resNoIdemp = $this->postJson('/api/v1/finance/accounts', $payload);
        $resNoIdemp->assertStatus(400)
            ->assertJsonPath('error.code', 'idempotency_key_required');

        // 2. With unexpected field returns 422
        $idempKey = 'idemp-acc-create-' . Str::random(10);
        $invalidPayload = array_merge($payload, ['unwanted_hacker_field' => 'injection']);
        $resUnexpected = $this->withHeader('Idempotency-Key', $idempKey)
            ->postJson('/api/v1/finance/accounts', $invalidPayload);
        $resUnexpected->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['errors' => ['unwanted_hacker_field']]]);

        // 3. Successful creation with Idempotency-Key
        $resSuccess = $this->withHeader('Idempotency-Key', $idempKey)
            ->postJson('/api/v1/finance/accounts', $payload);
        $resSuccess->assertCreated()
            ->assertJsonPath('data.name', 'Kas Pembangunan RW 01')
            ->assertJsonPath('data.opening_balance', 2000000)
            ->assertJsonPath('data.current_balance', 2000000);

        $createdId = $resSuccess->json('data.id');

        // 4. Replaying identical request returns cached 201 response with Idempotent-Replayed header
        $resReplay = $this->withHeader('Idempotency-Key', $idempKey)
            ->postJson('/api/v1/finance/accounts', $payload);
        $resReplay->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('data.id', $createdId);

        // Verify only 1 account created
        $this->assertDatabaseHas('cash_accounts', ['id' => $createdId]);
        $this->assertSame(1, CashAccount::query()->where('name', 'Kas Pembangunan RW 01')->count());
    }

    public function test_update_cash_account_details_and_close_account(): void
    {
        $this->actAsScopedUser(RoleKey::BendaharaRw, $this->orgA, $this->rwA1);

        // Update details
        $resUpdate = $this->patchJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}", [
            'name' => 'Kas Operasional RW 01 Terkini',
            'description' => 'Deskripsi baru',
        ]);
        $resUpdate->assertOk()
            ->assertJsonPath('data.name', 'Kas Operasional RW 01 Terkini')
            ->assertJsonPath('data.description', 'Deskripsi baru');

        // Close account
        $resClose = $this->postJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/close", [
            'reason' => 'Penggabungan kas ke rekening baru',
        ]);
        $resClose->assertOk()
            ->assertJsonPath('data.status', 'CLOSED');

        $this->assertSame(CashAccountStatus::Closed, $this->rwAccountA1->fresh()->status);
    }

    public function test_finance_category_management_and_scoping(): void
    {
        $this->actAsScopedUser(RoleKey::BendaharaRw, $this->orgA, $this->rwA1);

        // List categories in Org A (should not see Org B)
        $resList = $this->getJson('/api/v1/finance/categories');
        $resList->assertOk();
        $cats = $resList->json('data');
        $this->assertCount(2, $cats);
        $codes = array_column($cats, 'code');
        $this->assertContains('INC-01', $codes);
        $this->assertContains('EXP-01', $codes);
        $this->assertNotContains('INC-B', $codes);

        // Create new category
        $resCreate = $this->postJson('/api/v1/finance/categories', [
            'name' => 'Iuran Keamanan RW',
            'type' => 'INCOME',
            'code' => 'INC-AMAN',
            'description' => 'Iuran ronda bulanan',
        ]);
        $resCreate->assertCreated()
            ->assertJsonPath('data.name', 'Iuran Keamanan RW')
            ->assertJsonPath('data.type', 'INCOME');

        // Filter by type
        $resFilter = $this->getJson('/api/v1/finance/categories?type=EXPENSE');
        $resFilter->assertOk();
        $this->assertCount(1, $resFilter->json('data'));
        $this->assertSame('EXP-01', $resFilter->json('data.0.code'));
    }

    public function test_record_transaction_validates_amounts_and_category_type(): void
    {
        $this->actAsScopedUser(RoleKey::BendaharaRw, $this->orgA, $this->rwA1);

        $idempKey = 'idemp-tx-record-' . Str::random(10);

        // 1. Zero or negative amount rejected
        $resZero = $this->withHeader('Idempotency-Key', $idempKey)
            ->postJson('/api/v1/finance/transactions', [
                'cash_account_id' => $this->rwAccountA1->id,
                'category_id' => $this->incomeCategoryA->id,
                'type' => 'INCOME',
                'amount' => 0,
                'transaction_date' => '2026-10-14',
                'description' => 'Uji amount nol',
            ]);
        $resZero->assertStatus(422);

        // 2. Category mismatch: EXPENSE transaction with INCOME category
        $resMismatch = $this->withHeader('Idempotency-Key', $idempKey)
            ->postJson('/api/v1/finance/transactions', [
                'cash_account_id' => $this->rwAccountA1->id,
                'category_id' => $this->incomeCategoryA->id,
                'type' => 'EXPENSE',
                'amount' => 50000,
                'transaction_date' => '2026-10-14',
                'description' => 'Uji kategori salah arah',
            ]);
        $resMismatch->assertStatus(422)
            ->assertJsonPath('error.code', 'category_type_mismatch');

        // 3. Successful recording of an INCOME transaction
        $resSuccess = $this->withHeader('Idempotency-Key', $idempKey)
            ->postJson('/api/v1/finance/transactions', [
                'cash_account_id' => $this->rwAccountA1->id,
                'category_id' => $this->incomeCategoryA->id,
                'type' => 'INCOME',
                'amount' => 750000,
                'transaction_date' => '2026-10-14',
                'description' => 'Pemasukan Kas Warga RW',
                'reference_number' => 'REF-INC-001',
            ]);
        $resSuccess->assertCreated()
            ->assertJsonPath('data.amount', 750000)
            ->assertJsonPath('data.type', 'INCOME')
            ->assertJsonPath('data.is_reversal', false);

        $txId = $resSuccess->json('data.id');

        // Verify account balance updated
        $resSummary = $this->getJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/summary");
        $resSummary->assertOk()
            ->assertJsonPath('data.current_balance', 1750000) // 1000000 + 750000
            ->assertJsonPath('data.active_income', 750000);

        // 4. Replaying identical request is idempotent
        $resReplay = $this->withHeader('Idempotency-Key', $idempKey)
            ->postJson('/api/v1/finance/transactions', [
                'cash_account_id' => $this->rwAccountA1->id,
                'category_id' => $this->incomeCategoryA->id,
                'type' => 'INCOME',
                'amount' => 750000,
                'transaction_date' => '2026-10-14',
                'description' => 'Pemasukan Kas Warga RW',
                'reference_number' => 'REF-INC-001',
            ]);
        $resReplay->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('data.id', $txId);

        // Still only 1 transaction in DB
        $this->assertSame(1, FinancialTransaction::query()->where('cash_account_id', $this->rwAccountA1->id)->count());
    }

    public function test_reversal_creates_compensating_entry_and_rejects_duplicate(): void
    {
        $this->actAsScopedUser(RoleKey::BendaharaRw, $this->orgA, $this->rwA1);

        // 1. Post original EXPENSE transaction of 300,000
        $tx = FinancialTransaction::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => null,
            'cash_account_id' => $this->rwAccountA1->id,
            'category_id' => $this->expenseCategoryA->id,
            'type' => TransactionType::Expense,
            'amount' => 300000,
            'transaction_date' => '2026-10-14',
            'description' => 'Beli Cat Pos Ronda',
            'recorded_by' => auth()->id(),
        ]);

        // Balance is 1000000 - 300000 = 700000
        $resSummary1 = $this->getJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/summary");
        $resSummary1->assertOk()
            ->assertJsonPath('data.current_balance', 700000);

        // 2. Reverse the transaction
        $idempKey = 'idemp-reverse-' . Str::random(10);
        $resReverse = $this->withHeader('Idempotency-Key', $idempKey)
            ->postJson("/api/v1/finance/transactions/{$tx->id}/reverse", [
                'reversal_reason' => 'Salah catat nominal kas pos ronda',
                'transaction_date' => '2026-10-14',
            ]);

        $resReverse->assertCreated()
            ->assertJsonPath('data.is_reversal', true)
            ->assertJsonPath('data.original_transaction_id', $tx->id)
            ->assertJsonPath('data.amount', 300000);

        $reversalId = $resReverse->json('data.id');

        // 3. Balance restored to 1,000,000
        $resSummary2 = $this->getJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/summary");
        $resSummary2->assertOk()
            ->assertJsonPath('data.current_balance', 1000000)
            ->assertJsonPath('data.reversed_expense', 300000)
            ->assertJsonPath('data.active_expense', 0)
            ->assertJsonPath('data.transactions_count', 2);

        // 4. Duplicate reversal attempt with new idempotency key must be rejected (409)
        $idempKey2 = 'idemp-reverse-dup-' . Str::random(10);
        $resDup = $this->withHeader('Idempotency-Key', $idempKey2)
            ->postJson("/api/v1/finance/transactions/{$tx->id}/reverse", [
                'reversal_reason' => 'Mencoba membalik kedua kalinya',
            ]);
        $resDup->assertStatus(409)
            ->assertJsonPath('error.code', 'transaction_already_reversed');

        // 5. Replaying original reversal idempotency key returns the cached 201 response
        $resReplay = $this->withHeader('Idempotency-Key', $idempKey)
            ->postJson("/api/v1/finance/transactions/{$tx->id}/reverse", [
                'reversal_reason' => 'Salah catat nominal kas pos ronda',
                'transaction_date' => '2026-10-14',
            ]);
        $resReplay->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('data.id', $reversalId);
    }

    public function test_transaction_listing_supports_filters_and_scope(): void
    {
        $this->actAsScopedUser(RoleKey::BendaharaRw, $this->orgA, $this->rwA1);

        // Seed 2 transactions in RW account, 1 in RT account
        FinancialTransaction::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => null,
            'cash_account_id' => $this->rwAccountA1->id,
            'category_id' => $this->incomeCategoryA->id,
            'type' => TransactionType::Income,
            'amount' => 100000,
            'transaction_date' => '2026-10-10',
            'description' => 'Iuran Warga Oktober',
            'reference_number' => 'REF-001',
            'recorded_by' => auth()->id(),
        ]);

        FinancialTransaction::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => null,
            'cash_account_id' => $this->rwAccountA1->id,
            'category_id' => $this->expenseCategoryA->id,
            'type' => TransactionType::Expense,
            'amount' => 50000,
            'transaction_date' => '2026-10-12',
            'description' => 'Beli Pulsa Listrik Pos',
            'reference_number' => 'REF-002',
            'recorded_by' => auth()->id(),
        ]);

        FinancialTransaction::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'cash_account_id' => $this->rtAccountA1_1->id,
            'category_id' => $this->incomeCategoryA->id,
            'type' => TransactionType::Income,
            'amount' => 80000,
            'transaction_date' => '2026-10-13',
            'description' => 'Iuran Kas RT 01',
            'reference_number' => 'REF-003',
            'recorded_by' => auth()->id(),
        ]);

        // 1. RW user lists transactions: sees all 3 within RW scope
        $resRw = $this->getJson('/api/v1/finance/transactions');
        $resRw->assertOk();
        $this->assertCount(3, $resRw->json('data'));

        // 2. Filter by type=EXPENSE
        $resExpense = $this->getJson('/api/v1/finance/transactions?type=EXPENSE');
        $resExpense->assertOk();
        $this->assertCount(1, $resExpense->json('data'));
        $this->assertSame('REF-002', $resExpense->json('data.0.reference_number'));

        // 3. Search description
        $resSearch = $this->getJson('/api/v1/finance/transactions?q=Pulsa');
        $resSearch->assertOk();
        $this->assertCount(1, $resSearch->json('data'));
        $this->assertSame('REF-002', $resSearch->json('data.0.reference_number'));

        // 4. Switch to RT-A1-1 Bendahara: should only see the 1 transaction in RT-A1-1
        $this->actAsScopedUser(RoleKey::BendaharaRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        $resRt = $this->getJson('/api/v1/finance/transactions');
        $resRt->assertOk();
        $this->assertCount(1, $resRt->json('data'));
        $this->assertSame('REF-003', $resRt->json('data.0.reference_number'));
    }

    public function test_cross_tenant_idor_protection_on_transactions(): void
    {
        // Create transaction in Org B
        $txOrgB = FinancialTransaction::query()->create([
            'organization_id' => $this->orgB->id,
            'rw_id' => $this->rwB1->id,
            'rt_id' => $this->rtB1_1->id,
            'cash_account_id' => $this->rtAccountB1_1->id,
            'category_id' => $this->incomeCategoryB->id,
            'type' => TransactionType::Income,
            'amount' => 500000,
            'transaction_date' => '2026-10-14',
            'description' => 'Pemasukan Kas Org B',
            'recorded_by' => User::factory()->create()->id,
        ]);

        // RT-A1-1 user attempts to view detail of Org B transaction
        $this->actAsScopedUser(RoleKey::BendaharaRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        $resDetail = $this->getJson("/api/v1/finance/transactions/{$txOrgB->id}");
        $resDetail->assertForbidden();

        // RT-A1-1 user attempts to reverse Org B transaction
        $idempKey = 'idemp-reverse-cross-' . Str::random(10);
        $resReverse = $this->withHeader('Idempotency-Key', $idempKey)
            ->postJson("/api/v1/finance/transactions/{$txOrgB->id}/reverse", [
                'reversal_reason' => 'Mencoba membalik transaksi milik org lain',
            ]);
        $resReverse->assertForbidden();
    }
}
