<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\Finance\Actions\RecordFinancialTransactionAction;
use App\Domain\Finance\DTOs\RecordFinancialTransactionInput;
use App\Domain\Finance\Enums\CashAccountStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\FinanceCategory;
use App\Domain\Finance\Models\FinancialPeriodClosure;
use App\Domain\Finance\Models\FinancialTransaction;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialPeriodClosureTest extends TestCase
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
    private CashAccount $rtAccountB1_1;

    private FinanceCategory $incomeCategoryA;
    private FinanceCategory $expenseCategoryA;

    private User $ketuaRwA1;
    private User $bendaharaRwA1;
    private User $sekretarisRwA1;
    private User $ketuaRtA1_1;
    private User $bendaharaRtA1_1;
    private User $sekretarisRtA1_1;
    private User $ketuaRtB1_1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::factory()->create(['name' => 'Organisasi Keuangan A']);
        $this->orgB = Organization::factory()->create(['name' => 'Organisasi Keuangan B']);

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
            'name' => 'Kas Utama RW 01',
            'type' => 'CASH',
            'opening_balance' => 1000000,
            'status' => CashAccountStatus::Active->value,
        ]);

        $this->rtAccountA1_1 = CashAccount::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'name' => 'Kas Operasional RT 01',
            'type' => 'CASH',
            'opening_balance' => 500000,
            'status' => CashAccountStatus::Active->value,
        ]);

        $this->rtAccountB1_1 = CashAccount::query()->create([
            'organization_id' => $this->orgB->id,
            'rw_id' => $this->rwB1->id,
            'rt_id' => $this->rtB1_1->id,
            'name' => 'Kas RT Organisasi B',
            'type' => 'CASH',
            'opening_balance' => 250000,
            'status' => CashAccountStatus::Active->value,
        ]);

        // Categories
        $this->incomeCategoryA = FinanceCategory::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => null,
            'name' => 'Iuran Warga',
            'type' => 'INCOME',
            'code' => 'IURAN',
            'is_active' => true,
        ]);

        $this->expenseCategoryA = FinanceCategory::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => null,
            'name' => 'Operasional Lingkungan',
            'type' => 'EXPENSE',
            'code' => 'OPS',
            'is_active' => true,
        ]);

        // Users
        $this->ketuaRwA1 = $this->createScopedUser(RoleKey::KetuaRw, $this->orgA, $this->rwA1);
        $this->bendaharaRwA1 = $this->createScopedUser(RoleKey::BendaharaRw, $this->orgA, $this->rwA1);
        $this->sekretarisRwA1 = $this->createScopedUser(RoleKey::SekretarisRw, $this->orgA, $this->rwA1);

        $this->ketuaRtA1_1 = $this->createScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        $this->bendaharaRtA1_1 = $this->createScopedUser(RoleKey::BendaharaRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        $this->sekretarisRtA1_1 = $this->createScopedUser(RoleKey::SekretarisRt, $this->orgA, $this->rwA1, $this->rtA1_1);

        $this->ketuaRtB1_1 = $this->createScopedUser(RoleKey::KetuaRt, $this->orgB, $this->rwB1, $this->rtB1_1);
    }

    public function test_preview_balance_for_empty_month(): void
    {
        $response = $this->actingAs($this->bendaharaRwA1)
            ->getJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/periods/2026/1/preview");

        $response->assertOk()
            ->assertJsonPath('data.opening_balance', 1000000)
            ->assertJsonPath('data.closing_balance', 1000000)
            ->assertJsonPath('data.total_income', 0)
            ->assertJsonPath('data.total_expense', 0)
            ->assertJsonPath('data.net_movement', 0)
            ->assertJsonPath('data.total_transactions_count', 0)
            ->assertJsonPath('data.is_already_closed', false)
            ->assertJsonPath('data.is_eligible_for_closure', true);
    }

    public function test_preview_balance_with_prior_and_in_period_transactions(): void
    {
        $action = app(RecordFinancialTransactionAction::class);

        // Pre-period movement (Jan 2026)
        $action->execute(new RecordFinancialTransactionInput(
            cashAccountId: $this->rwAccountA1->id,
            categoryId: $this->incomeCategoryA->id,
            type: TransactionType::Income,
            amount: 500000,
            transactionDate: '2026-01-15',
            description: 'Setoran Awal Januari',
        ), $this->bendaharaRwA1);

        // In-period movements (Feb 2026)
        $action->execute(new RecordFinancialTransactionInput(
            cashAccountId: $this->rwAccountA1->id,
            categoryId: $this->incomeCategoryA->id,
            type: TransactionType::Income,
            amount: 300000,
            transactionDate: '2026-02-10',
            description: 'Iuran Warga Februari',
        ), $this->bendaharaRwA1);

        $action->execute(new RecordFinancialTransactionInput(
            cashAccountId: $this->rwAccountA1->id,
            categoryId: $this->expenseCategoryA->id,
            type: TransactionType::Expense,
            amount: 100000,
            transactionDate: '2026-02-20',
            description: 'Beli Sapu Lingkungan',
        ), $this->bendaharaRwA1);

        // Preview Feb 2026:
        // opening_balance = 1,000,000 + 500,000 = 1,500,000
        // in-period net = 300,000 - 100,000 = 200,000
        // closing_balance = 1,700,000
        $response = $this->actingAs($this->bendaharaRwA1)
            ->getJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/periods/2026/2/preview");

        $response->assertOk()
            ->assertJsonPath('data.opening_balance', 1500000)
            ->assertJsonPath('data.effective_income', 300000)
            ->assertJsonPath('data.effective_expense', 100000)
            ->assertJsonPath('data.net_movement', 200000)
            ->assertJsonPath('data.closing_balance', 1700000)
            ->assertJsonPath('data.total_transactions_count', 2);
    }

    public function test_current_and_future_months_cannot_be_closed(): void
    {
        $now = Carbon::now();
        $currentYear = $now->year;
        $currentMonth = $now->month;

        $response = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/periods/{$currentYear}/{$currentMonth}/close", []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'period_not_eligible');

        // Future month
        $future = $now->copy()->addMonths(2);
        $responseFuture = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/periods/{$future->year}/{$future->month}/close", []);

        $responseFuture->assertStatus(422)
            ->assertJsonPath('error.code', 'period_not_eligible');
    }

    public function test_successful_closure_with_no_physical_cash_count(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $response = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/periods/2026/1/close", []);

        $response->assertStatus(201)
            ->assertJsonPath('data.period_year', 2026)
            ->assertJsonPath('data.period_month', 1)
            ->assertJsonPath('data.period_start', '2026-01-01')
            ->assertJsonPath('data.period_end', '2026-01-31')
            ->assertJsonPath('data.opening_balance', 1000000)
            ->assertJsonPath('data.closing_balance', 1000000)
            ->assertJsonPath('data.is_reconciled', false)
            ->assertJsonPath('data.has_discrepancy', false)
            ->assertJsonPath('data.counted_cash_balance', null)
            ->assertJsonPath('data.reconciliation_difference', null);

        $this->assertDatabaseHas('financial_period_closures', [
            'cash_account_id' => $this->rwAccountA1->id,
            'period_year' => 2026,
            'period_month' => 1,
            'closing_balance' => 1000000,
        ]);
    }

    public function test_successful_closure_with_exact_physical_count(): void
    {
        $response = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/periods/2026/1/close", [
                'counted_cash_balance' => 1000000,
                'reconciliation_notes' => 'Fisik kas dihitung pas cocok.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_reconciled', true)
            ->assertJsonPath('data.has_discrepancy', false)
            ->assertJsonPath('data.counted_cash_balance', 1000000)
            ->assertJsonPath('data.reconciliation_difference', 0);
    }

    public function test_successful_closure_with_excess_cash_and_explanation(): void
    {
        // counted = 1,050,000, ledger = 1,000,000 -> difference = +50,000
        $response = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/periods/2026/1/close", [
                'counted_cash_balance' => 1050000,
                'reconciliation_notes' => 'Kelebihan Rp 50.000 akibat uang kembalian belanja belum disetor kuitansinya.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_reconciled', true)
            ->assertJsonPath('data.has_discrepancy', true)
            ->assertJsonPath('data.counted_cash_balance', 1050000)
            ->assertJsonPath('data.reconciliation_difference', 50000)
            ->assertJsonPath('data.reconciliation_notes', 'Kelebihan Rp 50.000 akibat uang kembalian belanja belum disetor kuitansinya.');
    }

    public function test_successful_closure_with_shortage_cash_and_explanation(): void
    {
        // counted = 950,000, ledger = 1,000,000 -> difference = -50,000
        $response = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/periods/2026/1/close", [
                'counted_cash_balance' => 950000,
                'reconciliation_notes' => 'Kekurangan Rp 50.000 karena panjar kas kecil belum dikembalikan oleh satpam.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_reconciled', true)
            ->assertJsonPath('data.has_discrepancy', true)
            ->assertJsonPath('data.counted_cash_balance', 950000)
            ->assertJsonPath('data.reconciliation_difference', -50000);
    }

    public function test_discrepancy_rejected_when_explanation_notes_are_missing(): void
    {
        $response = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/periods/2026/1/close", [
                'counted_cash_balance' => 950000,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'reconciliation_discrepancy_explanation_required');
    }

    public function test_duplicate_closure_returns_409_conflict(): void
    {
        // Close once
        $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/periods/2026/1/close", [])
            ->assertStatus(201);

        // Attempt closing again with a new idempotency key
        $response = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/periods/2026/1/close", [])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'period_already_closed');
    }

    public function test_idempotent_closure_replay(): void
    {
        $key = (string) Str::uuid();

        // First attempt
        $res1 = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => $key])
            ->postJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/periods/2026/1/close", [
                'counted_cash_balance' => 1000000,
                'reconciliation_notes' => 'Cocok',
            ]);
        $res1->assertStatus(201);
        $closureId = $res1->json('data.id');

        // Replay same key and payload
        $res2 = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => $key])
            ->postJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/periods/2026/1/close", [
                'counted_cash_balance' => 1000000,
                'reconciliation_notes' => 'Cocok',
            ]);
        $res2->assertStatus(201)
            ->assertJsonPath('data.id', $closureId);

        // Replaying same key with different payload returns 422
        $res3 = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => $key])
            ->postJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/periods/2026/1/close", [
                'counted_cash_balance' => 2000000,
                'reconciliation_notes' => 'Berbeda',
            ]);
        $res3->assertStatus(422)
            ->assertJsonPath('error.code', 'idempotency_key_reused');
    }

    public function test_posting_inside_closed_period_is_rejected(): void
    {
        // 1. Close January 2026
        $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/periods/2026/1/close", [])
            ->assertStatus(201);

        // 2. Attempt to post transaction on 2026-01-15 -> MUST FAIL (409)
        $response = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/finance/transactions', [
                'cash_account_id' => $this->rwAccountA1->id,
                'category_id' => $this->incomeCategoryA->id,
                'type' => 'INCOME',
                'amount' => 100000,
                'transaction_date' => '2026-01-15',
                'description' => 'Iuran susulan di periode tertutup',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'period_closed');
    }

    public function test_posting_on_first_and_last_day_of_closed_period_is_rejected(): void
    {
        // Close January 2026 (2026-01-01 to 2026-01-31)
        $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/periods/2026/1/close", [])
            ->assertStatus(201);

        // First day: 2026-01-01
        $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/finance/transactions', [
                'cash_account_id' => $this->rwAccountA1->id,
                'category_id' => $this->incomeCategoryA->id,
                'type' => 'INCOME',
                'amount' => 50000,
                'transaction_date' => '2026-01-01',
                'description' => 'Posting hari pertama',
            ])->assertStatus(409)->assertJsonPath('error.code', 'period_closed');

        // Last day: 2026-01-31
        $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/finance/transactions', [
                'cash_account_id' => $this->rwAccountA1->id,
                'category_id' => $this->incomeCategoryA->id,
                'type' => 'INCOME',
                'amount' => 50000,
                'transaction_date' => '2026-01-31',
                'description' => 'Posting hari terakhir',
            ])->assertStatus(409)->assertJsonPath('error.code', 'period_closed');

        // Day after (2026-02-01) -> SUCCEEDS (Feb is open)
        $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/finance/transactions', [
                'cash_account_id' => $this->rwAccountA1->id,
                'category_id' => $this->incomeCategoryA->id,
                'type' => 'INCOME',
                'amount' => 50000,
                'transaction_date' => '2026-02-01',
                'description' => 'Posting periode terbuka',
            ])->assertStatus(201);
    }

    public function test_reversal_into_closed_period_is_rejected_while_open_period_succeeds(): void
    {
        $action = app(RecordFinancialTransactionAction::class);

        // Create transaction in January 2026 BEFORE closure
        $transaction = $action->execute(new RecordFinancialTransactionInput(
            cashAccountId: $this->rwAccountA1->id,
            categoryId: $this->incomeCategoryA->id,
            type: TransactionType::Income,
            amount: 250000,
            transactionDate: '2026-01-10',
            description: 'Transaksi salah catat Januari',
        ), $this->bendaharaRwA1);

        // Now close January 2026
        $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/periods/2026/1/close", [])
            ->assertStatus(201);

        // Attempt reversal backdated into closed Jan 2026 -> MUST FAIL (409)
        $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/transactions/{$transaction->id}/reverse", [
                'reversal_reason' => 'Koreksi salah nominal',
                'transaction_date' => '2026-01-20',
            ])->assertStatus(409)->assertJsonPath('error.code', 'period_closed');

        // Reversal with open period date (e.g. Feb 2026) -> SUCCEEDS!
        $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/transactions/{$transaction->id}/reverse", [
                'reversal_reason' => 'Koreksi dicatat di periode berjalan',
                'transaction_date' => '2026-02-05',
            ])->assertStatus(201);

        // Original transaction remains unchanged
        $this->assertDatabaseHas('financial_transactions', [
            'id' => $transaction->id,
            'transaction_date' => '2026-01-10',
            'is_reversal' => false,
        ]);
    }

    public function test_immutability_trigger_aborts_update_and_delete(): void
    {
        $closure = FinancialPeriodClosure::create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => null,
            'cash_account_id' => $this->rwAccountA1->id,
            'period_year' => 2026,
            'period_month' => 1,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'opening_balance' => 1000000,
            'closing_balance' => 1000000,
            'closed_by' => $this->bendaharaRwA1->id,
        ]);

        // Attempt SQL UPDATE -> Trigger exception
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('financial_period_closures')
            ->where('id', $closure->id)
            ->update(['closing_balance' => 9999999]);
    }

    public function test_immutability_trigger_aborts_delete(): void
    {
        $closure = FinancialPeriodClosure::create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => null,
            'cash_account_id' => $this->rwAccountA1->id,
            'period_year' => 2026,
            'period_month' => 1,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'opening_balance' => 1000000,
            'closing_balance' => 1000000,
            'closed_by' => $this->bendaharaRwA1->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('financial_period_closures')
            ->where('id', $closure->id)
            ->delete();
    }

    public function test_scope_isolation_rt_cannot_close_or_view_other_rt_account(): void
    {
        // RT 01 actor tries to close RT B's account
        $response = $this->actingAs($this->ketuaRtA1_1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/accounts/{$this->rtAccountB1_1->id}/periods/2026/1/close", []);

        $response->assertStatus(403);

        // RT 01 actor tries to close RW account (RW level has rt_id null, outside RT scope)
        $responseRw = $this->actingAs($this->ketuaRtA1_1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/periods/2026/1/close", []);

        $responseRw->assertStatus(403);
    }

    public function test_read_only_role_sekretaris_cannot_close_period(): void
    {
        // Sekretaris RW has FinanceAccountView and FinanceTransactionView, but NOT FinancePeriodClose
        $response = $this->actingAs($this->sekretarisRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/periods/2026/1/close", []);

        $response->assertStatus(403);
    }

    public function test_list_and_show_period_closures(): void
    {
        // Close Jan 2026
        $res = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/accounts/{$this->rwAccountA1->id}/periods/2026/1/close", [
                'counted_cash_balance' => 1000000,
                'reconciliation_notes' => 'Tutup buku Januari selesai.',
            ]);
        $res->assertStatus(201);
        $closureId = $res->json('data.id');

        // List closures
        $listRes = $this->actingAs($this->sekretarisRwA1) // Sekretaris can view
            ->getJson("/api/v1/finance/period-closures?cash_account_id={$this->rwAccountA1->id}");

        $listRes->assertOk()
            ->assertJsonPath('data.0.id', $closureId)
            ->assertJsonPath('data.0.period_year', 2026)
            ->assertJsonPath('data.0.period_month', 1);

        // Show closure detail
        $showRes = $this->actingAs($this->sekretarisRwA1)
            ->getJson("/api/v1/finance/period-closures/{$closureId}");

        $showRes->assertOk()
            ->assertJsonPath('data.id', $closureId)
            ->assertJsonPath('data.closing_balance', 1000000)
            ->assertJsonPath('data.counted_cash_balance', 1000000)
            ->assertJsonPath('data.reconciliation_difference', 0);
    }
}
