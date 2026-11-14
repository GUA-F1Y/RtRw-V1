<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Finance\Enums\CashAccountStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\FinanceCategory;
use App\Domain\Finance\Models\FinancialTransaction;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use App\Policies\CashAccountPolicy;
use App\Policies\FinanceCategoryPolicy;
use App\Policies\FinancialTransactionPolicy;
use Tests\TestCase;

class FinanceAuthorizationPolicyTest extends TestCase
{
    private Organization $orgA;
    private Organization $orgB;

    private Rw $rwA1;
    private Rw $rwA2;
    private Rw $rwB1;

    private Rt $rtA1_1;
    private Rt $rtA1_2;
    private Rt $rtB1_1;

    private CashAccount $accountRwA1;
    private CashAccount $accountRtA1_1;
    private CashAccount $accountRtA1_2;
    private CashAccount $accountRwA2;
    private CashAccount $accountRtB1_1;

    private FinanceCategory $categoryA;
    private FinancialTransaction $transactionRtA1_1;
    private FinancialTransaction $transactionRwA1;

    private User $ketuaRwA1;
    private User $bendaharaRwA1;
    private User $sekretarisRwA1;

    private User $ketuaRtA1_1;
    private User $bendaharaRtA1_1;
    private User $sekretarisRtA1_1;

    private User $ketuaRtA1_2;
    private User $ketuaRwA2;
    private User $orgAdminA;
    private User $ketuaRtB1_1;
    private User $regularUser;

    private CashAccountPolicy $accountPolicy;
    private FinanceCategoryPolicy $categoryPolicy;
    private FinancialTransactionPolicy $transactionPolicy;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Hierarchy: Organization A
        $this->orgA = Organization::factory()->create(['name' => 'Org A']);
        $this->rwA1 = Rw::factory()->create(['organization_id' => $this->orgA->id, 'number' => 1]);
        $this->rwA2 = Rw::factory()->create(['organization_id' => $this->orgA->id, 'number' => 2]);
        $this->rtA1_1 = Rt::factory()->create(['rw_id' => $this->rwA1->id, 'number' => 1]);
        $this->rtA1_2 = Rt::factory()->create(['rw_id' => $this->rwA1->id, 'number' => 2]);

        // 2. Hierarchy: Organization B
        $this->orgB = Organization::factory()->create(['name' => 'Org B']);
        $this->rwB1 = Rw::factory()->create(['organization_id' => $this->orgB->id, 'number' => 1]);
        $this->rtB1_1 = Rt::factory()->create(['rw_id' => $this->rwB1->id, 'number' => 1]);

