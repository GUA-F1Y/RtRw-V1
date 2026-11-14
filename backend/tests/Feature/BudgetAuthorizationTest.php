<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\Finance\Actions\ApproveExpenditureRequestAction;
use App\Domain\Finance\Actions\CreateBudgetPlanAction;
use App\Domain\Finance\Actions\CreateExpenditureRequestAction;
use App\Domain\Finance\Actions\PublishBudgetPlanAction;
use App\Domain\Finance\Actions\SubmitExpenditureRequestAction;
use App\Domain\Finance\DTOs\ApproveExpenditureInput;
use App\Domain\Finance\DTOs\CreateBudgetPlanInput;
use App\Domain\Finance\DTOs\CreateExpenditureRequestInput;
use App\Domain\Finance\Enums\CashAccountStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\BudgetPlan;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\FinanceCategory;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Tests\TestCase;

class BudgetAuthorizationTest extends TestCase
{
    private Organization $orgA;
    private Organization $orgB;
    private Rw $rwA;
    private Rw $rwB;
    private Rt $rtA1;
    private Rt $rtA2;

    private User $ketuaRwA;
    private User $bendaharaRwA;
    private User $ketuaRtA1;
    private User $ketuaRtA2;
    private User $wargaA;

    private CashAccount $rwAccountA;
    private FinanceCategory $catExpenseA;
    private BudgetPlan $publishedPlanA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::factory()->create(['name' => 'Org Auth A']);
        $this->orgB = Organization::factory()->create(['name' => 'Org Auth B']);

        $this->rwA = Rw::factory()->create(['organization_id' => $this->orgA->id, 'number' => 1]);
        $this->rwB = Rw::factory()->create(['organization_id' => $this->orgB->id, 'number' => 1]);

        $this->rtA1 = Rt::factory()->create(['rw_id' => $this->rwA->id, 'number' => 1]);
        $this->rtA2 = Rt::factory()->create(['rw_id' => $this->rwA->id, 'number' => 2]);

