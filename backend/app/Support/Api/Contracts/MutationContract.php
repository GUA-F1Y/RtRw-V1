<?php

declare(strict_types=1);

namespace App\Support\Api\Contracts;

use App\Domain\Resident\Enums\ResidencyMovementType;

/**
 * Machine-readable mutation payload contract for API v1 (see docs/api/v1-contract.md §19).
 *
 * This is DATA, not a validator and not a FormRequest. It records, per operation, the
 * only top-level fields a client may send and the fields that are explicitly refused
 * (with the reason). Future FormRequests must derive their allowlists from here so the
 * trust boundary has a single source of truth.
 *
 * Trust rules encoded here:
 *  - Source state (from_* scope, current RT/RW/organization, current status) always comes
 *    from the database, never from the client.
 *  - Client sends intent/target fields only; Domain Actions decide legality of transitions.
 *  - No operation accepts arbitrary status, is_active, head flags, user links or ledger rows.
 */
final class MutationContract
{
    public const RESIDENT_REGISTER = 'resident.register';
    public const RESIDENT_UPDATE = 'resident.update';
    public const RESIDENT_VERIFY = 'resident.verify';
    public const RESIDENT_STATUS = 'resident.status';
    public const RESIDENT_RELOCATE = 'resident.relocate';
    public const RESIDENT_REMOVE_FROM_HOUSEHOLD = 'resident.remove_from_household';

    public const HOUSEHOLD_CREATE = 'household.create';
    public const HOUSEHOLD_UPDATE = 'household.update';
    public const HOUSEHOLD_ADD_MEMBER = 'household.add_member';
    public const HOUSEHOLD_REMOVE_MEMBER = 'household.remove_member';
    public const HOUSEHOLD_CHANGE_HEAD = 'household.change_head';
    public const HOUSEHOLD_MOVE = 'household.move';
    public const HOUSEHOLD_DISSOLVE = 'household.dissolve';

    public const FINANCE_ACCOUNT_CREATE = 'finance.account.create';
    public const FINANCE_ACCOUNT_UPDATE = 'finance.account.update';
    public const FINANCE_ACCOUNT_CLOSE = 'finance.account.close';
    public const FINANCE_CATEGORY_CREATE = 'finance.category.create';
    public const FINANCE_CATEGORY_UPDATE = 'finance.category.update';
    public const FINANCE_TRANSACTION_RECORD = 'finance.transaction.record';
    public const FINANCE_TRANSACTION_REVERSE = 'finance.transaction.reverse';
    public const FINANCE_TRANSACTION_ATTACH_EVIDENCE = 'finance.transaction.attach_evidence';
    public const FINANCE_PERIOD_CLOSE = 'finance.period.close';
    public const FINANCE_TRANSFER_RECORD = 'finance.transfer.record';
    public const FINANCE_TRANSFER_REVERSE = 'finance.transfer.reverse';

    public const FINANCE_BUDGET_CREATE = 'finance.budget.create';
    public const FINANCE_BUDGET_UPDATE = 'finance.budget.update';
    public const FINANCE_BUDGET_PUBLISH = 'finance.budget.publish';
    public const FINANCE_BUDGET_AMEND = 'finance.budget.amend';
    public const FINANCE_EXPENDITURE_CREATE = 'finance.expenditure.create';
    public const FINANCE_EXPENDITURE_SUBMIT = 'finance.expenditure.submit';
    public const FINANCE_EXPENDITURE_APPROVE = 'finance.expenditure.approve';
    public const FINANCE_EXPENDITURE_REJECT = 'finance.expenditure.reject';
    public const FINANCE_EXPENDITURE_CANCEL = 'finance.expenditure.cancel';
    public const FINANCE_EXPENDITURE_POST = 'finance.expenditure.post';

    /** Reasons reused across operations. */
    private const R_SCOPE = 'Scope is derived from the authoritative RT/household in the database.';
    private const R_STATUS = 'Status changes are performed by dedicated Domain Actions, never by client-supplied state.';
    private const R_SOURCE = 'Source state is always read from the database; the client sends target/intent only.';
    private const R_IDENTITY = 'Civil identity is immutable here; a correction requires a dedicated future command.';