        // 3. Financial Accounts
        $this->accountRwA1 = CashAccount::create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => null,
            'name' => 'Kas RW 01',
            'status' => CashAccountStatus::Active,
            'opening_balance' => 5000000,
        ]);

        $this->accountRtA1_1 = CashAccount::create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'name' => 'Kas RT 01',
            'status' => CashAccountStatus::Active,
            'opening_balance' => 1000000,
        ]);

        $this->accountRtA1_2 = CashAccount::create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_2->id,
            'name' => 'Kas RT 02',
            'status' => CashAccountStatus::Active,
            'opening_balance' => 2000000,
        ]);

        $this->accountRwA2 = CashAccount::create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA2->id,
            'rt_id' => null,
            'name' => 'Kas RW 02',
            'status' => CashAccountStatus::Active,
            'opening_balance' => 3000000,
        ]);

        $this->accountRtB1_1 = CashAccount::create([
            'organization_id' => $this->orgB->id,
            'rw_id' => $this->rwB1->id,
            'rt_id' => $this->rtB1_1->id,
            'name' => 'Kas RT 01 Org B',
            'status' => CashAccountStatus::Active,
            'opening_balance' => 500000,
        ]);

        // 4. Category
        $this->categoryA = FinanceCategory::create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'name' => 'Iuran Warga',
            'type' => TransactionType::Income,
            'is_active' => true,
        ]);

        // 5. User for recording
        $recorder = User::factory()->create();

        // 6. Transactions
        $this->transactionRtA1_1 = FinancialTransaction::create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => $this->rtA1_1->id,
            'cash_account_id' => $this->accountRtA1_1->id,
            'category_id' => $this->categoryA->id,
            'type' => TransactionType::Income,
            'amount' => 100000,
            'transaction_date' => '2026-10-14',
            'description' => 'Iuran warga RT 01',
            'recorded_by' => $recorder->id,
        ]);

        $this->transactionRwA1 = FinancialTransaction::create([
            'organization_id' => $this->orgA->id,
            'rw_id' => $this->rwA1->id,
            'rt_id' => null,
            'cash_account_id' => $this->accountRwA1->id,
            'category_id' => $this->categoryA->id,
            'type' => TransactionType::Income,
            'amount' => 500000,
            'transaction_date' => '2026-10-14',
            'description' => 'Iuran tingkat RW',
            'recorded_by' => $recorder->id,
        ]);

        // 7. Scoped Users
        $this->ketuaRwA1 = $this->createScopedUser(RoleKey::KetuaRw, $this->orgA, $this->rwA1);
        $this->bendaharaRwA1 = $this->createScopedUser(RoleKey::BendaharaRw, $this->orgA, $this->rwA1);
        $this->sekretarisRwA1 = $this->createScopedUser(RoleKey::SekretarisRw, $this->orgA, $this->rwA1);

        $this->ketuaRtA1_1 = $this->createScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        $this->bendaharaRtA1_1 = $this->createScopedUser(RoleKey::BendaharaRt, $this->orgA, $this->rwA1, $this->rtA1_1);
        $this->sekretarisRtA1_1 = $this->createScopedUser(RoleKey::SekretarisRt, $this->orgA, $this->rwA1, $this->rtA1_1);

        $this->ketuaRtA1_2 = $this->createScopedUser(RoleKey::KetuaRt, $this->orgA, $this->rwA1, $this->rtA1_2);
        $this->ketuaRwA2 = $this->createScopedUser(RoleKey::KetuaRw, $this->orgA, $this->rwA2);
        $this->orgAdminA = $this->createScopedUser(RoleKey::OrganizationAdmin, $this->orgA);
        $this->ketuaRtB1_1 = $this->createScopedUser(RoleKey::KetuaRt, $this->orgB, $this->rwB1, $this->rtB1_1);
        $this->regularUser = User::factory()->create();

        // 8. Policies
        $this->accountPolicy = app(CashAccountPolicy::class);
        $this->categoryPolicy = app(FinanceCategoryPolicy::class);
        $this->transactionPolicy = app(FinancialTransactionPolicy::class);
    }

    public function test_cash_account_view_scope_boundaries(): void
    {
        // 1. Same RT officers can view RT 01 account
        $this->assertTrue($this->accountPolicy->view($this->ketuaRtA1_1, $this->accountRtA1_1));
        $this->assertTrue($this->accountPolicy->view($this->bendaharaRtA1_1, $this->accountRtA1_1));
        $this->assertTrue($this->accountPolicy->view($this->sekretarisRtA1_1, $this->accountRtA1_1));

        // 2. Sibling RT officer CANNOT view RT 01 account
        $this->assertFalse($this->accountPolicy->view($this->ketuaRtA1_2, $this->accountRtA1_1));

        // 3. RT 01 officer CANNOT view RW account or other RT account
        $this->assertFalse($this->accountPolicy->view($this->ketuaRtA1_1, $this->accountRwA1));
        $this->assertFalse($this->accountPolicy->view($this->ketuaRtA1_1, $this->accountRtA1_2));

        // 4. Parent RW officers CAN view RW account AND child RT accounts
        $this->assertTrue($this->accountPolicy->view($this->ketuaRwA1, $this->accountRwA1));
        $this->assertTrue($this->accountPolicy->view($this->bendaharaRwA1, $this->accountRwA1));
        $this->assertTrue($this->accountPolicy->view($this->sekretarisRwA1, $this->accountRwA1));

        $this->assertTrue($this->accountPolicy->view($this->ketuaRwA1, $this->accountRtA1_1));
        $this->assertTrue($this->accountPolicy->view($this->bendaharaRwA1, $this->accountRtA1_1));
        $this->assertTrue($this->accountPolicy->view($this->sekretarisRwA1, $this->accountRtA1_1));

        // 5. Sibling RW officer CANNOT view RW 01 or RT 01 accounts
        $this->assertFalse($this->accountPolicy->view($this->ketuaRwA2, $this->accountRwA1));
        $this->assertFalse($this->accountPolicy->view($this->ketuaRwA2, $this->accountRtA1_1));

        // 6. Organization B officer CANNOT view Org A accounts
        $this->assertFalse($this->accountPolicy->view($this->ketuaRtB1_1, $this->accountRtA1_1));
        $this->assertFalse($this->accountPolicy->view($this->ketuaRtB1_1, $this->accountRwA1));

        // 7. Organization A admin CAN view all accounts in Org A
        $this->assertTrue($this->accountPolicy->view($this->orgAdminA, $this->accountRwA1));
        $this->assertTrue($this->accountPolicy->view($this->orgAdminA, $this->accountRtA1_1));
        $this->assertFalse($this->accountPolicy->view($this->orgAdminA, $this->accountRtB1_1)); // cannot view Org B

        // 8. Regular user has no view access
        $this->assertFalse($this->accountPolicy->view($this->regularUser, $this->accountRtA1_1));
    }

    public function test_cash_account_management_permissions_separation(): void
    {
        $rwScope = ScopeRef::rw($this->orgA->id, $this->rwA1->id);
        $rtScope = ScopeRef::rt($this->orgA->id, $this->rwA1->id, $this->rtA1_1->id);

        // Ketua RW & Bendahara RW: can manage RW and RT accounts
        $this->assertTrue($this->accountPolicy->create($this->ketuaRwA1, $rwScope));
        $this->assertTrue($this->accountPolicy->create($this->bendaharaRwA1, $rwScope));
        $this->assertTrue($this->accountPolicy->update($this->ketuaRwA1, $this->accountRwA1));
        $this->assertTrue($this->accountPolicy->close($this->bendaharaRwA1, $this->accountRwA1));

        // Sekretaris RW: read-only visibility, CANNOT manage
        $this->assertFalse($this->accountPolicy->create($this->sekretarisRwA1, $rwScope));
        $this->assertFalse($this->accountPolicy->update($this->sekretarisRwA1, $this->accountRwA1));
        $this->assertFalse($this->accountPolicy->close($this->sekretarisRwA1, $this->accountRwA1));

        // Ketua RT & Bendahara RT: can manage at RT scope
        $this->assertTrue($this->accountPolicy->create($this->ketuaRtA1_1, $rtScope));
        $this->assertTrue($this->accountPolicy->update($this->bendaharaRtA1_1, $this->accountRtA1_1));
        $this->assertTrue($this->accountPolicy->close($this->ketuaRtA1_1, $this->accountRtA1_1));

        // Ketua RT CANNOT manage at RW scope or other RT scope
        $this->assertFalse($this->accountPolicy->create($this->ketuaRtA1_1, $rwScope));
        $this->assertFalse($this->accountPolicy->update($this->ketuaRtA1_1, $this->accountRwA1));
        $this->assertFalse($this->accountPolicy->update($this->ketuaRtA1_1, $this->accountRtA1_2));

        // Sekretaris RT: CANNOT manage
        $this->assertFalse($this->accountPolicy->create($this->sekretarisRtA1_1, $rtScope));
        $this->assertFalse($this->accountPolicy->update($this->sekretarisRtA1_1, $this->accountRtA1_1));
    }

    public function test_financial_transaction_view_scope_boundaries(): void
    {
        // RT 01 officers can view RT 01 transaction
        $this->assertTrue($this->transactionPolicy->view($this->ketuaRtA1_1, $this->transactionRtA1_1));
        $this->assertTrue($this->transactionPolicy->view($this->bendaharaRtA1_1, $this->transactionRtA1_1));
        $this->assertTrue($this->transactionPolicy->view($this->sekretarisRtA1_1, $this->transactionRtA1_1));

        // RT 01 officers CANNOT view RW 01 transaction
        $this->assertFalse($this->transactionPolicy->view($this->ketuaRtA1_1, $this->transactionRwA1));

        // Sibling RT 02 officer CANNOT view RT 01 transaction
        $this->assertFalse($this->transactionPolicy->view($this->ketuaRtA1_2, $this->transactionRtA1_1));

        // Parent RW 01 officers CAN view RT 01 transaction AND RW 01 transaction
        $this->assertTrue($this->transactionPolicy->view($this->ketuaRwA1, $this->transactionRtA1_1));
        $this->assertTrue($this->transactionPolicy->view($this->bendaharaRwA1, $this->transactionRtA1_1));
        $this->assertTrue($this->transactionPolicy->view($this->sekretarisRwA1, $this->transactionRtA1_1));
        $this->assertTrue($this->transactionPolicy->view($this->ketuaRwA1, $this->transactionRwA1));

        // Sibling RW 02 officer CANNOT view RW 01 or RT 01 transactions
        $this->assertFalse($this->transactionPolicy->view($this->ketuaRwA2, $this->transactionRtA1_1));
        $this->assertFalse($this->transactionPolicy->view($this->ketuaRwA2, $this->transactionRwA1));
    }

    public function test_financial_transaction_record_permissions(): void
    {
        // Ketua RW & Bendahara RW: can record in RW and RT accounts
        $this->assertTrue($this->transactionPolicy->create($this->ketuaRwA1, $this->accountRwA1));
        $this->assertTrue($this->transactionPolicy->create($this->bendaharaRwA1, $this->accountRwA1));
        $this->assertTrue($this->transactionPolicy->create($this->ketuaRwA1, $this->accountRtA1_1));

        // Sekretaris RW: CANNOT record
        $this->assertFalse($this->transactionPolicy->create($this->sekretarisRwA1, $this->accountRwA1));

        // Ketua RT & Bendahara RT: can record in RT 01 account
        $this->assertTrue($this->transactionPolicy->create($this->ketuaRtA1_1, $this->accountRtA1_1));
        $this->assertTrue($this->transactionPolicy->create($this->bendaharaRtA1_1, $this->accountRtA1_1));

        // Ketua RT CANNOT record in RW account or other RT account
        $this->assertFalse($this->transactionPolicy->create($this->ketuaRtA1_1, $this->accountRwA1));
        $this->assertFalse($this->transactionPolicy->create($this->ketuaRtA1_1, $this->accountRtA1_2));

        // Sekretaris RT: CANNOT record
        $this->assertFalse($this->transactionPolicy->create($this->sekretarisRtA1_1, $this->accountRtA1_1));

        // Regular user: CANNOT record
        $this->assertFalse($this->transactionPolicy->create($this->regularUser, $this->accountRtA1_1));
    }

    public function test_financial_transaction_reverse_permissions(): void
    {
        // Ketua RW & Bendahara RW: can reverse in RW and RT
        $this->assertTrue($this->transactionPolicy->reverse($this->ketuaRwA1, $this->transactionRwA1));
        $this->assertTrue($this->transactionPolicy->reverse($this->bendaharaRwA1, $this->transactionRtA1_1));

        // Sekretaris RW: CANNOT reverse
        $this->assertFalse($this->transactionPolicy->reverse($this->sekretarisRwA1, $this->transactionRwA1));

        // Ketua RT & Bendahara RT: can reverse in their own RT
        $this->assertTrue($this->transactionPolicy->reverse($this->ketuaRtA1_1, $this->transactionRtA1_1));
        $this->assertTrue($this->transactionPolicy->reverse($this->bendaharaRtA1_1, $this->transactionRtA1_1));

        // Ketua RT CANNOT reverse in RW or sibling RT
        $this->assertFalse($this->transactionPolicy->reverse($this->ketuaRtA1_1, $this->transactionRwA1));

        // Sekretaris RT: CANNOT reverse
        $this->assertFalse($this->transactionPolicy->reverse($this->sekretarisRtA1_1, $this->transactionRtA1_1));

        // Regular user: CANNOT reverse
        $this->assertFalse($this->transactionPolicy->reverse($this->regularUser, $this->transactionRtA1_1));
    }
}
