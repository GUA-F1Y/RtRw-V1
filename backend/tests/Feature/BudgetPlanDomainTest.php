<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\Finance\Actions\AmendBudgetPlanAction;
use App\Domain\Finance\Actions\CreateBudgetPlanAction;
use App\Domain\Finance\Actions\PublishBudgetPlanAction;
use App\Domain\Finance\Actions\UpdateBudgetPlanAction;
use App\Domain\Finance\DTOs\CreateBudgetPlanInput;
use App\Domain\Finance\DTOs\UpdateBudgetPlanInput;
use App\Domain\Finance\Enums\BudgetPlanStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Exceptions\BudgetValidationException;
use App\Domain\Finance\Exceptions\CategoryTypeMismatchException;
use App\Domain\Finance\Exceptions\FinanceScopeMismatch;
use App\Domain\Finance\Models\BudgetLine;
use App\Domain\Finance\Models\BudgetPlan;
use App\Domain\Finance\Models\FinanceCategory;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class BudgetPlanDomainTest extends TestCase
{
    private Organization $org;
    private Organization $otherOrg;
    private Rw $rw;
    private Rw $otherRw;
    private Rt $rt;
    private Rt $otherRt;

    private User $ketuaRw;
    private User $bendaharaRw;
    private User $ketuaRt;

    private FinanceCategory $catExpense1;
    private FinanceCategory $catExpense2;
    private FinanceCategory $catIncome;
    private FinanceCategory $catRtExpense;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create(['name' => 'Org Budget Test']);
        $this->otherOrg = Organization::factory()->create(['name' => 'Other Org']);

        $this->rw = Rw::factory()->create(['organization_id' => $this->org->id, 'number' => 1]);
        $this->otherRw = Rw::factory()->create(['organization_id' => $this->org->id, 'number' => 2]);

        $this->rt = Rt::factory()->create(['rw_id' => $this->rw->id, 'number' => 1]);
        $this->otherRt = Rt::factory()->create(['rw_id' => $this->otherRw->id, 'number' => 1]);

        $this->ketuaRw = $this->createScopedUser(RoleKey::KetuaRw, $this->org, $this->rw);
        $this->bendaharaRw = $this->createScopedUser(RoleKey::BendaharaRw, $this->org, $this->rw);
        $this->ketuaRt = $this->createScopedUser(RoleKey::KetuaRt, $this->org, $this->rw, $this->rt);

        $this->catExpense1 = FinanceCategory::create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'name' => 'Operasional Kebersihan',
            'type' => TransactionType::Expense,
            'is_active' => true,
        ]);

        $this->catExpense2 = FinanceCategory::create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'name' => 'Keamanan Lingkungan',
            'type' => TransactionType::Expense,
            'is_active' => true,
        ]);

        $this->catIncome = FinanceCategory::create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'name' => 'Iuran Warga',
            'type' => TransactionType::Income,
            'is_active' => true,
        ]);

        $this->catRtExpense = FinanceCategory::create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'rt_id' => $this->rt->id,
            'name' => 'Konsumsi Kerja Bakti RT',
            'type' => TransactionType::Expense,
            'is_active' => true,
        ]);
    }

    public function test_create_valid_rw_budget_plan(): void
    {
        $action = app(CreateBudgetPlanAction::class);
        $input = new CreateBudgetPlanInput(
            year: 2026,
            month: 11,
            notes: 'Rencana Anggaran RW Bulan November',
            lines: [
                ['category_id' => $this->catExpense1->id, 'planned_amount' => 5_000_000, 'notes' => 'Truk sampah'],
                ['category_id' => $this->catExpense2->id, 'planned_amount' => 3_500_000, 'notes' => 'Honor satpam'],
            ]
        );

        $plan = $action->execute($input, $this->bendaharaRw, $this->org, $this->rw);

        $this->assertInstanceOf(BudgetPlan::class, $plan);
        $this->assertEquals(BudgetPlanStatus::Draft, $plan->status);
        $this->assertEquals(1, $plan->version);
        $this->assertEquals(2026, $plan->year);
        $this->assertEquals(11, $plan->month);
        $this->assertNull($plan->rt_id);
        $this->assertCount(2, $plan->lines);
        $this->assertEquals(8_500_000, $plan->lines()->sum('planned_amount'));
    }

    public function test_create_valid_rt_budget_plan(): void
    {
        $action = app(CreateBudgetPlanAction::class);
        $input = new CreateBudgetPlanInput(
            year: 2026,
            month: 11,
            rtId: $this->rt->id,
            notes: 'Rencana Anggaran RT 01',
            lines: [
                ['category_id' => $this->catRtExpense->id, 'planned_amount' => 1_000_000],
            ]
        );

        $plan = $action->execute($input, $this->ketuaRt, $this->org, $this->rw, $this->rt);

        $this->assertEquals($this->rt->id, $plan->rt_id);
        $this->assertEquals(BudgetPlanStatus::Draft, $plan->status);
        $this->assertCount(1, $plan->lines);
    }

    public function test_reject_mismatched_scope_combination(): void
    {
        $action = app(CreateBudgetPlanAction::class);
        $input = new CreateBudgetPlanInput(
            year: 2026,
            month: 11,
            rtId: $this->otherRt->id, // Belongs to otherRw!
            lines: [
                ['category_id' => $this->catExpense1->id, 'planned_amount' => 1_000_000],
            ]
        );

        $this->expectException(FinanceScopeMismatch::class);
        $action->execute($input, $this->bendaharaRw, $this->org, $this->rw, $this->otherRt);
    }

    public function test_reject_budgeting_income_categories(): void
    {
        $action = app(CreateBudgetPlanAction::class);
        $input = new CreateBudgetPlanInput(
            year: 2026,
            month: 11,
            lines: [
                ['category_id' => $this->catIncome->id, 'planned_amount' => 2_000_000],
            ]
        );

        $this->expectException(CategoryTypeMismatchException::class);
        $action->execute($input, $this->bendaharaRw, $this->org, $this->rw);
    }

    public function test_reject_duplicate_categories_in_same_plan(): void
    {
        $action = app(CreateBudgetPlanAction::class);
        $input = new CreateBudgetPlanInput(
            year: 2026,
            month: 11,
            lines: [
                ['category_id' => $this->catExpense1->id, 'planned_amount' => 2_000_000],
                ['category_id' => $this->catExpense1->id, 'planned_amount' => 3_000_000],
            ]
        );

        $this->expectException(BudgetValidationException::class);
        $action->execute($input, $this->bendaharaRw, $this->org, $this->rw);
    }

    public function test_reject_negative_budget_amount(): void
    {
        $action = app(CreateBudgetPlanAction::class);
        $input = new CreateBudgetPlanInput(
            year: 2026,
            month: 11,
            lines: [
                ['category_id' => $this->catExpense1->id, 'planned_amount' => -500_000],
            ]
        );

        $this->expectException(BudgetValidationException::class);
        $action->execute($input, $this->bendaharaRw, $this->org, $this->rw);
    }

    public function test_update_draft_budget_plan(): void
    {
        $createAction = app(CreateBudgetPlanAction::class);
        $plan = $createAction->execute(
            new CreateBudgetPlanInput(
                year: 2026,
                month: 12,
                notes: 'Draft Awal',
                lines: [
                    ['category_id' => $this->catExpense1->id, 'planned_amount' => 1_000_000],
                ]
            ),
            $this->bendaharaRw,
            $this->org,
            $this->rw
        );

        $updateAction = app(UpdateBudgetPlanAction::class);
        $updated = $updateAction->execute(
            $plan,
            new UpdateBudgetPlanInput(
                notes: 'Draft Revisi Akhir',
                lines: [
                    ['category_id' => $this->catExpense1->id, 'planned_amount' => 1_500_000],
                    ['category_id' => $this->catExpense2->id, 'planned_amount' => 2_500_000],
                ]
            ),
            $this->bendaharaRw
        );

        $this->assertEquals('Draft Revisi Akhir', $updated->notes);
        $this->assertCount(2, $updated->lines);
        $this->assertEquals(4_000_000, $updated->lines()->sum('planned_amount'));
    }

    public function test_publish_budget_plan_and_supersede_prior_version(): void
    {
        $createAction = app(CreateBudgetPlanAction::class);
        $publishAction = app(PublishBudgetPlanAction::class);

        $plan1 = $createAction->execute(
            new CreateBudgetPlanInput(
                year: 2026,
                month: 10,
                lines: [
                    ['category_id' => $this->catExpense1->id, 'planned_amount' => 5_000_000],
                ]
            ),
            $this->bendaharaRw,
            $this->org,
            $this->rw
        );

        $published1 = $publishAction->execute($plan1, $this->ketuaRw);
        $this->assertEquals(BudgetPlanStatus::Published, $published1->status);
        $this->assertEquals($this->ketuaRw->id, $published1->published_by);
        $this->assertNotNull($published1->published_at);

        // Amending creates version 2 draft
        $amendAction = app(AmendBudgetPlanAction::class);
        $plan2Draft = $amendAction->execute($published1, $this->bendaharaRw, 'Amandemen penyesuaian biaya');
        $this->assertEquals(2, $plan2Draft->version);
        $this->assertEquals(BudgetPlanStatus::Draft, $plan2Draft->status);

        // Publish version 2
        $published2 = $publishAction->execute($plan2Draft, $this->ketuaRw);
        $this->assertEquals(BudgetPlanStatus::Published, $published2->status);

        // Version 1 should now be SUPERSEDED
        $published1->refresh();
        $this->assertEquals(BudgetPlanStatus::Superseded, $published1->status);
    }

    public function test_published_budget_lines_cannot_be_mutated_trigger(): void
    {
        $createAction = app(CreateBudgetPlanAction::class);
        $publishAction = app(PublishBudgetPlanAction::class);

        $plan = $createAction->execute(
            new CreateBudgetPlanInput(
                year: 2026,
                month: 9,
                lines: [
                    ['category_id' => $this->catExpense1->id, 'planned_amount' => 3_000_000],
                ]
            ),
            $this->bendaharaRw,
            $this->org,
            $this->rw
        );

        $publishAction->execute($plan, $this->ketuaRw);

        $line = $plan->lines()->first();

        // Attempting to update or delete via DB will trigger PostgreSQL exception
        $this->expectException(QueryException::class);
        $line->update(['planned_amount' => 99_000_000]);
    }

    public function test_api_endpoints_for_budget_plans(): void
    {
        $this->actAsScopedUser(RoleKey::BendaharaRw, $this->org, $this->rw);

        // POST /api/v1/finance/budgets
        $res = $this->postJson('/api/v1/finance/budgets', [
            'year' => 2027,
            'month' => 1,
            'notes' => 'Budget Januari 2027',
            'lines' => [
                ['category_id' => $this->catExpense1->id, 'planned_amount' => 4_000_000],
            ],
        ]);

        $res->assertStatus(201);
        $budgetId = $res->json('data.id');
        $this->assertNotNull($budgetId);

        // GET /api/v1/finance/budgets
        $listRes = $this->getJson('/api/v1/finance/budgets?year=2027&month=1');
        $listRes->assertOk();
        $this->assertCount(1, $listRes->json('data'));

        // GET /api/v1/finance/budgets/{id}
        $showRes = $this->getJson("/api/v1/finance/budgets/{$budgetId}");
        $showRes->assertOk();
        $this->assertEquals(4_000_000, $showRes->json('data.summary.total_planned'));

        // Switch to Ketua RW to publish
        $this->actAsScopedUser(RoleKey::KetuaRw, $this->org, $this->rw);
        $publishRes = $this->postJson("/api/v1/finance/budgets/{$budgetId}/publish", [
            'confirmation' => true,
        ], ['Idempotency-Key' => 'idemp-publish-1']);
        $publishRes->assertOk();
        $this->assertEquals('PUBLISHED', $publishRes->json('data.status'));
    }
}
