<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\Finance\Actions\RecordFinancialTransactionAction;
use App\Domain\Finance\Actions\ReverseFinancialTransactionAction;
use App\Domain\Finance\DTOs\RecordFinancialTransactionInput;
use App\Domain\Finance\DTOs\ReverseFinancialTransactionInput;
use App\Domain\Finance\Enums\CashAccountStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\FinanceCategory;
use App\Domain\Finance\Models\FinancialTransaction;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Tests\TestCase;

class FinanceReportApiTest extends TestCase
{
    private Organization $orgA;
    private Organization $orgB;
    private Rw $rwA1;
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
    private FinanceCategory $inactiveCategoryA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::factory()->create(['name' => 'Organisasi Laporan A']);
        $this->orgB = Organization::factory()->create(['name' => 'Organisasi Laporan B']);

        $this->rwA1 = Rw::factory()->create(['organization_id' => $this->orgA->id, 'number' => 1]);
        $this->rwB1 = Rw::factory()->create(['organization_id' => $this->orgB->id, 'number' => 1]);

        $this->rtA1_1 = Rt::factory()->create(['rw_id' => $this->rwA1->id, 'number' => 1]);
        $this->rtA1_2 = Rt::factory()->create(['rw_id' => $this->rwA1->id, 'number' => 2]);
        $this->rtB1_1 = Rt::factory()->create(['rw_id' => $this->rwB1->id, 'number' => 1]);