    /**
     * @var array<string, list<string>>
     */
    private const ACCEPTED = [
        self::RESIDENT_REGISTER => [
            'nik', 'name', 'gender', 'birth_place', 'birth_date', 'religion', 'marital_status',
            'rt_id', 'blood_type', 'occupation', 'education', 'phone', 'residency_type',
        ],
        self::RESIDENT_UPDATE => ['occupation', 'education', 'phone', 'marital_status'],
        self::RESIDENT_VERIFY => ['notes', 'reference_letter_number', 'attachment_file_id'],
        self::RESIDENT_STATUS => ['intent', 'reason', 'reference_letter_number', 'attachment_file_id', 'effective_at'],
        self::RESIDENT_RELOCATE => [
            'movement_type', 'target_rt_id', 'target_household_id', 'effective_date',
            'reference_letter_number', 'attachment_file_id', 'notes',
        ],
        self::RESIDENT_REMOVE_FROM_HOUSEHOLD => ['left_reason', 'left_at'],

        self::HOUSEHOLD_CREATE => ['kk_number', 'address', 'rt_id', 'notes', 'initial_head_resident_id'],
        self::HOUSEHOLD_UPDATE => ['address', 'notes'],
        self::HOUSEHOLD_ADD_MEMBER => ['resident_id', 'relationship', 'joined_at'],
        self::HOUSEHOLD_REMOVE_MEMBER => ['left_reason', 'left_at'],
        self::HOUSEHOLD_CHANGE_HEAD => ['new_head_resident_id', 'old_head_new_relationship'],
        self::HOUSEHOLD_MOVE => ['move_type', 'target_rt_id', 'new_address', 'notes'],
        self::HOUSEHOLD_DISSOLVE => ['reason'],

        self::FINANCE_ACCOUNT_CREATE => ['name', 'rt_id', 'account_number', 'description', 'opening_balance'],
        self::FINANCE_ACCOUNT_UPDATE => ['name', 'account_number', 'description'],
        self::FINANCE_ACCOUNT_CLOSE => ['reason'],
        self::FINANCE_CATEGORY_CREATE => ['name', 'type', 'rt_id', 'code', 'description'],
        self::FINANCE_CATEGORY_UPDATE => ['name', 'code', 'description'],
        self::FINANCE_TRANSACTION_RECORD => [
            'cash_account_id', 'category_id', 'type', 'amount', 'transaction_date',
            'description', 'reference_number', 'evidence_file_id',
        ],
        self::FINANCE_TRANSACTION_REVERSE => ['reversal_reason', 'transaction_date'],
        self::FINANCE_TRANSACTION_ATTACH_EVIDENCE => ['file_id', 'file'],
        self::FINANCE_PERIOD_CLOSE => ['counted_cash_balance', 'reconciliation_notes'],
        self::FINANCE_TRANSFER_RECORD => [
            'source_cash_account_id', 'destination_cash_account_id', 'amount', 'transfer_date',
            'description', 'reference_number', 'evidence_file_id',
        ],
        self::FINANCE_TRANSFER_REVERSE => ['reversal_reason', 'transfer_date'],

        self::FINANCE_BUDGET_CREATE => ['year', 'month', 'rt_id', 'notes', 'lines'],
        self::FINANCE_BUDGET_UPDATE => ['notes', 'lines'],
        self::FINANCE_BUDGET_PUBLISH => ['confirmation'],
        self::FINANCE_BUDGET_AMEND => ['notes'],
        self::FINANCE_EXPENDITURE_CREATE => [
            'budget_line_id', 'cash_account_id', 'proposed_amount', 'planned_date',
            'title', 'description', 'reference_number', 'evidence_file_id', 'submit',
        ],
        self::FINANCE_EXPENDITURE_SUBMIT => [],
        self::FINANCE_EXPENDITURE_APPROVE => ['approved_amount', 'decision_reason'],
        self::FINANCE_EXPENDITURE_REJECT => ['decision_reason'],
        self::FINANCE_EXPENDITURE_CANCEL => ['cancellation_reason'],
        self::FINANCE_EXPENDITURE_POST => [
            'actual_amount', 'transaction_date', 'cash_account_id', 'reference_number', 'evidence_file_id',
        ],
    ];

