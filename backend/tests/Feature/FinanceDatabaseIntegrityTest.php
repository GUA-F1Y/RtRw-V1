<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Finance\Enums\CashAccountStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\FinanceCategory;
use App\Domain\Finance\Models\FinancialTransaction;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rw;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinanceDatabaseIntegrityTest extends TestCase
{
    private Organization $org;
    private Rw $rw;
    private CashAccount $account;
    private FinanceCategory $category;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->rw = Rw::factory()->create(['organization_id' => $this->org->id]);
        $this->user = User::factory()->create();

        $this->account = CashAccount::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'name' => 'Kas Operasional RW',
            'opening_balance' => 1000000,
            'status' => CashAccountStatus::Active,
        ]);

        $this->category = FinanceCategory::query()->create([
            'organization_id' => $this->org->id,
            'name' => 'Iuran Warga',
            'type' => TransactionType::Income,
            'code' => 'INC-01',
            'is_active' => true,
        ]);
    }

    public function test_postgres_trigger_prevents_updating_financial_transactions(): void
    {
        $tx = FinancialTransaction::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'cash_account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => TransactionType::Income,
            'amount' => 150000,
            'transaction_date' => '2026-10-14',
            'description' => 'Original ledger entry',
            'recorded_by' => $this->user->id,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Financial transactions are immutable append-only ledger entries');

        $tx->update(['amount' => 999999]);
    }

    public function test_postgres_trigger_prevents_deleting_financial_transactions(): void
    {
        $tx = FinancialTransaction::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'cash_account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => TransactionType::Income,
            'amount' => 150000,
            'transaction_date' => '2026-10-14',
            'description' => 'Original ledger entry',
            'recorded_by' => $this->user->id,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Financial transactions are immutable append-only ledger entries');

        $tx->delete();
    }

    public function test_check_constraint_rejects_zero_or_negative_amount_at_database_level(): void
    {
        $this->expectException(QueryException::class);

        DB::table('financial_transactions')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'cash_account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'INCOME',
            'amount' => 0, // Violates check_financial_transactions_amount_positive
            'transaction_date' => '2026-10-14',
            'description' => 'Zero amount insert',
            'recorded_by' => $this->user->id,
            'is_reversal' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_check_constraint_rejects_negative_opening_balance_on_cash_accounts(): void
    {
        $this->expectException(QueryException::class);

        DB::table('cash_accounts')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'name' => 'Invalid Account',
            'opening_balance' => -100, // Violates check_cash_accounts_opening_balance_non_negative
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_unique_partial_index_prevents_duplicate_reversal_at_database_level(): void
    {
        $original = FinancialTransaction::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'cash_account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => TransactionType::Income,
            'amount' => 100000,
            'transaction_date' => '2026-10-14',
            'description' => 'Original transaction',
            'recorded_by' => $this->user->id,
        ]);

        // 1st reversal succeeds
        FinancialTransaction::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'cash_account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => TransactionType::Expense,
            'amount' => 100000,
            'transaction_date' => '2026-10-14',
            'description' => 'Reversal 1',
            'recorded_by' => $this->user->id,
            'is_reversal' => true,
            'original_transaction_id' => $original->id,
            'reversal_reason' => 'Alasan 1',
        ]);

        // 2nd reversal must violate unique_reversal_per_original_transaction
        $this->expectException(QueryException::class);

        FinancialTransaction::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'cash_account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => TransactionType::Expense,
            'amount' => 100000,
            'transaction_date' => '2026-10-14',
            'description' => 'Reversal 2',
            'recorded_by' => $this->user->id,
            'is_reversal' => true,
            'original_transaction_id' => $original->id,
            'reversal_reason' => 'Alasan 2',
        ]);
    }

    public function test_foreign_key_restricts_deletion_of_account_with_transactions(): void
    {
        FinancialTransaction::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'cash_account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => TransactionType::Income,
            'amount' => 50000,
            'transaction_date' => '2026-10-14',
            'description' => 'Transaction keeping account alive',
            'recorded_by' => $this->user->id,
        ]);

        $this->expectException(QueryException::class);
        // Deleting the cash account violates restrict foreign key constraint
        $this->account->delete();
    }

    public function test_foreign_key_restricts_deletion_of_category_with_transactions(): void
    {
        FinancialTransaction::query()->create([
            'organization_id' => $this->org->id,
            'rw_id' => $this->rw->id,
            'cash_account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => TransactionType::Income,
            'amount' => 50000,
            'transaction_date' => '2026-10-14',
            'description' => 'Transaction keeping category alive',
            'recorded_by' => $this->user->id,
        ]);

        $this->expectException(QueryException::class);
        // Deleting the finance category violates restrict foreign key constraint
        $this->category->delete();
    }
}