        // Accounts
        $this->rwAccountA1 = CashAccount::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => null,
            'name' => 'Kas Induk RW 01',
            'account_number' => 'ACC-RW01',
            'description' => 'Kas RW',
            'opening_balance' => 1000000,
            'status' => CashAccountStatus::Active,
        ]);

        $this->rtAccountA1_1 = CashAccount::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'name' => 'Kas RT 01',
            'account_number' => 'ACC-RT01',
            'description' => 'Kas RT 01',
            'opening_balance' => 500000,
            'status' => CashAccountStatus::Active,
        ]);

        $this->rtAccountA1_2 = CashAccount::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_2->id,
            'name' => 'Kas RT 02',
            'account_number' => 'ACC-RT02',
            'description' => 'Kas RT 02',
            'opening_balance' => 250000,
            'status' => CashAccountStatus::Active,
        ]);

        $this->rtAccountB1_1 = CashAccount::query()->create([
            'organization_id' => $this->orgB->id,
            'rw_id' => $this->rwB1->id,
            'rt_id' => $this->rtB1_1->id,
            'name' => 'Kas RT 01 Org B',
            'account_number' => 'ACC-RT-B',
            'description' => 'Kas RT B',
            'opening_balance' => 300000,
            'status' => CashAccountStatus::Active,
        ]);

        // Categories
        $this->incomeCategoryA = FinanceCategory::query()->create([
            'organization_id' => $this->orgA->id,
            'name' => 'Iuran Warga Bulanan',
            'type' => TransactionType::Income,
            'code' => 'INC-01',
            'is_active' => true,
        ]);

        $this->expenseCategoryA = FinanceCategory::query()->create([
            'organization_id' => $this->orgA->id,
            'name' => 'Pemeliharaan Fasilitas',
            'type' => TransactionType::Expense,
            'code' => 'EXP-01',
            'is_active' => true,
        ]);

        $this->inactiveCategoryA = FinanceCategory::query()->create([
            'organization_id' => $this->orgA->id,
            'name' => 'Pos Sumbangan Khusus (Arsip)',
            'type' => TransactionType::Income,
            'code' => 'INC-ARCHIVE',
            'is_active' => false,
        ]);
    }

    public function test_period_summary_computes_correct_opening_and_closing_balance(): void
    {
        $actor = $this->actAsScopedUser(RoleKey::BendaharaRw, $this->orgA, $this->rwA1);
        $recordAction = app(RecordFinancialTransactionAction::class);

        // Pre-period: 2026-09-15 Income Rp 200.000, 2026-09-20 Expense Rp 50.000
        $recordAction->execute(new RecordFinancialTransactionInput(
            cashAccountId: $this->rwAccountA1->id,
            categoryId: $this->incomeCategoryA->id,
            type: TransactionType::Income,
            amount: 200000,
            transactionDate: '2026-09-15',
            description: 'Pre-period income'
        ), $actor);

        $recordAction->execute(new RecordFinancialTransactionInput(
            cashAccountId: $this->rwAccountA1->id,
            categoryId: $this->expenseCategoryA->id,
            type: TransactionType::Expense,
            amount: 50000,
            transactionDate: '2026-09-20',
            description: 'Pre-period expense'
        ), $actor);

        // In-period (2026-10-01 to 2026-10-31): Income Rp 500.000, Expense Rp 150.000
        $recordAction->execute(new RecordFinancialTransactionInput(
            cashAccountId: $this->rwAccountA1->id,
            categoryId: $this->incomeCategoryA->id,
            type: TransactionType::Income,
            amount: 500000,
            transactionDate: '2026-10-05',
            description: 'In-period income'
        ), $actor);

        $recordAction->execute(new RecordFinancialTransactionInput(
            cashAccountId: $this->rwAccountA1->id,
            categoryId: $this->expenseCategoryA->id,
            type: TransactionType::Expense,
            amount: 150000,
            transactionDate: '2026-10-10',
            description: 'In-period expense'
        ), $actor);

        // Post-period: 2026-11-05 Income Rp 999.000 (must be excluded)
        $recordAction->execute(new RecordFinancialTransactionInput(
            cashAccountId: $this->rwAccountA1->id,
            categoryId: $this->incomeCategoryA->id,
            type: TransactionType::Income,
            amount: 999000,
            transactionDate: '2026-11-05',
            description: 'Post-period income'
        ), $actor);

        // Query summary specifically for this RW account
        $res = $this->getJson("/api/v1/finance/reports/summary?cash_account_id={$this->rwAccountA1->id}&date_from=2026-10-01&date_to=2026-10-31");
        $res->assertOk();
        $data = $res->json('data');

        // Opening balance = 1.000.000 baseline + 200.000 - 50.000 = 1.150.000
        $this->assertSame(1150000, $data['opening_balance']);
        $this->assertSame(500000, $data['effective_income']);
        $this->assertSame(150000, $data['effective_expense']);
        $this->assertSame(350000, $data['net_movement']);
        // Closing balance = 1.150.000 + 350.000 = 1.500.000
        $this->assertSame(1500000, $data['closing_balance']);
        $this->assertSame(2, $data['effective_transactions_count']);
        $this->assertSame(0, $data['reversed_transactions_count']);
    }

    public function test_period_summary_reversal_arithmetic(): void
    {
        $actor = $this->actAsScopedUser(RoleKey::BendaharaRw, $this->orgA, $this->rwA1);
        $recordAction = app(RecordFinancialTransactionAction::class);
        $reverseAction = app(ReverseFinancialTransactionAction::class);

        // Post an income of Rp 300.000
        $tx = $recordAction->execute(new RecordFinancialTransactionInput(
            cashAccountId: $this->rwAccountA1->id,
            categoryId: $this->incomeCategoryA->id,
            type: TransactionType::Income,
            amount: 300000,
            transactionDate: '2026-10-05',
            description: 'Income to reverse'
        ), $actor);

        // Reverse the income
        $reverseAction->execute(new ReverseFinancialTransactionInput(
            transactionId: $tx->id,
            reversalReason: 'Salah input nominal',
            transactionDate: '2026-10-06'
        ), $actor);

        $res = $this->getJson("/api/v1/finance/reports/summary?cash_account_id={$this->rwAccountA1->id}&date_from=2026-10-01&date_to=2026-10-31");
        $res->assertOk();
        $data = $res->json('data');

        $this->assertSame(1000000, $data['opening_balance']);
        $this->assertSame(300000, $data['posted_income']);
        $this->assertSame(300000, $data['reversed_income']);
        $this->assertSame(0, $data['effective_income']);
        $this->assertSame(0, $data['net_movement']);
        $this->assertSame(1000000, $data['closing_balance']);
        $this->assertSame(1, $data['reversed_transactions_count']);
        $this->assertSame(1, $data['effective_transactions_count']);
        $this->assertSame(2, $data['total_transactions_count']);
    }

    public function test_reversal_attributed_to_reversal_transaction_date(): void
    {
        $actor = $this->actAsScopedUser(RoleKey::BendaharaRw, $this->orgA, $this->rwA1);
        $recordAction = app(RecordFinancialTransactionAction::class);
        $reverseAction = app(ReverseFinancialTransactionAction::class);

        // Original transaction on 2026-09-20 (pre-period)
        $tx = $recordAction->execute(new RecordFinancialTransactionInput(
            cashAccountId: $this->rwAccountA1->id,
            categoryId: $this->incomeCategoryA->id,
            type: TransactionType::Income,
            amount: 400000,
            transactionDate: '2026-09-20',
            description: 'September income'
        ), $actor);

        // Reversal occurs on 2026-10-02 (in-period)
        $reverseAction->execute(new ReverseFinancialTransactionInput(
            transactionId: $tx->id,
            reversalReason: 'Koreksi audit Oktober',
            transactionDate: '2026-10-02'
        ), $actor);

        // Summary for October
        $res = $this->getJson("/api/v1/finance/reports/summary?cash_account_id={$this->rwAccountA1->id}&date_from=2026-10-01&date_to=2026-10-31");
        $res->assertOk();
        $data = $res->json('data');

        // Opening balance for October includes the September Rp 400.000: 1.000.000 + 400.000 = 1.400.000
        $this->assertSame(1400000, $data['opening_balance']);
        // During October, the reversal negates it: reversed_income = 400.000, effective_income = -400.000
        $this->assertSame(0, $data['posted_income']);
        $this->assertSame(400000, $data['reversed_income']);
        $this->assertSame(-400000, $data['effective_income']);
        $this->assertSame(-400000, $data['net_movement']);
        // Closing balance = 1.400.000 - 400.000 = 1.000.000
        $this->assertSame(1000000, $data['closing_balance']);
    }

    public function test_cash_flow_trend_daily_and_monthly(): void
    {
        $actor = $this->actAsScopedUser(RoleKey::BendaharaRw, $this->orgA, $this->rwA1);
        $recordAction = app(RecordFinancialTransactionAction::class);

        $recordAction->execute(new RecordFinancialTransactionInput(
            cashAccountId: $this->rwAccountA1->id,
            categoryId: $this->incomeCategoryA->id,
            type: TransactionType::Income,
            amount: 100000,
            transactionDate: '2026-10-02',
            description: 'Hari 2'
        ), $actor);

        $recordAction->execute(new RecordFinancialTransactionInput(
            cashAccountId: $this->rwAccountA1->id,
            categoryId: $this->expenseCategoryA->id,
            type: TransactionType::Expense,
            amount: 40000,
            transactionDate: '2026-10-05',
            description: 'Hari 5'
        ), $actor);

        // Daily trend
        $resDaily = $this->getJson("/api/v1/finance/reports/cash-flow?cash_account_id={$this->rwAccountA1->id}&date_from=2026-10-01&date_to=2026-10-05&interval=daily");
        $resDaily->assertOk();
        $dailyBuckets = $resDaily->json('data.buckets');
        $this->assertCount(5, $dailyBuckets); // 5 days: Oct 1, 2, 3, 4, 5
        $this->assertSame('2026-10-02', $dailyBuckets[1]['period']);
        $this->assertSame(100000, $dailyBuckets[1]['effective_income']);
        $this->assertSame(0, $dailyBuckets[2]['effective_income']); // zero filled Oct 3
        $this->assertSame(40000, $dailyBuckets[4]['effective_expense']);

        // Monthly trend
        $resMonthly = $this->getJson("/api/v1/finance/reports/cash-flow?cash_account_id={$this->rwAccountA1->id}&date_from=2026-09-01&date_to=2026-10-31&interval=monthly");
        $resMonthly->assertOk();
        $monthlyBuckets = $resMonthly->json('data.buckets');
        $this->assertCount(2, $monthlyBuckets);
        $this->assertSame('2026-09', $monthlyBuckets[0]['period']);
        $this->assertSame('2026-10', $monthlyBuckets[1]['period']);
        $this->assertSame(100000, $monthlyBuckets[1]['effective_income']);
        $this->assertSame(40000, $monthlyBuckets[1]['effective_expense']);
    }

    public function test_category_breakdown_enforces_direction_and_calculates_percentages(): void
    {
        $actor = $this->actAsScopedUser(RoleKey::BendaharaRw, $this->orgA, $this->rwA1);
        $recordAction = app(RecordFinancialTransactionAction::class);

        $recordAction->execute(new RecordFinancialTransactionInput(
            cashAccountId: $this->rwAccountA1->id,
            categoryId: $this->incomeCategoryA->id,
            type: TransactionType::Income,
            amount: 750000,
            transactionDate: '2026-10-05',
            description: 'Iuran Warga'
        ), $actor);

        // Record while category is active, then mark it inactive to test historical inactive pos category
        $this->inactiveCategoryA->update(['is_active' => true]);
        $recordAction->execute(new RecordFinancialTransactionInput(
            cashAccountId: $this->rwAccountA1->id,
            categoryId: $this->inactiveCategoryA->id,
            type: TransactionType::Income,
            amount: 250000,
            transactionDate: '2026-10-06',
            description: 'Sumbangan'
        ), $actor);
        $this->inactiveCategoryA->update(['is_active' => false]);

        $recordAction->execute(new RecordFinancialTransactionInput(
            cashAccountId: $this->rwAccountA1->id,
            categoryId: $this->expenseCategoryA->id,
            type: TransactionType::Expense,
            amount: 200000,
            transactionDate: '2026-10-07',
            description: 'Perbaikan Lampu'
        ), $actor);

        $res = $this->getJson("/api/v1/finance/reports/categories?cash_account_id={$this->rwAccountA1->id}&date_from=2026-10-01&date_to=2026-10-31");
        $res->assertOk();
        $data = $res->json('data');

        $this->assertSame(1000000, $data['summary']['total_effective_income']);
        $this->assertSame(200000, $data['summary']['total_effective_expense']);

        $categories = $data['categories'];
        $this->assertCount(3, $categories);

        // Find inactive category item
        $inactiveItem = collect($categories)->firstWhere('category_id', $this->inactiveCategoryA->id);
        $this->assertNotNull($inactiveItem);
        $this->assertFalse($inactiveItem['is_active']);
        $this->assertSame(250000, $inactiveItem['effective_amount']);
        $this->assertEquals(25.0, $inactiveItem['percentage']);

        // Find active income category item
        $activeIncomeItem = collect($categories)->firstWhere('category_id', $this->incomeCategoryA->id);
        $this->assertNotNull($activeIncomeItem);
        $this->assertSame(750000, $activeIncomeItem['effective_amount']);
        $this->assertEquals(75.0, $activeIncomeItem['percentage']);
    }

    public function test_rt_breakdown_and_scope_isolation(): void
    {
        // 1. RW Actor sees both RW-level accounts and child RT accounts
        $this->actAsScopedUser(RoleKey::BendaharaRw, $this->orgA, $this->rwA1);
        $resRw = $this->getJson('/api/v1/finance/reports/rt-breakdown?date_from=2026-10-01&date_to=2026-10-31');
        $resRw->assertOk();
        $rwData = $resRw->json('data');

        $items = $rwData['items'];
        $this->assertCount(3, $items); // RW Induk, RT 01, RT 02

        $rwGroup = collect($items)->firstWhere('scope_type', 'RW');
        $this->assertNotNull($rwGroup);
        $this->assertSame('Kas Tingkat RW', $rwGroup['label']);
        $this->assertSame(1000000, $rwGroup['opening_balance']);

        $rt1Group = collect($items)->firstWhere('rt_number', '001');
        $this->assertNotNull($rt1Group);
        $this->assertSame('RT 001', $rt1Group['label']);
        $this->assertSame(500000, $rt1Group['opening_balance']);

        // 2. RT Actor is strictly restricted to their own RT
        $this->actAsScopedUser(RoleKey::BendaharaRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        $resRt = $this->getJson('/api/v1/finance/reports/rt-breakdown?date_from=2026-10-01&date_to=2026-10-31');
        $resRt->assertOk();
        $rtData = $resRt->json('data');

        $this->assertCount(1, $rtData['items']);
        $this->assertSame('RT 001', $rtData['items'][0]['label']);
        $this->assertSame($this->rtA1_1->id, $rtData['items'][0]['rt_id']);
    }

    public function test_account_comparison_table(): void
    {
        $this->actAsScopedUser(RoleKey::BendaharaRw, $this->orgA, $this->rwA1);
        $res = $this->getJson('/api/v1/finance/reports/accounts?date_from=2026-10-01&date_to=2026-10-31');
        $res->assertOk();
        $data = $res->json('data');

        $this->assertSame(3, $data['summary']['total_accounts']);
        $this->assertSame(1750000, $data['summary']['total_opening_balance']); // 1M + 500k + 250k
        $this->assertCount(3, $data['accounts']);
    }

    public function test_csv_export_streaming_and_formula_injection_defense(): void
    {
        $actor = $this->actAsScopedUser(RoleKey::BendaharaRw, $this->orgA, $this->rwA1);
        $recordAction = app(RecordFinancialTransactionAction::class);

        // Record a transaction with potentially dangerous formula injection text
        $recordAction->execute(new RecordFinancialTransactionInput(
            cashAccountId: $this->rwAccountA1->id,
            categoryId: $this->incomeCategoryA->id,
            type: TransactionType::Income,
            amount: 150000,
            transactionDate: '2026-10-05',
            description: '=SUM(A1:B1)',
            referenceNumber: '+62812345678'
        ), $actor);

        $res = $this->get('/api/v1/finance/reports/export?date_from=2026-10-01&date_to=2026-10-31');
        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="laporan-keuangan-2026-10-01-sd-2026-10-31.csv"', $res->headers->get('Content-Disposition'));

        $content = $res->streamedContent();

        // Check UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // Check header columns
        $this->assertStringContainsString('Tanggal,"Nomor Referensi","Akun Kas"', $content);

        // Check formula injection escaping: '=SUM(A1:B1)' must be escaped as "'=SUM(A1:B1)"
        $this->assertStringContainsString("'=SUM(A1:B1)", $content);
        $this->assertStringContainsString("'+62812345678", $content);
    }

    public function test_cross_scope_idor_protection(): void
    {
        // User in Org A cannot see Org B's transactions or accounts
        $this->actAsScopedUser(RoleKey::BendaharaRw, $this->orgA, $this->rwA1);

        $res = $this->getJson("/api/v1/finance/reports/summary?cash_account_id={$this->rtAccountB1_1->id}&date_from=2026-10-01&date_to=2026-10-31");
        $res->assertOk();
        $data = $res->json('data');

        // Since account B belongs to Org B, it is not permitted for Org A actor -> returns empty summary (0 accounts)
        $this->assertSame(0, $data['cash_account_count']);
        $this->assertSame(0, $data['opening_balance']);
        $this->assertSame(0, $data['closing_balance']);
    }

    public function test_invalid_date_ranges_rejected(): void
    {
        $this->actAsScopedUser(RoleKey::BendaharaRw, $this->orgA, $this->rwA1);

        // date_to before date_from
        $res = $this->getJson('/api/v1/finance/reports/summary?date_from=2026-10-31&date_to=2026-10-01');
        $res->assertStatus(422);

        // export > 366 days
        $resExport = $this->get('/api/v1/finance/reports/export?date_from=2024-01-01&date_to=2026-10-01');
        $resExport->assertStatus(422);
    }
}