    /**
     * Explicitly refused fields and why. Anything not accepted is refused; this list names
     * the dangerous ones so they are documented and covered by tests.
     *
     * @var array<string, array<string, string>>
     */
    private const REJECTED = [
        self::RESIDENT_REGISTER => [
            'organization_id' => self::R_SCOPE,
            'rw_id' => self::R_SCOPE,
            'user_id' => 'User linking is a separate future command with its own authorization (account-link abuse).',
            'status' => 'Registration always results in PROSPECTIVE; verification is a separate gate.',
            'current_household_id' => 'Household membership is created only by household actions.',
            'id' => 'Server-generated.',
        ],
        self::RESIDENT_UPDATE => [
            'status' => self::R_STATUS,
            'nik' => self::R_IDENTITY,
            'name' => self::R_IDENTITY,
            'gender' => self::R_IDENTITY,
            'birth_place' => self::R_IDENTITY,
            'birth_date' => self::R_IDENTITY,
            'current_household_id' => 'Household membership changes use household actions.',
            'organization_id' => self::R_SCOPE,
            'rw_id' => self::R_SCOPE,
            'rt_id' => 'Moving between RTs is a relocation, not a profile update.',
            'user_id' => 'User linking is a separate future command.',
            'relationship' => 'Membership/head state is owned by household actions.',
        ],
        self::RESIDENT_VERIFY => [
            'status' => self::R_STATUS,
            'new_status' => self::R_STATUS,
            'target_status' => self::R_STATUS,
        ],
        self::RESIDENT_STATUS => [
            'status' => self::R_STATUS,
            'new_status' => self::R_STATUS,
            'previous_status' => self::R_SOURCE,
            'target_status' => self::R_STATUS,
        ],
        self::RESIDENT_RELOCATE => [
            'from_organization_id' => self::R_SOURCE,
            'from_rw_id' => self::R_SOURCE,
            'from_rt_id' => self::R_SOURCE,
            'from_household_id' => self::R_SOURCE,
            'to_organization_id' => 'Destination organization is derived from the target RT.',
            'to_rw_id' => 'Destination RW is derived from the target RT.',
            'recorded_by' => 'Recorder is the authenticated actor.',
            'resident_id' => 'The resident is identified by the URL path only.',
        ],
        self::RESIDENT_REMOVE_FROM_HOUSEHOLD => [
            'is_active' => 'Membership state is owned by the action.',
            'household_id' => 'The household is derived from the active membership.',
        ],

        self::HOUSEHOLD_CREATE => [
            'organization_id' => self::R_SCOPE,
            'rw_id' => self::R_SCOPE,
            'status' => 'Initial status is derived (ACTIVE with an initial head, otherwise HEAD_VACANT).',
        ],
        self::HOUSEHOLD_UPDATE => [
            'kk_number' => self::R_IDENTITY,
            'status' => self::R_STATUS,
            'organization_id' => self::R_SCOPE,
            'rw_id' => self::R_SCOPE,
            'rt_id' => 'Moving a household between RTs is POST /households/{household}/move.',
            'is_external_move' => 'PATCH must never perform relocation.',
        ],
        self::HOUSEHOLD_ADD_MEMBER => [
            'is_active' => 'Membership state is owned by the action.',
            'is_head' => 'Head is expressed only through relationship and validated by the domain.',
            'left_at' => 'A new membership cannot be created already ended.',
            'left_reason' => 'A new membership cannot be created already ended.',
            'household_id' => 'The household is identified by the URL path only.',
        ],
        self::HOUSEHOLD_REMOVE_MEMBER => [
            'is_active' => 'Membership state is owned by the action.',
            'resident_id' => 'The resident is identified by the URL path only.',
        ],
        self::HOUSEHOLD_CHANGE_HEAD => [
            'is_head' => 'Head is expressed only through the action.',
            'is_active' => 'Membership state is owned by the action.',
            'relationship' => 'The new head always becomes KEPALA_KELUARGA; only the demoted head relationship is chosen.',
        ],
        self::HOUSEHOLD_MOVE => [
            'from_rt_id' => self::R_SOURCE,
            'from_rw_id' => self::R_SOURCE,
            'from_organization_id' => self::R_SOURCE,
            'is_external_move' => 'EXTERNAL household moves are DEFERRED until destination semantics exist.',
            'effective_date' => 'Not supported by MoveHouseholdAction (always now); requires a future domain capability.',
            'status' => self::R_STATUS,
        ],
        self::HOUSEHOLD_DISSOLVE => [
            'status' => self::R_STATUS,
        ],

        self::FINANCE_ACCOUNT_CREATE => [
            'organization_id' => self::R_SCOPE,
            'rw_id' => self::R_SCOPE,
            'status' => self::R_STATUS,
            'id' => 'Server-generated.',
        ],
        self::FINANCE_ACCOUNT_UPDATE => [
            'organization_id' => self::R_SCOPE,
            'rw_id' => self::R_SCOPE,
            'rt_id' => self::R_SCOPE,
            'status' => self::R_STATUS,
            'opening_balance' => 'Opening balance is immutable after creation.',
        ],
        self::FINANCE_ACCOUNT_CLOSE => [
            'status' => self::R_STATUS,
        ],
        self::FINANCE_CATEGORY_CREATE => [
            'organization_id' => self::R_SCOPE,
            'rw_id' => self::R_SCOPE,
            'is_active' => 'Categories start active upon creation.',
        ],
        self::FINANCE_CATEGORY_UPDATE => [
            'organization_id' => self::R_SCOPE,
            'rw_id' => self::R_SCOPE,
            'rt_id' => self::R_SCOPE,
            'is_active' => 'Category status is managed by dedicated actions, not arbitrary PATCH.',
            'type' => 'Category direction type (INCOME/EXPENSE) is immutable.',
        ],
        self::FINANCE_TRANSACTION_RECORD => [
            'organization_id' => self::R_SCOPE,
            'rw_id' => self::R_SCOPE,
            'rt_id' => self::R_SCOPE,
            'recorded_by' => 'Derived from authenticated user.',
            'is_reversal' => 'Reversals use dedicated reversal endpoint.',
            'original_transaction_id' => 'Reversals use dedicated reversal endpoint.',
        ],
        self::FINANCE_TRANSACTION_REVERSE => [
            'organization_id' => self::R_SCOPE,
            'rw_id' => self::R_SCOPE,
            'rt_id' => self::R_SCOPE,
            'amount' => 'Reversal amount is derived from original transaction.',
            'type' => 'Reversal type is derived from original transaction.',
            'recorded_by' => 'Derived from authenticated user.',
        ],
        self::FINANCE_TRANSACTION_ATTACH_EVIDENCE => [
            'organization_id' => self::R_SCOPE,
            'rw_id' => self::R_SCOPE,
            'rt_id' => self::R_SCOPE,
            'amount' => 'Financial transactions are immutable.',
            'type' => 'Financial transactions are immutable.',
            'recorded_by' => 'Derived from authenticated user.',
        ],
        self::FINANCE_PERIOD_CLOSE => [
            'organization_id' => self::R_SCOPE,
            'rw_id' => self::R_SCOPE,
            'rt_id' => self::R_SCOPE,
            'opening_balance' => 'Opening balance is derived from authoritative ledger.',
            'closing_balance' => 'Closing balance is derived from authoritative ledger.',
            'total_income' => 'Income totals are derived from authoritative ledger.',
            'total_expense' => 'Expense totals are derived from authoritative ledger.',
            'net_movement' => 'Net movement is derived from authoritative ledger.',
            'transaction_count' => 'Transaction counts are derived from authoritative ledger.',
            'reversal_count' => 'Reversal counts are derived from authoritative ledger.',
            'closed_by' => 'Derived from authenticated user.',
            'closed_at' => 'Server-generated.',
        ],
        self::FINANCE_BUDGET_CREATE => [
            'organization_id' => self::R_SCOPE,
            'rw_id' => self::R_SCOPE,
            'status' => self::R_STATUS,
            'version' => 'Version starts at 1 and increments automatically upon amendment.',
            'created_by' => 'Derived from authenticated user.',
            'published_by' => 'Set upon publication.',
            'published_at' => 'Set upon publication.',
            'id' => 'Server-generated.',
        ],
        self::FINANCE_BUDGET_UPDATE => [
            'organization_id' => self::R_SCOPE,
            'rw_id' => self::R_SCOPE,
            'rt_id' => self::R_SCOPE,
            'year' => 'Budget plan period is immutable; create a new plan for a different period.',
            'month' => 'Budget plan period is immutable; create a new plan for a different period.',
            'status' => self::R_STATUS,
            'version' => 'Version is immutable during draft update.',
        ],
        self::FINANCE_BUDGET_PUBLISH => [
            'status' => self::R_STATUS,
            'published_by' => 'Derived from authenticated user.',
            'published_at' => 'Server-generated.',
        ],
        self::FINANCE_BUDGET_AMEND => [
            'version' => 'Version increments automatically.',
            'status' => self::R_STATUS,
        ],
        self::FINANCE_EXPENDITURE_CREATE => [
            'organization_id' => self::R_SCOPE,
            'rw_id' => self::R_SCOPE,
            'rt_id' => self::R_SCOPE,
            'status' => self::R_STATUS,
            'category_id' => 'Category is derived from the referenced budget line.',
            'approved_amount' => 'Approved amount is determined during the approval action.',
            'actual_amount' => 'Actual amount is recorded during posting.',
            'approved_by' => 'Approver is the authenticated actor during approval.',
            'decision_at' => 'Server-generated during approval.',
            'financial_transaction_id' => 'Transaction reference is created when posted to ledger.',
            'requested_by' => 'Requester is derived from authenticated user.',
            'id' => 'Server-generated.',
        ],
        self::FINANCE_EXPENDITURE_SUBMIT => [
            'status' => self::R_STATUS,
        ],
        self::FINANCE_EXPENDITURE_APPROVE => [
            'status' => self::R_STATUS,
            'approved_by' => 'Derived from authenticated user.',
            'decision_at' => 'Server-generated.',
        ],
        self::FINANCE_EXPENDITURE_REJECT => [
            'status' => self::R_STATUS,
            'approved_by' => 'Derived from authenticated user.',
            'decision_at' => 'Server-generated.',
        ],
        self::FINANCE_EXPENDITURE_CANCEL => [
            'status' => self::R_STATUS,
        ],
        self::FINANCE_EXPENDITURE_POST => [
            'organization_id' => self::R_SCOPE,
            'rw_id' => self::R_SCOPE,
            'rt_id' => self::R_SCOPE,
            'status' => self::R_STATUS,
            'financial_transaction_id' => 'Server-generated ledger transaction.',
            'posted_by' => 'Derived from authenticated user.',
            'posted_at' => 'Server-generated.',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function operations(): array
    {
        return array_keys(self::ACCEPTED);
    }

    /**
     * @return list<string>
     */
    public static function accepted(string $operation): array
    {
        return self::ACCEPTED[$operation] ?? [];
    }

    /**
     * @return array<string, string> field => reason it is refused
     */
    public static function rejected(string $operation): array
    {
        return self::REJECTED[$operation] ?? [];
    }

    /**
     * Top-level keys present in the payload that this operation does not accept.
     * A future FormRequest must treat any non-empty result as a validation failure (422).
     *
     * @param  array<array-key, mixed>  $payload
     * @return list<string>
     */
    public static function unexpectedFields(string $operation, array $payload): array
    {
        $accepted = self::accepted($operation);

        return array_values(array_map(
            'strval',
            array_filter(array_keys($payload), static fn ($key): bool => ! in_array((string) $key, $accepted, true))
        ));
    }

    /**
     * Enumerated values a client may send for a field, narrower than the domain enum
     * where an intent is exposed instead of raw state. Null = no enum restriction.
     *
     * @return list<string>|null
     */
    public static function allowedValues(string $operation, string $field): ?array
    {
        return match ([$operation, $field]) {
            [self::RESIDENT_RELOCATE, 'movement_type'] => array_values(array_diff(
                array_map(static fn (ResidencyMovementType $t): string => $t->value, ResidencyMovementType::cases()),
                self::deferredMovementTypes()
            )),
            // Intent, not state. MOVED is deliberately absent: it must go through relocation
            // (MOVE_OUT_EXTERNAL) so the movement ledger is written. PROSPECTIVE -> ACTIVE is
            // only available through verify.
            [self::RESIDENT_STATUS, 'intent'] => ['DEACTIVATE', 'REACTIVATE', 'MARK_DECEASED'],
            [self::HOUSEHOLD_MOVE, 'move_type'] => ['INTER_RT'],
            [self::FINANCE_CATEGORY_CREATE, 'type'] => ['INCOME', 'EXPENSE'],
            [self::FINANCE_TRANSACTION_RECORD, 'type'] => ['INCOME', 'EXPENSE'],
            default => null,
        };
    }

    /**
     * Domain capabilities intentionally NOT exposed in API v1.
     *
     * @return list<string>
     */
    public static function deferredMovementTypes(): array
    {
        return [ResidencyMovementType::MoveInExternal->value];
    }

    /**
     * Idempotency policy per operation: true = Idempotency-Key header is mandatory
     * (route uses `idempotent:required`), false = optional but recommended (`idempotent`).
     */
    public static function requiresIdempotencyKey(string $operation): bool
    {
        return in_array($operation, [
            self::RESIDENT_REGISTER,
            self::RESIDENT_RELOCATE,
            self::HOUSEHOLD_CREATE,
            self::HOUSEHOLD_ADD_MEMBER,
            self::HOUSEHOLD_MOVE,
            self::FINANCE_ACCOUNT_CREATE,
            self::FINANCE_TRANSACTION_RECORD,
            self::FINANCE_TRANSACTION_REVERSE,
            self::FINANCE_PERIOD_CLOSE,
            self::FINANCE_TRANSFER_RECORD,
            self::FINANCE_TRANSFER_REVERSE,
            self::FINANCE_BUDGET_PUBLISH,
            self::FINANCE_EXPENDITURE_CREATE,
            self::FINANCE_EXPENDITURE_APPROVE,
            self::FINANCE_EXPENDITURE_REJECT,
            self::FINANCE_EXPENDITURE_POST,
        ], true);
    }
}
