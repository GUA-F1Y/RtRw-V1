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
use App\Domain\Finance\Actions\RecordFinancialTransactionAction;
use App\Domain\Finance\Actions\ReverseFinancialTransactionAction;
use App\Domain\Finance\Actions\SubmitExpenditureRequestAction;
use App\Domain\Finance\DTOs\ApproveExpenditureInput;
use App\Domain\Finance\DTOs\CancelExpenditureInput;
use App\Domain\Finance\DTOs\CreateBudgetPlanInput;
use App\Domain\Finance\DTOs\CreateExpenditureRequestInput;
use App\Domain\Finance\DTOs\PostExpenditureInput;
use App\Domain\Finance\DTOs\RecordFinancialTransactionInput;
use App\Domain\Finance\DTOs\ReverseFinancialTransactionInput;
use App\Domain\Finance\Enums\CashAccountStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\BudgetPlan;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\FinanceCategory;
use App\Domain\Finance\Queries\BudgetUtilizationSummaryQuery;
use App\Domain\Finance\Services\BudgetCalculationService;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Tests\TestCase;

class BudgetCalculationTest extends TestCase
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

    private BudgetCalculationService $calcService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calcService = app(BudgetCalculationService::class);

        $this->org = Organization::factory()->create(['name' => 'Org Calc Test']);
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
            'account_number' => 'ACC-RW-CALC',
            'type' => 'OPERATIONAL',
            'status' => CashAccountStatus::Active,
        ]);

        $this->rtAccount = CashAccount::create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'name' => 'Kas Operasional RT 01',
            'account_number' => 'ACC-RT-CALC',
            'type' => 'OPERATIONAL',
            'status' => CashAccountStatus::Active,
        ]);

        $this->catExpense = FinanceCategory::create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'name' => 'Operasional Kebersihan',
            'code' => 'EXP-CLEAN',
            'type' => TransactionType::Expense,
            'is_system' => false,
            'is_active' => true,
        ]);

        $createPlanAction = app(CreateBudgetPlanAction::class);
        $publishAction = app(PublishBudgetPlanAction::class);

        $draft = $createPlanAction->execute(
            new CreateBudgetPlanInput(
                year: 2026,
                month: 10,
                lines: [
                    ['category_id' => $this->catExpense->id, 'planned_amount' => 10_000_000],
                ]
            ),
            $this->bendaharaRw,
            $this->org,
            $this->rw
        );

        $this->publishedRwPlan = $publishAction->execute($draft, $this->ketuaRw);
    }

    public function test_formula_available_budget_with_commitments_and_actuals(): void
    {
        $line = $this->publishedRwPlan->lines->first();

        // Initial state: planned = 10,000,000, commitments = 0, actual = 0, remaining = 10,000,000
        $initialMetrics = $this->calcService->calculateLineMetrics($line);
        $this->assertEquals(10_000_000, $initialMetrics['planned_amount']);
        $this->assertEquals(0, $initialMetrics['outstanding_commitments']);
        $this->assertEquals(0, $initialMetrics['effective_actual_expenses']);
        $this->assertEquals(10_000_000, $initialMetrics['remaining_budget']);

        // Create, submit, and approve Rp 3,000,000 request
        $createAction = app(CreateExpenditureRequestAction::class);
        $submitAction = app(SubmitExpenditureRequestAction::class);
        $approveAction = app(ApproveExpenditureRequestAction::class);
        $postAction = app(PostExpenditureRequestAction::class);

        $req = $createAction->execute(
            new CreateExpenditureRequestInput(
                budgetLineId: $line->id,
                cashAccountId: $this->rwAccount->id,
                proposedAmount: 3_000_000,
                plannedDate: '2026-10-05',
                title: 'Alat Kebersihan',
                submit: false
            ),
            $this->sekretarisRw,
            $this->org,
            $this->rw
        );

        // Draft does not consume commitments
        $draftMetrics = $this->calcService->calculateLineMetrics($line);
        $this->assertEquals(0, $draftMetrics['outstanding_commitments']);
        $this->assertEquals(10_000_000, $draftMetrics['remaining_budget']);

        $sub = $submitAction->execute($req, $this->sekretarisRw);

        // Submitted does not consume commitments until approved
        $subMetrics = $this->calcService->calculateLineMetrics($line);
        $this->assertEquals(0, $subMetrics['outstanding_commitments']);

        $approved = $approveAction->execute($sub, new ApproveExpenditureInput(requestId: $sub->id), $this->ketuaRw);

        // Approved consumes Rp 3,000,000 commitment
        $approvedMetrics = $this->calcService->calculateLineMetrics($line);
        $this->assertEquals(3_000_000, $approvedMetrics['outstanding_commitments']);
        $this->assertEquals(0, $approvedMetrics['effective_actual_expenses']);
        $this->assertEquals(7_000_000, $approvedMetrics['remaining_budget']);

        // Post request with actual amount Rp 2,800,000
        $postAction->execute(
            $approved,
            new PostExpenditureInput(
                requestId: $approved->id,
                actualAmount: 2_800_000,
                transactionDate: '2026-10-06',
                cashAccountId: $this->rwAccount->id
            ),
            $this->bendaharaRw
        );

        // Once posted: commitment drops to 0, actual becomes 2,800,000, remaining = 10,000,000 - 2,800,000 = 7,200,000
        $postedMetrics = $this->calcService->calculateLineMetrics($line);
        $this->assertEquals(0, $postedMetrics['outstanding_commitments']);
        $this->assertEquals(2_800_000, $postedMetrics['effective_actual_expenses']);
        $this->assertEquals(7_200_000, $postedMetrics['remaining_budget']);
    }

    public function test_direct_ledger_expenses_reduce_available_budget(): void
    {
        $line = $this->publishedRwPlan->lines->first();

        // Bendahara records a direct transaction without prior expenditure request
        $recordAction = app(RecordFinancialTransactionAction::class);
        $recordAction->execute(
            new RecordFinancialTransactionInput(
                cashAccountId: $this->rwAccount->id,
                categoryId: $this->catExpense->id,
                type: TransactionType::Expense,
                amount: 1_500_000,
                transactionDate: '2026-10-12',
                description: 'Pembelian sapu dan tempat sampah darurat'
            ),
            $this->bendaharaRw
        );

        $metrics = $this->calcService->calculateLineMetrics($line);
        $this->assertEquals(1_500_000, $metrics['effective_actual_expenses']);
        $this->assertEquals(8_500_000, $metrics['remaining_budget']);
    }

    public function test_compensating_reversals_restore_available_budget(): void
    {
        $line = $this->publishedRwPlan->lines->first();
        $recordAction = app(RecordFinancialTransactionAction::class);
        $reverseAction = app(ReverseFinancialTransactionAction::class);

        // 1. Post expense
        $tx = $recordAction->execute(
            new RecordFinancialTransactionInput(
                cashAccountId: $this->rwAccount->id,
                categoryId: $this->catExpense->id,
                type: TransactionType::Expense,
                amount: 2_000_000,
                transactionDate: '2026-10-14',
                description: 'Biaya sewa truk sampah'
            ),
            $this->bendaharaRw
        );

        $metricsAfterExpense = $this->calcService->calculateLineMetrics($line);
        $this->assertEquals(2_000_000, $metricsAfterExpense['effective_actual_expenses']);
        $this->assertEquals(8_000_000, $metricsAfterExpense['remaining_budget']);

        // 2. Reverse expense
        $reverseAction->execute(
            new ReverseFinancialTransactionInput(
                transactionId: $tx->id,
                reversalReason: 'Pembatalan sewa truk oleh pihak ketiga',
                transactionDate: '2026-10-15'
            ),
            $this->bendaharaRw
        );

        $metricsAfterReversal = $this->calcService->calculateLineMetrics($line);
        $this->assertEquals(0, $metricsAfterReversal['effective_actual_expenses']);
        $this->assertEquals(2_000_000, $metricsAfterReversal['posted_expenses']);
        $this->assertEquals(2_000_000, $metricsAfterReversal['reversed_expenses']);
        $this->assertEquals(10_000_000, $metricsAfterReversal['remaining_budget']);
    }

    public function test_cancelled_or_rejected_requests_release_commitments(): void
    {
        $line = $this->publishedRwPlan->lines->first();
        $createAction = app(CreateExpenditureRequestAction::class);
        $submitAction = app(SubmitExpenditureRequestAction::class);
        $approveAction = app(ApproveExpenditureRequestAction::class);
        $cancelAction = app(CancelExpenditureRequestAction::class);

        $req = $createAction->execute(
            new CreateExpenditureRequestInput(
                budgetLineId: $line->id,
                cashAccountId: $this->rwAccount->id,
                proposedAmount: 4_000_000,
                plannedDate: '2026-10-18',
                title: 'Alat Fogging',
                submit: false
            ),
            $this->sekretarisRw,
            $this->org,
            $this->rw
        );

        $sub = $submitAction->execute($req, $this->sekretarisRw);
        $approved = $approveAction->execute($sub, new ApproveExpenditureInput(requestId: $sub->id), $this->ketuaRw);

        $metricsApproved = $this->calcService->calculateLineMetrics($line);
        $this->assertEquals(4_000_000, $metricsApproved['outstanding_commitments']);
        $this->assertEquals(6_000_000, $metricsApproved['remaining_budget']);

        // Cancel approved request (e.g. vendor cancelled)
        $cancelAction->execute(
            $approved,
            new CancelExpenditureInput(requestId: $approved->id, cancellationReason: 'Vendor fogging tidak tersedia'),
            $this->sekretarisRw
        );

        $metricsCancelled = $this->calcService->calculateLineMetrics($line);
        $this->assertEquals(0, $metricsCancelled['outstanding_commitments']);
        $this->assertEquals(10_000_000, $metricsCancelled['remaining_budget']);
    }

    public function test_scope_isolation_between_rw_and_rt_budget_lines(): void
    {
        $createPlanAction = app(CreateBudgetPlanAction::class);
        $publishAction = app(PublishBudgetPlanAction::class);
        $createReqAction = app(CreateExpenditureRequestAction::class);
        $submitAction = app(SubmitExpenditureRequestAction::class);
        $approveAction = app(ApproveExpenditureRequestAction::class);

        // Create and publish RT 01 plan for 2026-10 with Rp 2,000,000
        $rtDraft = $createPlanAction->execute(
            new CreateBudgetPlanInput(
                year: 2026,
                month: 10,
                lines: [
                    ['category_id' => $this->catExpense->id, 'planned_amount' => 2_000_000],
                ]
            ),
            $this->bendaharaRt,
            $this->org,
            $this->rw,
            $this->rt
        );

        $rtPublished = $publishAction->execute($rtDraft, $this->ketuaRt);

        $rwLine = $this->publishedRwPlan->lines->first();
        $rtLine = $rtPublished->lines->first();

        // Submit and approve RT expenditure of Rp 800,000
        $rtReq = $createReqAction->execute(
            new CreateExpenditureRequestInput(
                budgetLineId: $rtLine->id,
                cashAccountId: $this->rtAccount->id,
                proposedAmount: 800_000,
                plannedDate: '2026-10-20',
                title: 'Kerja Bakti RT 01',
                submit: false
            ),
            $this->bendaharaRt,
            $this->org,
            $this->rw,
            $this->rt
        );
        $rtSub = $submitAction->execute($rtReq, $this->bendaharaRt);
        $approveAction->execute($rtSub, new ApproveExpenditureInput(requestId: $rtSub->id), $this->ketuaRt);

        // RT Line metrics: commitment 800,000, remaining 1,200,000
        $rtMetrics = $this->calcService->calculateLineMetrics($rtLine);
        $this->assertEquals(800_000, $rtMetrics['outstanding_commitments']);
        $this->assertEquals(1_200_000, $rtMetrics['remaining_budget']);

        // RW Line metrics: UNTOUCHED! Commitments 0, remaining 10,000,000
        $rwMetrics = $this->calcService->calculateLineMetrics($rwLine);
        $this->assertEquals(0, $rwMetrics['outstanding_commitments']);
        $this->assertEquals(10_000_000, $rwMetrics['remaining_budget']);
    }

    public function test_budget_utilization_summary_query(): void
    {
        $query = app(BudgetUtilizationSummaryQuery::class);

        // Record some actual expense in RW
        $recordAction = app(RecordFinancialTransactionAction::class);
        $recordAction->execute(
            new RecordFinancialTransactionInput(
                cashAccountId: $this->rwAccount->id,
                categoryId: $this->catExpense->id,
                type: TransactionType::Expense,
                amount: 3_000_000,
                transactionDate: '2026-10-22',
                description: 'Pengangkutan sampah minggu ke-3'
            ),
            $this->bendaharaRw
        );

        $result = $query->execute($this->ketuaRw, ['year' => 2026, 'month' => 10]);

        $summary = $result['summary'];
        $this->assertEquals(10_000_000, $summary['total_planned']);
        $this->assertEquals(3_000_000, $summary['total_actual']);
        $this->assertEquals(7_000_000, $summary['total_remaining']);
        $this->assertEquals(30.0, $summary['overall_utilization_percentage']);
        $this->assertCount(1, $result['plans']);
    }
}
