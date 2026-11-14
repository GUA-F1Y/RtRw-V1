<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Finance\Actions\CloseCashAccountAction;
use App\Domain\Finance\Actions\CreateCashAccountAction;
use App\Domain\Finance\Actions\CreateFinanceCategoryAction;
use App\Domain\Finance\Actions\RecordFinancialTransactionAction;
use App\Domain\Finance\Actions\ReverseFinancialTransactionAction;
use App\Domain\Finance\Actions\UpdateCashAccountDetailsAction;
use App\Domain\Finance\DTOs\CloseCashAccountInput;
use App\Domain\Finance\DTOs\CreateCashAccountInput;
use App\Domain\Finance\DTOs\CreateFinanceCategoryInput;
use App\Domain\Finance\DTOs\RecordFinancialTransactionInput;
use App\Domain\Finance\DTOs\ReverseFinancialTransactionInput;
use App\Domain\Finance\DTOs\UpdateCashAccountInput;
use App\Domain\Finance\Enums\CashAccountStatus;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Exceptions\CashAccountClosedException;
use App\Domain\Finance\Exceptions\CategoryTypeMismatchException;
use App\Domain\Finance\Exceptions\FinanceScopeMismatch;
use App\Domain\Finance\Exceptions\InvalidTransactionException;
use App\Domain\Finance\Exceptions\TransactionAlreadyReversedException;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Finance\Models\FinanceCategory;
use App\Domain\Finance\Models\FinancialTransaction;
use App\Domain\Finance\Services\AccountBalanceService;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use App\Support\Api\Exceptions\ApiException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinanceDomainActionTest extends TestCase
{
    private Organization $org;
    private Rw $rw;
    private Rt $rt1;
    private Rt $rt2;
    private User $actor;
    private AccountBalanceService $balanceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create(['name' => 'Org RW 05']);
        $this->rw = Rw::factory()->create(['organization_id' => $this->org->id, 'number' => 5]);
        $this->rt1 = Rt::factory()->create(['rw_id' => $this->rw->id, 'number' => 1]);
        $this->rt2 = Rt::factory()->create(['rw_id' => $this->rw->id, 'number' => 2]);
        $this->actor = User::factory()->create();
        $this->balanceService = app(AccountBalanceService::class);
    }

    public function test_create_cash_account_with_valid_rw_scope(): void
    {
        $action = app(CreateCashAccountAction::class);
        $input = new CreateCashAccountInput(
            rwId: $this->rw->id,
            name: 'Kas Utama RW 05',
            rtId: null,
            accountNumber: 'ACC-RW05-01',
            description: 'Rekening kas operasional RW 05',
            openingBalance: 10000000
        );

        $account = $action->execute($input, $this->actor);

        $this->assertInstanceOf(CashAccount::class, $account);
        $this->assertEquals('Kas Utama RW 05', $account->name);
        $this->assertEquals($this->rw->id, $account->rw_id);
        $this->assertNull($account->rt_id);
        $this->assertTrue($account->isRwAccount());
        $this->assertFalse($account->isRtAccount());
        $this->assertEquals(CashAccountStatus::Active, $account->status);
        $this->assertEquals(10000000, $account->opening_balance);

        // Verify audit log
        $audit = AuditLog::where('event', 'finance.account.created')->first();
        $this->assertNotNull($audit);
        $this->assertEquals($this->actor->id, $audit->causer_id);
        $this->assertEquals($account->id, $audit->properties['account_id']);
    }

    public function test_create_cash_account_with_valid_rt_scope(): void
    {
        $action = app(CreateCashAccountAction::class);
        $input = new CreateCashAccountInput(
            rwId: $this->rw->id,
            name: 'Kas RT 01',
            rtId: $this->rt1->id,
            accountNumber: 'ACC-RT01-01',
            description: 'Kas RT 01 RW 05',
            openingBalance: 2500000
        );

        $account = $action->execute($input, $this->actor);

        $this->assertInstanceOf(CashAccount::class, $account);
        $this->assertEquals('Kas RT 01', $account->name);
        $this->assertEquals($this->rw->id, $account->rw_id);
        $this->assertEquals($this->rt1->id, $account->rt_id);
        $this->assertTrue($account->isRtAccount());
        $this->assertEquals(CashAccountStatus::Active, $account->status);
        $this->assertEquals(2500000, $account->opening_balance);
    }

    public function test_reject_inconsistent_rw_rt_combination(): void
    {
        $otherRw = Rw::factory()->create(['organization_id' => $this->org->id, 'number' => 99]);

        $action = app(CreateCashAccountAction::class);
        $input = new CreateCashAccountInput(
            rwId: $otherRw->id, // mismatch with $this->rt1 which belongs to $this->rw
            name: 'Kas Invalid Scope',
            rtId: $this->rt1->id,
            openingBalance: 0
        );

        $this->expectException(FinanceScopeMismatch::class);
        $action->execute($input, $this->actor);
    }

    public function test_reject_negative_opening_balance(): void
    {
        $action = app(CreateCashAccountAction::class);
        $input = new CreateCashAccountInput(
            rwId: $this->rw->id,
            name: 'Kas Minus',
            openingBalance: -50000
        );

        $this->expectException(ApiException::class);
        $action->execute($input, $this->actor);
    }

    public function test_update_cash_account_details(): void
    {
        $createAction = app(CreateCashAccountAction::class);
        $account = $createAction->execute(new CreateCashAccountInput(
            rwId: $this->rw->id,
            name: 'Kas Awal',
            description: 'Deskripsi awal',
            openingBalance: 1000000
        ), $this->actor);

        $updateAction = app(UpdateCashAccountDetailsAction::class);
        $updated = $updateAction->execute($account, new UpdateCashAccountInput(
            name: 'Kas Baru Diperbarui',
            accountNumber: 'NEW-NUM-01',
            description: 'Deskripsi baru'
        ), $this->actor);

        $this->assertEquals('Kas Baru Diperbarui', $updated->name);
        $this->assertEquals('NEW-NUM-01', $updated->account_number);
        $this->assertEquals('Deskripsi baru', $updated->description);
        $this->assertEquals(1000000, $updated->opening_balance); // opening balance preserved
    }

    public function test_close_cash_account_lifecycle(): void
    {
        $createAction = app(CreateCashAccountAction::class);
        $account = $createAction->execute(new CreateCashAccountInput(
            rwId: $this->rw->id,
            name: 'Kas Mau Ditutup',
            openingBalance: 500000
        ), $this->actor);

        $closeAction = app(CloseCashAccountAction::class);
        $closed = $closeAction->execute($account, new CloseCashAccountInput('Penggabungan rekening RT ke RW'), $this->actor);

        $this->assertEquals(CashAccountStatus::Closed, $closed->status);
        $this->assertNotNull($closed->closed_at);
        $this->assertEquals('Penggabungan rekening RT ke RW', $closed->closed_reason);

        // Cannot close twice
        $this->expectException(CashAccountClosedException::class);
        $closeAction->execute($closed, new CloseCashAccountInput('Tutup lagi'), $this->actor);
    }

    public function test_prevent_posting_to_closed_account(): void
    {
        $account = app(CreateCashAccountAction::class)->execute(new CreateCashAccountInput(
            rwId: $this->rw->id,
            name: 'Kas Tertutup',
            openingBalance: 0
        ), $this->actor);

        app(CloseCashAccountAction::class)->execute($account, new CloseCashAccountInput('Tutup'), $this->actor);

        $category = app(CreateFinanceCategoryAction::class)->execute(new CreateFinanceCategoryInput(
            name: 'Iuran',
            type: TransactionType::Income,
            rwId: $this->rw->id
        ), $this->actor);

        $recordAction = app(RecordFinancialTransactionAction::class);
        $input = new RecordFinancialTransactionInput(
            cashAccountId: $account->id,
            categoryId: $category->id,
            type: TransactionType::Income,
            amount: 100000,
            transactionDate: '2026-10-14',
            description: 'Iuran warga'
        );

        $this->expectException(CashAccountClosedException::class);
        $recordAction->execute($input, $this->actor);
    }

    public function test_record_income_and_expense_and_balance_calculation(): void
    {
        $account = app(CreateCashAccountAction::class)->execute(new CreateCashAccountInput(
            rwId: $this->rw->id,
            name: 'Kas Operasional',
            openingBalance: 2000000
        ), $this->actor);

        $catIncome = app(CreateFinanceCategoryAction::class)->execute(new CreateFinanceCategoryInput(
            name: 'Iuran Bulanan',
            type: TransactionType::Income,
            rwId: $this->rw->id
        ), $this->actor);

        $catExpense = app(CreateFinanceCategoryAction::class)->execute(new CreateFinanceCategoryInput(
            name: 'Honor Keamanan',
            type: TransactionType::Expense,
            rwId: $this->rw->id
        ), $this->actor);

        $recordAction = app(RecordFinancialTransactionAction::class);

        // 1. Record Income Rp 1,500,000
        $t1 = $recordAction->execute(new RecordFinancialTransactionInput(
            cashAccountId: $account->id,
            categoryId: $catIncome->id,
            type: TransactionType::Income,
            amount: 1500000,
            transactionDate: '2026-10-10',
            description: 'Penerimaan iuran RT 01'
        ), $this->actor);

        $this->assertEquals(TransactionType::Income, $t1->type);
        $this->assertEquals(1500000, $t1->amount);
        $this->assertFalse($t1->is_reversal);

        // Balance check: 2,000,000 + 1,500,000 = 3,500,000
        $b1 = $this->balanceService->calculate($account);
        $this->assertEquals(3500000, $b1['current_balance']);
        $this->assertEquals(1500000, $b1['posted_income']);
        $this->assertEquals(0, $b1['posted_expense']);

        // 2. Record Expense Rp 500,000
        $t2 = $recordAction->execute(new RecordFinancialTransactionInput(
            cashAccountId: $account->id,
            categoryId: $catExpense->id,
            type: TransactionType::Expense,
            amount: 500000,
            transactionDate: '2026-10-12',
            description: 'Pembayaran pos satpam'
        ), $this->actor);

        $this->assertEquals(TransactionType::Expense, $t2->type);
        $this->assertEquals(500000, $t2->amount);

        // Balance check: 3,500,000 - 500,000 = 3,000,000
        $b2 = $this->balanceService->calculate($account);
        $this->assertEquals(3000000, $b2['current_balance']);
        $this->assertEquals(1500000, $b2['posted_income']);
        $this->assertEquals(500000, $b2['posted_expense']);
        $this->assertEquals(1500000, $b2['net_income']);
        $this->assertEquals(500000, $b2['net_expense']);
    }

    public function test_reject_zero_or_negative_transaction_amount(): void
    {
        $account = app(CreateCashAccountAction::class)->execute(new CreateCashAccountInput(
            rwId: $this->rw->id,
            name: 'Kas Test',
            openingBalance: 100000
        ), $this->actor);

        $category = app(CreateFinanceCategoryAction::class)->execute(new CreateFinanceCategoryInput(
            name: 'Donasi',
            type: TransactionType::Income,
            rwId: $this->rw->id
        ), $this->actor);

        $recordAction = app(RecordFinancialTransactionAction::class);

        $this->expectException(InvalidTransactionException::class);
        $recordAction->execute(new RecordFinancialTransactionInput(
            cashAccountId: $account->id,
            categoryId: $category->id,
            type: TransactionType::Income,
            amount: 0,
            transactionDate: '2026-10-14',
            description: 'Nol rupiah'
        ), $this->actor);
    }

    public function test_reject_category_and_transaction_type_mismatch(): void
    {
        $account = app(CreateCashAccountAction::class)->execute(new CreateCashAccountInput(
            rwId: $this->rw->id,
            name: 'Kas Test Mismatch',
            openingBalance: 100000
        ), $this->actor);

        $expenseCat = app(CreateFinanceCategoryAction::class)->execute(new CreateFinanceCategoryInput(
            name: 'Pengeluaran Taman',
            type: TransactionType::Expense,
            rwId: $this->rw->id
        ), $this->actor);

        $recordAction = app(RecordFinancialTransactionAction::class);

        // Recording INCOME using EXPENSE category
        $this->expectException(CategoryTypeMismatchException::class);
        $recordAction->execute(new RecordFinancialTransactionInput(
            cashAccountId: $account->id,
            categoryId: $expenseCat->id,
            type: TransactionType::Income,
            amount: 100000,
            transactionDate: '2026-10-14',
            description: 'Salah jenis'
        ), $this->actor);
    }

    public function test_reverse_transaction_preserves_history_and_corrects_balance_once(): void
    {
        $account = app(CreateCashAccountAction::class)->execute(new CreateCashAccountInput(
            rwId: $this->rw->id,
            name: 'Kas Reversal',
            openingBalance: 1000000
        ), $this->actor);

        $catIncome = app(CreateFinanceCategoryAction::class)->execute(new CreateFinanceCategoryInput(
            name: 'Iuran',
            type: TransactionType::Income,
            rwId: $this->rw->id
        ), $this->actor);

        $catExpense = app(CreateFinanceCategoryAction::class)->execute(new CreateFinanceCategoryInput(
            name: 'Listrik',
            type: TransactionType::Expense,
            rwId: $this->rw->id
        ), $this->actor);

        $recordAction = app(RecordFinancialTransactionAction::class);
        $reverseAction = app(ReverseFinancialTransactionAction::class);

        // 1. Post Income Rp 400,000 -> Balance = 1,400,000
        $tIncome = $recordAction->execute(new RecordFinancialTransactionInput(
            cashAccountId: $account->id,
            categoryId: $catIncome->id,
            type: TransactionType::Income,
            amount: 400000,
            transactionDate: '2026-10-10',
            description: 'Iuran keliru input'
        ), $this->actor);

        $this->assertEquals(1400000, $this->balanceService->calculate($account)['current_balance']);

        // 2. Reverse the Income transaction
        $revIncome = $reverseAction->execute(new ReverseFinancialTransactionInput(
            transactionId: $tIncome->id,
            reversalReason: 'Salah jumlah warga, harusnya di kas RT bukan RW'
        ), $this->actor);

        $this->assertInstanceOf(FinancialTransaction::class, $revIncome);
        $this->assertTrue($revIncome->is_reversal);
        $this->assertEquals($tIncome->id, $revIncome->original_transaction_id);
        $this->assertEquals(400000, $revIncome->amount);
        $this->assertEquals(TransactionType::Income, $revIncome->type);

        // Balance after reversing income: 1,400,000 - 400,000 = 1,000,000 (returned to opening balance)
        $bAfterRevIncome = $this->balanceService->calculate($account);
        $this->assertEquals(1000000, $bAfterRevIncome['current_balance']);
        $this->assertEquals(400000, $bAfterRevIncome['posted_income']);
        $this->assertEquals(400000, $bAfterRevIncome['reversed_income']);
        $this->assertEquals(0, $bAfterRevIncome['net_income']);

        // 3. Post Expense Rp 300,000 -> Balance = 700,000
        $tExpense = $recordAction->execute(new RecordFinancialTransactionInput(
            cashAccountId: $account->id,
            categoryId: $catExpense->id,
            type: TransactionType::Expense,
            amount: 300000,
            transactionDate: '2026-10-11',
            description: 'Biaya listrik salah'
        ), $this->actor);

        $this->assertEquals(700000, $this->balanceService->calculate($account)['current_balance']);

        // 4. Reverse the Expense transaction -> Balance = 1,000,000
        $revExpense = $reverseAction->execute(new ReverseFinancialTransactionInput(
            transactionId: $tExpense->id,
            reversalReason: 'Dibatalkan oleh PLN, nota tidak valid'
        ), $this->actor);

        $this->assertTrue($revExpense->is_reversal);
        $this->assertEquals($tExpense->id, $revExpense->original_transaction_id);
        $this->assertEquals(TransactionType::Expense, $revExpense->type);

        $bAfterRevExpense = $this->balanceService->calculate($account);
        $this->assertEquals(1000000, $bAfterRevExpense['current_balance']);
        $this->assertEquals(300000, $bAfterRevExpense['posted_expense']);
        $this->assertEquals(300000, $bAfterRevExpense['reversed_expense']);
        $this->assertEquals(0, $bAfterRevExpense['net_expense']);

        // Original entries still exist in history (not deleted!)
        $this->assertDatabaseHas('financial_transactions', ['id' => $tIncome->id]);
        $this->assertDatabaseHas('financial_transactions', ['id' => $tExpense->id]);
    }

    public function test_reject_duplicate_reversal(): void
    {
        $account = app(CreateCashAccountAction::class)->execute(new CreateCashAccountInput(
            rwId: $this->rw->id,
            name: 'Kas Double Reversal Test',
            openingBalance: 100000
        ), $this->actor);

        $cat = app(CreateFinanceCategoryAction::class)->execute(new CreateFinanceCategoryInput(
            name: 'Kategori Test',
            type: TransactionType::Income,
            rwId: $this->rw->id
        ), $this->actor);

        $t = app(RecordFinancialTransactionAction::class)->execute(new RecordFinancialTransactionInput(
            cashAccountId: $account->id,
            categoryId: $cat->id,
            type: TransactionType::Income,
            amount: 50000,
            transactionDate: '2026-10-14',
            description: 'Transaksi sekali balik'
        ), $this->actor);

        $reverseAction = app(ReverseFinancialTransactionAction::class);
        $reverseAction->execute(new ReverseFinancialTransactionInput(
            transactionId: $t->id,
            reversalReason: 'Pembalikan pertama'
        ), $this->actor);

        // Attempting to reverse a second time must be rejected
        $this->expectException(TransactionAlreadyReversedException::class);
        $reverseAction->execute(new ReverseFinancialTransactionInput(
            transactionId: $t->id,
            reversalReason: 'Pembalikan kedua'
        ), $this->actor);
    }

    public function test_reject_reversing_a_reversal(): void
    {
        $account = app(CreateCashAccountAction::class)->execute(new CreateCashAccountInput(
            rwId: $this->rw->id,
            name: 'Kas Test',
            openingBalance: 100000
        ), $this->actor);

        $cat = app(CreateFinanceCategoryAction::class)->execute(new CreateFinanceCategoryInput(
            name: 'Kategori Test',
            type: TransactionType::Income,
            rwId: $this->rw->id
        ), $this->actor);

        $t = app(RecordFinancialTransactionAction::class)->execute(new RecordFinancialTransactionInput(
            cashAccountId: $account->id,
            categoryId: $cat->id,
            type: TransactionType::Income,
            amount: 50000,
            transactionDate: '2026-10-14',
            description: 'Transaksi'
        ), $this->actor);

        $reversal = app(ReverseFinancialTransactionAction::class)->execute(new ReverseFinancialTransactionInput(
            transactionId: $t->id,
            reversalReason: 'Pembalikan'
        ), $this->actor);

        // Attempting to reverse the reversal entry itself
        $this->expectException(InvalidTransactionException::class);
        app(ReverseFinancialTransactionAction::class)->execute(new ReverseFinancialTransactionInput(
            transactionId: $reversal->id,
            reversalReason: 'Mencoba membalikkan pembalikan'
        ), $this->actor);
    }

    public function test_financial_transaction_update_rejected_by_trigger(): void
    {
        $account = app(CreateCashAccountAction::class)->execute(new CreateCashAccountInput(
            rwId: $this->rw->id,
            name: 'Kas Immutability Update',
            openingBalance: 500000
        ), $this->actor);

        $cat = app(CreateFinanceCategoryAction::class)->execute(new CreateFinanceCategoryInput(
            name: 'Kategori Immutability Update',
            type: TransactionType::Income,
            rwId: $this->rw->id
        ), $this->actor);

        $trx = app(RecordFinancialTransactionAction::class)->execute(new RecordFinancialTransactionInput(
            cashAccountId: $account->id,
            categoryId: $cat->id,
            type: TransactionType::Income,
            amount: 100000,
            transactionDate: '2026-10-14',
            description: 'Uang masuk tidak boleh diubah'
        ), $this->actor);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('immutable append-only');

        DB::table('financial_transactions')->where('id', $trx->id)->update(['amount' => 999999]);
    }

    public function test_financial_transaction_delete_rejected_by_trigger(): void
    {
        $account = app(CreateCashAccountAction::class)->execute(new CreateCashAccountInput(
            rwId: $this->rw->id,
            name: 'Kas Immutability Delete',
            openingBalance: 500000
        ), $this->actor);

        $cat = app(CreateFinanceCategoryAction::class)->execute(new CreateFinanceCategoryInput(
            name: 'Kategori Immutability Delete',
            type: TransactionType::Income,
            rwId: $this->rw->id
        ), $this->actor);

        $trx = app(RecordFinancialTransactionAction::class)->execute(new RecordFinancialTransactionInput(
            cashAccountId: $account->id,
            categoryId: $cat->id,
            type: TransactionType::Income,
            amount: 100000,
            transactionDate: '2026-10-14',
            description: 'Uang masuk tidak boleh dihapus'
        ), $this->actor);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('immutable append-only');

        DB::table('financial_transactions')->where('id', $trx->id)->delete();
    }
}