        $this->ketuaRwA = $this->createScopedUser(RoleKey::KetuaRw, $this->orgA, $this->rwA);
        $this->bendaharaRwA = $this->createScopedUser(RoleKey::BendaharaRw, $this->orgA, $this->rwA);
        $this->ketuaRtA1 = $this->createScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA, $this->rtA1);
        $this->ketuaRtA2 = $this->createScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA, $this->rtA2);
        $this->wargaA = User::factory()->create();

        $this->rwAccountA = CashAccount::create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA->id,
            'rt_id' => null,
            'name' => 'Kas RW A',
            'account_number' => 'ACC-AUTH-01',
            'type' => 'OPERATIONAL',
            'status' => CashAccountStatus::Active,
        ]);

        $this->catExpenseA = FinanceCategory::create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA->id,
            'name' => 'Biaya Ronda',
            'code' => 'EXP-RONDA',
            'type' => TransactionType::Expense,
            'is_system' => false,
            'is_active' => true,
        ]);

        $createPlanAction = app(CreateBudgetPlanAction::class);
        $publishAction = app(PublishBudgetPlanAction::class);

        $draft = $createPlanAction->execute(
            new CreateBudgetPlanInput(
                year: 2026,
                month: 11,
                lines: [
                    ['category_id' => $this->catExpenseA->id, 'planned_amount' => 5_000_000],
                ]
            ),
            $this->bendaharaRwA,
            $this->orgA,
            $this->rwA
        );

        $this->publishedPlanA = $publishAction->execute($draft, $this->ketuaRwA);
    }

    public function test_warga_cannot_create_budget_plan_or_expenditure_request(): void
    {
        $this->actingAs($this->wargaA);

        // Cannot create budget
        $budgetRes = $this->postJson('/api/v1/finance/budgets', [
            'year' => 2026,
            'month' => 12,
            'lines' => [
                ['category_id' => $this->catExpenseA->id, 'planned_amount' => 1_000_000],
            ],
        ], ['Idempotency-Key' => 'idemp-warga-budget']);
        $budgetRes->assertStatus(403);

        // Cannot create expenditure request
        $line = $this->publishedPlanA->lines->first();
        $expRes = $this->postJson('/api/v1/finance/expenditures', [
            'budget_line_id' => $line->id,
            'cash_account_id' => $this->rwAccountA->id,
            'proposed_amount' => 500_000,
            'planned_date' => '2026-11-10',
            'title' => 'Permintaan Warga',
        ], ['Idempotency-Key' => 'idemp-warga-exp']);
        $expRes->assertStatus(403);
    }

    public function test_bendahara_cannot_approve_expenditure_request(): void
    {
        $createAction = app(CreateExpenditureRequestAction::class);
        $submitAction = app(SubmitExpenditureRequestAction::class);

        $line = $this->publishedPlanA->lines->first();

        $req = $createAction->execute(
            new CreateExpenditureRequestInput(
                budgetLineId: $line->id,
                cashAccountId: $this->rwAccountA->id,
                proposedAmount: 1_000_000,
                plannedDate: '2026-11-12',
                title: 'Pengadaan Lampu Senter Ronda',
                submit: false
            ),
            $this->bendaharaRwA,
            $this->orgA,
            $this->rwA
        );

        $sub = $submitAction->execute($req, $this->bendaharaRwA);

        // Bendahara attempts to approve -> 403 Forbidden (Ketua approves)
        $this->actingAs($this->bendaharaRwA);
        $res = $this->postJson("/api/v1/finance/expenditures/{$sub->id}/approve", [
            'approved_amount' => 1_000_000,
        ], ['Idempotency-Key' => 'idemp-bend-approve']);

        $res->assertStatus(403);
    }

    public function test_ketua_cannot_post_expenditure_request(): void
    {
        $createAction = app(CreateExpenditureRequestAction::class);
        $submitAction = app(SubmitExpenditureRequestAction::class);
        $approveAction = app(ApproveExpenditureRequestAction::class);

        $line = $this->publishedPlanA->lines->first();

        // Created by Bendahara, approved by Ketua
        $req = $createAction->execute(
            new CreateExpenditureRequestInput(
                budgetLineId: $line->id,
                cashAccountId: $this->rwAccountA->id,
                proposedAmount: 1_000_000,
                plannedDate: '2026-11-15',
                title: 'Baterai Senter Ronda',
                submit: false
            ),
            $this->bendaharaRwA,
            $this->orgA,
            $this->rwA
        );

        $sub = $submitAction->execute($req, $this->bendaharaRwA);
        $approved = $approveAction->execute($sub, new ApproveExpenditureInput(requestId: $sub->id), $this->ketuaRwA);

        // Ketua attempts to post to ledger -> 403 Forbidden (Bendahara posts)
        $this->actingAs($this->ketuaRwA);
        $res = $this->postJson("/api/v1/finance/expenditures/{$approved->id}/post", [
            'cash_account_id' => $this->rwAccountA->id,
            'transaction_date' => '2026-11-16',
            'actual_amount' => 1_000_000,
        ], ['Idempotency-Key' => 'idemp-ketua-post']);

        $res->assertStatus(403);
    }

    public function test_rt_user_scoped_isolation(): void
    {
        $createPlanAction = app(CreateBudgetPlanAction::class);

        // RT 01 Plan
        $planRt1 = $createPlanAction->execute(
            new CreateBudgetPlanInput(
                year: 2026,
                month: 11,
                lines: [
                    ['category_id' => $this->catExpenseA->id, 'planned_amount' => 800_000],
                ]
            ),
            $this->ketuaRtA1,
            $this->orgA,
            $this->rwA,
            $this->rtA1
        );

        // RT 02 Plan
        $planRt2 = $createPlanAction->execute(
            new CreateBudgetPlanInput(
                year: 2026,
                month: 11,
                lines: [
                    ['category_id' => $this->catExpenseA->id, 'planned_amount' => 900_000],
                ]
            ),
            $this->ketuaRtA2,
            $this->orgA,
            $this->rwA,
            $this->rtA2
        );

        // Ketua RT 01 tries to view RT 02 plan details -> 403 Forbidden
        $this->actingAs($this->ketuaRtA1);
        $res = $this->getJson("/api/v1/finance/budgets/{$planRt2->id}");
        $res->assertStatus(403);

        // Ketua RT 01 listing budgets only sees their own RT plan and RW plan
        $listRes = $this->getJson('/api/v1/finance/budgets?year=2026&month=11');
        $listRes->assertOk();
        $ids = collect($listRes->json('data'))->pluck('id')->all();
        $this->assertContains($planRt1->id, $ids);
        $this->assertNotContains($planRt2->id, $ids);
    }
}
