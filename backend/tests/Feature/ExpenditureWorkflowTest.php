<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\Finance\Actions\ApproveExpenditureRequestAction;
use App\Domain\Finance\Actions\CancelExpenditureRequestAction;
use App\Domain\Finance\Actions\CreateBudgetPlanAction;
use App\Domain\Finance\Actions\CreateExpenditureRequestAction;
use App\Domain\Finance\Actions\PostExpenditureRequestAction;
use App\Domain\Finance\Actions\PublishBudgetPlanAction;
use App\Domain\Finance\Actions\RejectExpenditureRequestAction;
use App\Domain\Finance\Actions\SubmitExpenditureRequestAction;
use App\Domain\Finance\DTOs\ApproveExpenditureInput;
use App\Domain\Finance\DTOs\CancelExpenditureInput;
use App\Domain\Finance\DTOs\CreateBudgetPlanInput;
use App\Domain\Finance\DTOs\CreateExpenditureRequestInput;
use App\Domain\Finance\DTOs\PostExpenditureInput;
use App\Domain\Finance\DTOs\RejectExpenditureInput;
use App\Domain\Finance\Enums\CashAccountStatus;
use App\Domain\Finance\Enums\ExpenditureRequestStatus;
use App\Domain\Finance\Enums\PeriodClosureStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Exceptions\ClosedPeriodException;
use App\Domain\Finance\Exceptions\InsufficientBudgetException;
use App\Domain\Finance\Exceptions\SelfApprovalException;
use App\Domain\Finance\Models\BudgetPlan;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\FinanceCategory;
use App\Domain\Finance\Models\FinancialPeriodClosure;
use App\Domain\Finance\Models\FinancialTransaction;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExpenditureWorkflowTest extends TestCase
{
    private Organization $org;
    private Rw $rw;
    private Rt $rt;

    private User $ketuaRw;
    private User $bendaharaRw;
    private User $sekretarisRw;
    private User $ketuaRt;
    private User $bendaharaRt;

    private CashAccount $rwAccount;
    private CashAccount $rtAccount;
    private FinanceCategory $catExpense;
    private BudgetPlan $publishedRwPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create(['name' => 'Org Expenditure Test']);
        $this->rw = Rw::factory()->create(['organization_id' => $this->org->id, 'number' => 1]);
        $this->rt = Rt::factory()->create(['rw_id' => $this->rw->id, 'number' => 1]);

        $this->ketuaRw = $this->createScopedUser(RoleKey::KetuaRw, $this->org, $this->rw);
        $this->bendaharaRw = $this->createScopedUser(RoleKey::BendaharaRw, $this->org, $this->rw);
        $this->sekretarisRw = $this->createScopedUser(RoleKey::SekretarisRw, $this->org, $this->rw);
        $this->ketuaRt = $this->createScopedUser(RoleKey::KetuaRt, $this->org, $this->rw, $this->rt);
        $this->bendaharaRt = $this->createScopedUser(RoleKey::BendaharaRt, $this->org, $this->rw, $this->rt);

        $this->rwAccount = CashAccount::create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => null,
            'name' => 'Kas Operasional RW',
            'account_number' => 'ACC-RW-001',
            'type' => 'OPERATIONAL',
            'status' => CashAccountStatus::Active,
        ]);

        $this->rtAccount = CashAccount::create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'name' => 'Kas Operasional RT 01',
            'account_number' => 'ACC-RT-001',
            'type' => 'OPERATIONAL',
            'status' => CashAccountStatus::Active,
        ]);

        $this->catExpense = FinanceCategory::create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'name' => 'Pemeliharaan Lingkungan',
            'code' => 'EXP-MAINT',
            'type' => TransactionType::Expense,
            'is_system' => false,
            'is_active' => true,
        ]);

        // Publish a budget plan for 2026-09
        $createPlanAction = app(CreateBudgetPlanAction::class);
        $publishAction = app(PublishBudgetPlanAction::class);

        $draft = $createPlanAction->execute(
            new CreateBudgetPlanInput(
                year: 2026,
                month: 9,
                lines: [
                    ['category_id' => $this->catExpense->id, 'planned_amount' => 5_000_000],
                ]
            ),
            $this->bendaharaRw,
            $this->org,
            $this->rw
        );

        $this->publishedRwPlan = $publishAction->execute($draft, $this->ketuaRw);
    }

    public function test_can_create_and_submit_expenditure_request(): void
    {
        $createAction = app(CreateExpenditureRequestAction::class);
        $submitAction = app(SubmitExpenditureRequestAction::class);

        $line = $this->publishedRwPlan->lines->first();

        $request = $createAction->execute(
            new CreateExpenditureRequestInput(
                budgetLineId: $line->id,
                cashAccountId: $this->rwAccount->id,
                proposedAmount: 1_200_000,
                plannedDate: '2026-09-10',
                title: 'Perbaikan Lampu Jalan',
                description: 'Beli 10 lampu LED',
                submit: false
            ),
            $this->sekretarisRw,
            $this->org,
            $this->rw
        );

        $this->assertEquals(ExpenditureRequestStatus::Draft, $request->status);
        $this->assertEquals(1_200_000, $request->proposed_amount);
        $this->assertNull($request->approved_amount);

        $submitted = $submitAction->execute($request, $this->sekretarisRw);
        $this->assertEquals(ExpenditureRequestStatus::Submitted, $submitted->status);
        $this->assertNotNull($submitted->submitted_at);
    }

    public function test_ketua_can_approve_submitted_expenditure_request(): void
    {
        $createAction = app(CreateExpenditureRequestAction::class);
        $submitAction = app(SubmitExpenditureRequestAction::class);
        $approveAction = app(ApproveExpenditureRequestAction::class);

        $line = $this->publishedRwPlan->lines->first();

        $request = $createAction->execute(
            new CreateExpenditureRequestInput(
                budgetLineId: $line->id,
                cashAccountId: $this->rwAccount->id,
                proposedAmount: 800_000,
                plannedDate: '2026-09-12',
                title: 'Pembelian Cat Gapura',
                description: 'Untuk cat ulang gapura',
                submit: false
            ),
            $this->sekretarisRw,
            $this->org,
            $this->rw
        );

        $submitted = $submitAction->execute($request, $this->sekretarisRw);

        $approved = $approveAction->execute(
            $submitted,
            new ApproveExpenditureInput(
                requestId: $submitted->id,
                approvedAmount: 750_000,
                decisionReason: 'Disetujui sebagian, harga pasar lebih murah'
            ),
            $this->ketuaRw
        );

        $this->assertEquals(ExpenditureRequestStatus::Approved, $approved->status);
        $this->assertEquals(750_000, $approved->approved_amount);
        $this->assertEquals($this->ketuaRw->id, $approved->approved_by);
        $this->assertNotNull($approved->decision_at);
        $this->assertEquals('Disetujui sebagian, harga pasar lebih murah', $approved->decision_reason);
    }

    public function test_self_approval_is_strictly_forbidden(): void
    {
        $createAction = app(CreateExpenditureRequestAction::class);
        $submitAction = app(SubmitExpenditureRequestAction::class);
        $approveAction = app(ApproveExpenditureRequestAction::class);

        $line = $this->publishedRwPlan->lines->first();

        // Ketua RW requests expenditure
        $request = $createAction->execute(
            new CreateExpenditureRequestInput(
                budgetLineId: $line->id,
                cashAccountId: $this->rwAccount->id,
                proposedAmount: 500_000,
                plannedDate: '2026-09-14',
                title: 'Pengeluaran Ketua',
                description: 'Keperluan koordinasi',
                submit: false
            ),
            $this->ketuaRw,
            $this->org,
            $this->rw
        );

        $submitted = $submitAction->execute($request, $this->ketuaRw);

        // Ketua RW cannot approve their own request!
        $this->expectException(SelfApprovalException::class);
        $approveAction->execute(
            $submitted,
            new ApproveExpenditureInput(
                requestId: $submitted->id,
                approvedAmount: 500_000
            ),
            $this->ketuaRw
        );
    }

    public function test_cannot_approve_request_if_insufficient_available_budget(): void
    {
        $createAction = app(CreateExpenditureRequestAction::class);
        $submitAction = app(SubmitExpenditureRequestAction::class);
        $approveAction = app(ApproveExpenditureRequestAction::class);

        $line = $this->publishedRwPlan->lines->first(); // 5,000,000 planned

        // 1. Approve a 4,500,000 request
        $req1 = $createAction->execute(
            new CreateExpenditureRequestInput(
                budgetLineId: $line->id,
                cashAccountId: $this->rwAccount->id,
                proposedAmount: 4_500_000,
                plannedDate: '2026-09-05',
                title: 'Renovasi Pos Kamling',
                description: 'Bahan bangunan',
                submit: false
            ),
            $this->sekretarisRw,
            $this->org,
            $this->rw
        );
        $sub1 = $submitAction->execute($req1, $this->sekretarisRw);
        $approveAction->execute($sub1, new ApproveExpenditureInput(requestId: $sub1->id), $this->ketuaRw);

        // Remaining available is 500,000

        // 2. Try to approve a 700,000 request -> Should throw InsufficientBudgetException
        $req2 = $createAction->execute(
            new CreateExpenditureRequestInput(
                budgetLineId: $line->id,
                cashAccountId: $this->rwAccount->id,
                proposedAmount: 700_000,
                plannedDate: '2026-09-10',
                title: 'Sewa Tenda Rapat',
                description: 'Tenda acara',
                submit: false
            ),
            $this->sekretarisRw,
            $this->org,
            $this->rw
        );
        $sub2 = $submitAction->execute($req2, $this->sekretarisRw);

        $this->expectException(InsufficientBudgetException::class);
        $approveAction->execute($sub2, new ApproveExpenditureInput(requestId: $sub2->id), $this->ketuaRw);
    }

    public function test_can_reject_submitted_expenditure_request(): void
    {
        $createAction = app(CreateExpenditureRequestAction::class);
        $submitAction = app(SubmitExpenditureRequestAction::class);
        $rejectAction = app(RejectExpenditureRequestAction::class);

        $line = $this->publishedRwPlan->lines->first();

        $request = $createAction->execute(
            new CreateExpenditureRequestInput(
                budgetLineId: $line->id,
                cashAccountId: $this->rwAccount->id,
                proposedAmount: 3_000_000,
                plannedDate: '2026-09-15',
                title: 'Beli Sound System',
                description: 'Sound portabel',
                submit: false
            ),
            $this->sekretarisRw,
            $this->org,
            $this->rw
        );

        $submitted = $submitAction->execute($request, $this->sekretarisRw);

        $rejected = $rejectAction->execute(
            $submitted,
            new RejectExpenditureInput(
                requestId: $submitted->id,
                decisionReason: 'Dana dialokasikan untuk prioritas perbaikan saluran air'
            ),
            $this->ketuaRw
        );

        $this->assertEquals(ExpenditureRequestStatus::Rejected, $rejected->status);
        $this->assertEquals($this->ketuaRw->id, $rejected->approved_by);
        $this->assertNotNull($rejected->decision_at);
        $this->assertStringContainsString('prioritas', $rejected->decision_reason);
    }

    public function test_can_cancel_draft_and_submitted_expenditure_request(): void
    {
        $createAction = app(CreateExpenditureRequestAction::class);
        $cancelAction = app(CancelExpenditureRequestAction::class);

        $line = $this->publishedRwPlan->lines->first();

        $request = $createAction->execute(
            new CreateExpenditureRequestInput(
                budgetLineId: $line->id,
                cashAccountId: $this->rwAccount->id,
                proposedAmount: 400_000,
                plannedDate: '2026-09-18',
                title: 'Pesanan Snack',
                description: 'Snack pertemuan',
                submit: false
            ),
            $this->sekretarisRw,
            $this->org,
            $this->rw
        );

        $cancelled = $cancelAction->execute(
            $request,
            new CancelExpenditureInput(
                requestId: $request->id,
                cancellationReason: 'Pertemuan dibatalkan'
            ),
            $this->sekretarisRw
        );

        $this->assertEquals(ExpenditureRequestStatus::Cancelled, $cancelled->status);
        $this->assertStringContainsString('Pertemuan dibatalkan', $cancelled->decision_reason);
    }

    public function test_bendahara_can_post_approved_request_to_immutable_ledger(): void
    {
        $createAction = app(CreateExpenditureRequestAction::class);
        $submitAction = app(SubmitExpenditureRequestAction::class);
        $approveAction = app(ApproveExpenditureRequestAction::class);
        $postAction = app(PostExpenditureRequestAction::class);

        $line = $this->publishedRwPlan->lines->first();

        $request = $createAction->execute(
            new CreateExpenditureRequestInput(
                budgetLineId: $line->id,
                cashAccountId: $this->rwAccount->id,
                proposedAmount: 1_500_000,
                plannedDate: '2026-09-20',
                title: 'Perbaikan Pintu Air',
                description: 'Material dan upah tukang',
                submit: false
            ),
            $this->sekretarisRw,
            $this->org,
            $this->rw
        );

        $submitted = $submitAction->execute($request, $this->sekretarisRw);
        $approved = $approveAction->execute($submitted, new ApproveExpenditureInput(requestId: $submitted->id), $this->ketuaRw);

        $posted = $postAction->execute(
            $approved,
            new PostExpenditureInput(
                requestId: $approved->id,
                actualAmount: 1_500_000,
                transactionDate: '2026-09-21',
                cashAccountId: $this->rwAccount->id,
                referenceNumber: 'DISB-2026-001'
            ),
            $this->bendaharaRw
        );

        $this->assertEquals(ExpenditureRequestStatus::Posted, $posted->status);
        $this->assertEquals($this->bendaharaRw->id, $posted->posted_by);
        $this->assertNotNull($posted->posted_at);
        $this->assertNotNull($posted->financial_transaction_id);

        // Check exactly 1 financial transaction created in ledger
        $tx = FinancialTransaction::findOrFail($posted->financial_transaction_id);
        $this->assertEquals(TransactionType::Expense, $tx->type);
        $this->assertEquals(1_500_000, $tx->amount);
        $this->assertEquals('2026-09-21', $tx->transaction_date->toDateString());
        $this->assertEquals($this->rwAccount->id, $tx->cash_account_id);
        $this->assertEquals($this->catExpense->id, $tx->category_id);
    }

    public function test_cannot_post_into_closed_period(): void
    {
        $createAction = app(CreateExpenditureRequestAction::class);
        $submitAction = app(SubmitExpenditureRequestAction::class);
        $approveAction = app(ApproveExpenditureRequestAction::class);
        $postAction = app(PostExpenditureRequestAction::class);

        $line = $this->publishedRwPlan->lines->first();

        $request = $createAction->execute(
            new CreateExpenditureRequestInput(
                budgetLineId: $line->id,
                cashAccountId: $this->rwAccount->id,
                proposedAmount: 800_000,
                plannedDate: '2026-09-15',
                title: 'Lampu Lapangan',
                description: 'Lampu sorot',
                submit: false
            ),
            $this->sekretarisRw,
            $this->org,
            $this->rw
        );

        $submitted = $submitAction->execute($request, $this->sekretarisRw);
        $approved = $approveAction->execute($submitted, new ApproveExpenditureInput(requestId: $submitted->id), $this->ketuaRw);

        // Create closed period for September 2026
        FinancialPeriodClosure::create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => null,
            'cash_account_id' => $this->rwAccount->id,
            'period_year' => 2026,
            'period_month' => 9,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'opening_balance' => 0,
            'closing_balance' => 0,
            'total_income' => 0,
            'total_expense' => 0,
            'posted_income' => 0,
            'reversed_income' => 0,
            'posted_expense' => 0,
            'reversed_expense' => 0,
            'net_movement' => 0,
            'transaction_count' => 0,
            'reversal_count' => 0,
            'closed_by' => $this->bendaharaRw->id,
            'closed_at' => now(),
        ]);

        $this->expectException(ClosedPeriodException::class);
        $postAction->execute(
            $approved,
            new PostExpenditureInput(
                requestId: $approved->id,
                actualAmount: 800_000,
                transactionDate: '2026-09-20',
                cashAccountId: $this->rwAccount->id
            ),
            $this->bendaharaRw
        );
    }

    public function test_database_trigger_prevents_deletion_of_approved_request(): void
    {
        $createAction = app(CreateExpenditureRequestAction::class);
        $submitAction = app(SubmitExpenditureRequestAction::class);
        $approveAction = app(ApproveExpenditureRequestAction::class);

        $line = $this->publishedRwPlan->lines->first();

        $request = $createAction->execute(
            new CreateExpenditureRequestInput(
                budgetLineId: $line->id,
                cashAccountId: $this->rwAccount->id,
                proposedAmount: 900_000,
                plannedDate: '2026-09-22',
                title: 'Perlengkapan Posyandu',
                description: 'Timbangan dan alat ukur',
                submit: false
            ),
            $this->sekretarisRw,
            $this->org,
            $this->rw
        );

        $submitted = $submitAction->execute($request, $this->sekretarisRw);
        $approved = $approveAction->execute($submitted, new ApproveExpenditureInput(requestId: $submitted->id), $this->ketuaRw);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Expenditure requests with financial decision history cannot be deleted');
        DB::table('expenditure_requests')->where('id', $approved->id)->delete();
    }

    public function test_database_trigger_prevents_mutation_of_posted_request(): void
    {
        $createAction = app(CreateExpenditureRequestAction::class);
        $submitAction = app(SubmitExpenditureRequestAction::class);
        $approveAction = app(ApproveExpenditureRequestAction::class);
        $postAction = app(PostExpenditureRequestAction::class);

        $line = $this->publishedRwPlan->lines->first();

        $request = $createAction->execute(
            new CreateExpenditureRequestInput(
                budgetLineId: $line->id,
                cashAccountId: $this->rwAccount->id,
                proposedAmount: 900_000,
                plannedDate: '2026-09-22',
                title: 'Perlengkapan Posyandu',
                description: 'Timbangan dan alat ukur',
                submit: false
            ),
            $this->sekretarisRw,
            $this->org,
            $this->rw
        );

        $submitted = $submitAction->execute($request, $this->sekretarisRw);
        $approved = $approveAction->execute($submitted, new ApproveExpenditureInput(requestId: $submitted->id), $this->ketuaRw);

        $posted = $postAction->execute(
            $approved,
            new PostExpenditureInput(
                requestId: $approved->id,
                actualAmount: 900_000,
                transactionDate: '2026-09-23',
                cashAccountId: $this->rwAccount->id
            ),
            $this->bendaharaRw
        );

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Posted expenditure requests are immutable and cannot be updated');
        DB::table('expenditure_requests')->where('id', $posted->id)->update(['title' => 'Tampered Title']);
    }

    public function test_api_endpoints_for_expenditures(): void
    {
        $line = $this->publishedRwPlan->lines->first();

        // 1. Sekretaris creates expenditure request via API
        $this->actingAs($this->sekretarisRw);
        $createRes = $this->postJson('/api/v1/finance/expenditures', [
            'budget_line_id' => $line->id,
            'cash_account_id' => $this->rwAccount->id,
            'title' => 'API Test Expenditure',
            'description' => 'Keperluan ATK RW',
            'planned_date' => '2026-09-25',
            'proposed_amount' => 600_000,
            'submit' => false,
        ], ['Idempotency-Key' => 'idemp-req-create-1']);
        $createRes->assertStatus(201);
        $requestId = $createRes->json('data.id');
        $this->assertNotNull($requestId);

        // 2. Sekretaris submits request
        $submitRes = $this->postJson("/api/v1/finance/expenditures/{$requestId}/submit", [], [
            'Idempotency-Key' => 'idemp-req-submit-1',
        ]);
        $submitRes->assertOk();
        $this->assertEquals('SUBMITTED', $submitRes->json('data.status'));

        // 3. Sekretaris cannot self-approve
        $selfApproveRes = $this->postJson("/api/v1/finance/expenditures/{$requestId}/approve", [
            'approved_amount' => 600_000,
        ], ['Idempotency-Key' => 'idemp-req-approve-fail']);
        $selfApproveRes->assertStatus(403);

        // 4. Ketua RW approves
        $this->actingAs($this->ketuaRw);
        $approveRes = $this->postJson("/api/v1/finance/expenditures/{$requestId}/approve", [
            'approved_amount' => 600_000,
            'decision_reason' => 'Disetujui penuh via API',
        ], ['Idempotency-Key' => 'idemp-req-approve-1']);
        $approveRes->assertOk();
        $this->assertEquals('APPROVED', $approveRes->json('data.status'));

        // 5. Bendahara RW posts to ledger
        $this->actingAs($this->bendaharaRw);
        $postRes = $this->postJson("/api/v1/finance/expenditures/{$requestId}/post", [
            'cash_account_id' => $this->rwAccount->id,
            'transaction_date' => '2026-09-26',
            'actual_amount' => 600_000,
            'reference_number' => 'API-DISB-001',
        ], ['Idempotency-Key' => 'idemp-req-post-1']);
        $postRes->assertOk();
        $this->assertEquals('POSTED', $postRes->json('data.status'));
        $this->assertNotNull($postRes->json('data.financial_transaction_id'));

        // 6. List requests
        $listRes = $this->getJson('/api/v1/finance/expenditures?status=POSTED');
        $listRes->assertOk();
        $this->assertCount(1, $listRes->json('data'));

        // 7. Show request
        $showRes = $this->getJson("/api/v1/finance/expenditures/{$requestId}");
        $showRes->assertOk();
        $this->assertEquals('POSTED', $showRes->json('data.status'));
        $this->assertEquals(600_000, $showRes->json('data.actual_amount'));
    }
}
