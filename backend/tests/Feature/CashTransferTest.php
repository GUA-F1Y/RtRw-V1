<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\Finance\Actions\RecordCashTransferAction;
use App\Domain\Finance\Actions\ReverseCashTransferAction;
use App\Domain\Finance\DTOs\RecordCashTransferInput;
use App\Domain\Finance\DTOs\ReverseCashTransferInput;
use App\Domain\Finance\Enums\CashAccountStatus;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\CashTransfer;
use App\Domain\Finance\Models\FinancialPeriodClosure;
use App\Domain\Finance\Queries\FinanceReportSummaryQuery;
use App\Domain\Finance\Services\AccountBalanceService;
use App\Domain\Finance\Services\FinanceCsvExportService;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashTransferTest extends TestCase
{
    private Organization $orgA;
    private Organization $orgB;
    private Rw $rwA1;
    private Rw $rwB1;
    private Rt $rtA1_1;
    private Rt $rtA1_2;
    private Rt $rtB1_1;

    private CashAccount $rwAccountA1;
    private CashAccount $rwAccountA2;
    private CashAccount $rtAccountA1_1;
    private CashAccount $rtAccountA1_2;
    private CashAccount $rtAccountB1_1;

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

        // Cash Accounts
        $this->rwAccountA1 = CashAccount::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => null,
            'name' => 'Kas Operasional RW 01',
            'type' => 'CASH',
            'opening_balance' => 2000000,
            'status' => CashAccountStatus::Active->value,
        ]);

        $this->rwAccountA2 = CashAccount::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => null,
            'name' => 'Kas Pembangunan RW 01',
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

        $this->rtAccountA1_2 = CashAccount::query()->create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_2->id,
            'name' => 'Kas Operasional RT 02',
            'type' => 'CASH',
            'opening_balance' => 300000,
            'status' => CashAccountStatus::Active->value,
        ]);

        $this->rtAccountB1_1 = CashAccount::query()->create([
            'organization_id' => $this->orgB->id,
            'rw_id' => $this->rwB1->id,
            'rt_id' => $this->rtB1_1->id,
            'name' => 'Kas RT Organisasi B',
            'type' => 'CASH',
            'opening_balance' => 500000,
            'status' => CashAccountStatus::Active->value,
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

    public function test_valid_same_scope_transfer_updates_balances(): void
    {
        $balanceService = app(AccountBalanceService::class);

        $response = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/finance/transfers', [
                'source_cash_account_id' => $this->rwAccountA1->id,
                'destination_cash_account_id' => $this->rwAccountA2->id,
                'amount' => 500000,
                'transfer_date' => '2026-03-10',
                'description' => 'Alokasi dana kas operasional ke pembangunan',
                'reference_number' => 'TRF-001',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.amount', 500000)
            ->assertJsonPath('data.source_cash_account_id', $this->rwAccountA1->id)
            ->assertJsonPath('data.destination_cash_account_id', $this->rwAccountA2->id)
            ->assertJsonPath('data.is_reversal', false);

        // Verify balances via authoritative service
        $sourceBalance = $balanceService->calculate($this->rwAccountA1);
        $destBalance = $balanceService->calculate($this->rwAccountA2);

        $this->assertSame(1500000, $sourceBalance['current_balance']);
        $this->assertSame(500000, $sourceBalance['transfers_out']);
        $this->assertSame(0, $sourceBalance['transfers_in']);

        $this->assertSame(1500000, $destBalance['current_balance']);
        $this->assertSame(0, $destBalance['transfers_out']);
        $this->assertSame(500000, $destBalance['transfers_in']);
    }

    public function test_valid_rw_to_rt_transfer_when_authorized(): void
    {
        $balanceService = app(AccountBalanceService::class);

        $response = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/finance/transfers', [
                'source_cash_account_id' => $this->rwAccountA1->id,
                'destination_cash_account_id' => $this->rtAccountA1_1->id,
                'amount' => 300000,
                'transfer_date' => '2026-03-12',
                'description' => 'Subsidi operasional RT 01',
            ]);

        $response->assertStatus(201);

        $sourceBalance = $balanceService->calculate($this->rwAccountA1);
        $rtBalance = $balanceService->calculate($this->rtAccountA1_1);

        $this->assertSame(1700000, $sourceBalance['current_balance']);
        $this->assertSame(800000, $rtBalance['current_balance']);
    }

    public function test_source_and_destination_must_differ(): void
    {
        $response = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/finance/transfers', [
                'source_cash_account_id' => $this->rwAccountA1->id,
                'destination_cash_account_id' => $this->rwAccountA1->id,
                'amount' => 100000,
                'transfer_date' => '2026-03-10',
                'description' => 'Transfer ke akun yang sama',
            ]);

        $response->assertStatus(422);
    }

    public function test_cross_organization_transfer_rejected(): void
    {
        $response = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/finance/transfers', [
                'source_cash_account_id' => $this->rwAccountA1->id,
                'destination_cash_account_id' => $this->rtAccountB1_1->id,
                'amount' => 100000,
                'transfer_date' => '2026-03-10',
                'description' => 'Transfer lintas organisasi',
            ]);

        // Destination is in Org B, user is in Org A -> forbidden or 422
        $this->assertTrue(in_array($response->status(), [403, 422], true));
    }

    public function test_rt_actor_cannot_transfer_to_rw_or_other_rt(): void
    {
        // RT 1 leader trying to transfer from RT 1 account to RW account
        $resRw = $this->actingAs($this->bendaharaRtA1_1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/finance/transfers', [
                'source_cash_account_id' => $this->rtAccountA1_1->id,
                'destination_cash_account_id' => $this->rwAccountA1->id,
                'amount' => 50000,
                'transfer_date' => '2026-03-10',
                'description' => 'RT mencoba transfer ke RW',
            ]);
        $resRw->assertStatus(403);

        // RT 1 leader trying to transfer from RT 1 account to RT 2 account
        $resOtherRt = $this->actingAs($this->bendaharaRtA1_1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/finance/transfers', [
                'source_cash_account_id' => $this->rtAccountA1_1->id,
                'destination_cash_account_id' => $this->rtAccountA1_2->id,
                'amount' => 50000,
                'transfer_date' => '2026-03-10',
                'description' => 'RT mencoba transfer ke RT lain',
            ]);
        $resOtherRt->assertStatus(403);
    }

    public function test_insufficient_available_balance_rejected(): void
    {
        $response = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/finance/transfers', [
                'source_cash_account_id' => $this->rwAccountA1->id,
                'destination_cash_account_id' => $this->rwAccountA2->id,
                'amount' => 5000000, // Balance is only 2,000,000
                'transfer_date' => '2026-03-10',
                'description' => 'Transfer melebihi saldo',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'insufficient_funds');
    }

    public function test_invalid_amount_rejected(): void
    {
        $response = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/finance/transfers', [
                'source_cash_account_id' => $this->rwAccountA1->id,
                'destination_cash_account_id' => $this->rwAccountA2->id,
                'amount' => 0,
                'transfer_date' => '2026-03-10',
                'description' => 'Transfer nol rupiah',
            ]);

        $response->assertStatus(422);
    }

    public function test_reversal_of_transfer_restores_balances(): void
    {
        $balanceService = app(AccountBalanceService::class);

        // 1. Post original transfer
        $createRes = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/finance/transfers', [
                'source_cash_account_id' => $this->rwAccountA1->id,
                'destination_cash_account_id' => $this->rwAccountA2->id,
                'amount' => 400000,
                'transfer_date' => '2026-03-10',
                'description' => 'Transfer awal untuk dibalikkan',
            ]);
        $createRes->assertStatus(201);
        $transferId = $createRes->json('data.id');

        // Check balances after transfer
        $this->assertSame(1600000, $balanceService->calculate($this->rwAccountA1)['current_balance']);
        $this->assertSame(1400000, $balanceService->calculate($this->rwAccountA2)['current_balance']);

        // 2. Reverse transfer
        $reverseRes = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/transfers/{$transferId}/reverse", [
                'reversal_reason' => 'Salah tujuan transfer',
                'transfer_date' => '2026-03-11',
            ]);

        $reverseRes->assertStatus(201)
            ->assertJsonPath('data.is_reversal', true)
            ->assertJsonPath('data.original_transfer_id', $transferId)
            ->assertJsonPath('data.amount', 400000)
            ->assertJsonPath('data.source_cash_account_id', $this->rwAccountA2->id) // Inverted
            ->assertJsonPath('data.destination_cash_account_id', $this->rwAccountA1->id); // Inverted

        // Check balances restored
        $this->assertSame(2000000, $balanceService->calculate($this->rwAccountA1)['current_balance']);
        $this->assertSame(1000000, $balanceService->calculate($this->rwAccountA2)['current_balance']);
    }

    public function test_duplicate_reversal_prevented(): void
    {
        // 1. Post original transfer
        $createRes = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/finance/transfers', [
                'source_cash_account_id' => $this->rwAccountA1->id,
                'destination_cash_account_id' => $this->rwAccountA2->id,
                'amount' => 200000,
                'transfer_date' => '2026-03-10',
                'description' => 'Transfer pengujian duplikasi reversal',
            ]);
        $createRes->assertStatus(201);
        $transferId = $createRes->json('data.id');

        // 2. First reversal
        $rev1 = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/transfers/{$transferId}/reverse", [
                'reversal_reason' => 'Koreksi pertama',
            ]);
        $rev1->assertStatus(201);

        // 3. Second reversal attempt
        $rev2 = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/transfers/{$transferId}/reverse", [
                'reversal_reason' => 'Koreksi kedua tidak boleh',
            ]);
        $rev2->assertStatus(409)
            ->assertJsonPath('error.code', 'transfer_already_reversed');
    }

    public function test_cannot_reverse_a_reversal(): void
    {
        // Post transfer
        $createRes = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/finance/transfers', [
                'source_cash_account_id' => $this->rwAccountA1->id,
                'destination_cash_account_id' => $this->rwAccountA2->id,
                'amount' => 150000,
                'transfer_date' => '2026-03-10',
                'description' => 'Transfer asli',
            ]);
        $transferId = $createRes->json('data.id');

        // Reverse transfer
        $revRes = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/transfers/{$transferId}/reverse", [
                'reversal_reason' => 'Reversal asli',
            ]);
        $reversalId = $revRes->json('data.id');

        // Try to reverse the reversal itself
        $revRevRes = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/transfers/{$reversalId}/reverse", [
                'reversal_reason' => 'Mencoba membalikkan pembalikan',
            ]);
        $revRevRes->assertStatus(422);
    }

    public function test_transfers_immutable_trigger(): void
    {
        $transfer = CashTransfer::create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => null,
            'source_cash_account_id' => $this->rwAccountA1->id,
            'destination_cash_account_id' => $this->rwAccountA2->id,
            'amount' => 100000,
            'transfer_date' => '2026-03-10',
            'description' => 'Uji trigger immutabilitas',
            'recorded_by' => $this->bendaharaRwA1->id,
            'is_reversal' => false,
        ]);

        $this->expectException(\PDOException::class);
        DB::table('cash_transfers')->where('id', $transfer->id)->update(['amount' => 999999]);
    }

    public function test_transfers_delete_trigger(): void
    {
        $transfer = CashTransfer::create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => null,
            'source_cash_account_id' => $this->rwAccountA1->id,
            'destination_cash_account_id' => $this->rwAccountA2->id,
            'amount' => 100000,
            'transfer_date' => '2026-03-10',
            'description' => 'Uji trigger immutabilitas delete',
            'recorded_by' => $this->bendaharaRwA1->id,
            'is_reversal' => false,
        ]);

        $this->expectException(\PDOException::class);
        DB::table('cash_transfers')->where('id', $transfer->id)->delete();
    }

    public function test_closed_period_transfer_is_rejected(): void
    {
        // Close Jan 2026 for rwAccountA1
        FinancialPeriodClosure::create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => null,
            'cash_account_id' => $this->rwAccountA1->id,
            'period_year' => 2026,
            'period_month' => 1,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'opening_balance' => 2000000,
            'closing_balance' => 2000000,
            'total_income' => 0,
            'total_expense' => 0,
            'posted_income' => 0,
            'reversed_income' => 0,
            'posted_expense' => 0,
            'reversed_expense' => 0,
            'net_movement' => 0,
            'transaction_count' => 0,
            'reversal_count' => 0,
            'closed_by' => $this->bendaharaRwA1->id,
            'closed_at' => Carbon::now(),
        ]);

        // Attempt transfer backdated to closed Jan 2026
        $response = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/finance/transfers', [
                'source_cash_account_id' => $this->rwAccountA1->id,
                'destination_cash_account_id' => $this->rwAccountA2->id,
                'amount' => 100000,
                'transfer_date' => '2026-01-15',
                'description' => 'Transfer pada periode tertutup',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'period_closed');
    }

    public function test_transfers_excluded_from_operating_income_and_expense_in_reports(): void
    {
        // Perform transfer of 500,000 in March 2026
        $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/finance/transfers', [
                'source_cash_account_id' => $this->rwAccountA1->id,
                'destination_cash_account_id' => $this->rwAccountA2->id,
                'amount' => 500000,
                'transfer_date' => '2026-03-15',
                'description' => 'Pindahan kas RW',
            ])->assertStatus(201);

        $summaryQuery = app(FinanceReportSummaryQuery::class);
        $summary = $summaryQuery->execute($this->bendaharaRwA1, [
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]);

        // Operating income and expense must NOT include transfers!
        $this->assertSame(0, $summary['effective_income']);
        $this->assertSame(0, $summary['effective_expense']);

        // Internal transfers cancel out across both accounts in organizational aggregate
        $this->assertSame(500000, $summary['transfers_in']);
        $this->assertSame(500000, $summary['transfers_out']);
        $this->assertSame(0, $summary['net_transfer_movement']);
        $this->assertSame(0, $summary['net_movement']);

        // Summary for rwAccountA1 only:
        $summarySource = $summaryQuery->execute($this->bendaharaRwA1, [
            'cash_account_id' => $this->rwAccountA1->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]);
        $this->assertSame(0, $summarySource['effective_income']);
        $this->assertSame(0, $summarySource['effective_expense']);
        $this->assertSame(0, $summarySource['transfers_in']);
        $this->assertSame(500000, $summarySource['transfers_out']);
        $this->assertSame(-500000, $summarySource['net_transfer_movement']);
        $this->assertSame(-500000, $summarySource['net_movement']);
        $this->assertSame(1500000, $summarySource['closing_balance']);
    }

    public function test_csv_export_distinguishes_transfers(): void
    {
        $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/finance/transfers', [
                'source_cash_account_id' => $this->rwAccountA1->id,
                'destination_cash_account_id' => $this->rwAccountA2->id,
                'amount' => 250000,
                'transfer_date' => '2026-03-20',
                'description' => 'Transfer pengujian CSV',
                'reference_number' => 'CSV-TRF-01',
            ])->assertStatus(201);

        $response = $this->actingAs($this->bendaharaRwA1)
            ->get('/api/v1/finance/reports/export?date_from=2026-03-01&date_to=2026-03-31&type=TRANSFER');

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Transfer Internal', $content);
        $this->assertStringContainsString('Transfer Keluar', $content);
        $this->assertStringContainsString('Transfer Masuk', $content);
        $this->assertStringContainsString('250000', $content);
    }

    public function test_idempotent_transfer_creation(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'source_cash_account_id' => $this->rwAccountA1->id,
            'destination_cash_account_id' => $this->rwAccountA2->id,
            'amount' => 100000,
            'transfer_date' => '2026-03-10',
            'description' => 'Uji idempotensi transfer',
        ];

        // First call
        $res1 = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson('/api/v1/finance/transfers', $payload);
        $res1->assertStatus(201);
        $transferId = $res1->json('data.id');

        // Replay with exact same payload
        $res2 = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson('/api/v1/finance/transfers', $payload);
        $res2->assertStatus(201)
            ->assertJsonPath('data.id', $transferId);

        // Conflict attempt: same key, different payload
        $res3 = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson('/api/v1/finance/transfers', array_merge($payload, ['amount' => 200000]));
        $res3->assertStatus(422)
            ->assertJsonPath('error.code', 'idempotency_key_reused');
    }

    public function test_read_only_secretary_permissions(): void
    {
        // 1. Post a transfer using bendahara
        $createRes = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/finance/transfers', [
                'source_cash_account_id' => $this->rwAccountA1->id,
                'destination_cash_account_id' => $this->rwAccountA2->id,
                'amount' => 100000,
                'transfer_date' => '2026-03-10',
                'description' => 'Transfer sekretaris check',
            ]);
        $createRes->assertStatus(201);
        $transferId = $createRes->json('data.id');

        // 2. Sekretaris can list transfers
        $listRes = $this->actingAs($this->sekretarisRwA1)
            ->getJson('/api/v1/finance/transfers');
        $listRes->assertOk();

        // 3. Sekretaris can show transfer detail
        $showRes = $this->actingAs($this->sekretarisRwA1)
            ->getJson("/api/v1/finance/transfers/{$transferId}");
        $showRes->assertOk();

        // 4. Sekretaris CANNOT create transfers
        $createSecRes = $this->actingAs($this->sekretarisRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/finance/transfers', [
                'source_cash_account_id' => $this->rwAccountA1->id,
                'destination_cash_account_id' => $this->rwAccountA2->id,
                'amount' => 50000,
                'transfer_date' => '2026-03-10',
                'description' => 'Sekretaris mencoba transfer',
            ]);
        $createSecRes->assertStatus(403);

        // 5. Sekretaris CANNOT reverse transfers
        $revSecRes = $this->actingAs($this->sekretarisRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/transfers/{$transferId}/reverse", [
                'reversal_reason' => 'Sekretaris mencoba reversal',
            ]);
        $revSecRes->assertStatus(403);
    }

    public function test_audit_logging_on_transfer_and_reversal(): void
    {
        $res = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/finance/transfers', [
                'source_cash_account_id' => $this->rwAccountA1->id,
                'destination_cash_account_id' => $this->rwAccountA2->id,
                'amount' => 120000,
                'transfer_date' => '2026-03-10',
                'description' => 'Transfer dengan audit',
            ]);
        $res->assertStatus(201);
        $transferId = $res->json('data.id');

        $this->assertDatabaseHas('activity_log', [
            'event' => 'finance.transfer.recorded',
            'subject_id' => $transferId,
            'causer_id' => $this->bendaharaRwA1->id,
        ]);

        $revRes = $this->actingAs($this->bendaharaRwA1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/finance/transfers/{$transferId}/reverse", [
                'reversal_reason' => 'Alasan audit reversal',
            ]);
        $revRes->assertStatus(201);
        $reversalId = $revRes->json('data.id');

        $this->assertDatabaseHas('activity_log', [
            'event' => 'finance.transfer.reversed',
            'subject_id' => $reversalId,
            'causer_id' => $this->bendaharaRwA1->id,
        ]);
    }
}
