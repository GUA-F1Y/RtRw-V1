<?php

declare(strict_types=1);

namespace App\Support\Api\Contracts;

use App\Domain\Authorization\Enums\PrivacyTier;

/**
 * Machine-readable list-query contract for API v1 (see docs/api/v1-contract.md §14-18).
 *
 * Pure allowlists and tiny pure helpers; no queries, no request access, no authorization.
 * Filters are query constraints ONLY: they run inside the already scope-constrained query
 * (ScopedQuery / AccessResolver::scopesGranting) and can never widen access. A client
 * passing another RT's id simply gets an empty list (or 403 on a single-resource route).
 *
 * Unknown/unsupported query parameters have no effect. In particular `sensitive`, `tier`,
 * `representation` and `fields` are never read: the response tier is chosen server-side
 * from the Policy decision, and there is no dynamic field selection in v1.
 */
final class QueryContract
{
    public const DEFAULT_PER_PAGE = 15;
    public const MIN_PER_PAGE = 1;
    public const MAX_PER_PAGE = 100;

    public const MIN_SEARCH_LENGTH = 3;
    public const MAX_SEARCH_LENGTH = 100;

    /** Deepest include chain supported (e.g. `members.resident` = 2). */
    public const MAX_INCLUDE_DEPTH = 2;

    /** Maximum number of include tokens in one request. */
    public const MAX_INCLUDES = 5;

    public const RESIDENT = 'resident';
    public const HOUSEHOLD = 'household';
    public const FINANCE_ACCOUNT = 'finance_account';
    public const FINANCE_CATEGORY = 'finance_category';
    public const FINANCE_TRANSACTION = 'finance_transaction';
    public const FINANCE_PERIOD_CLOSURE = 'finance_period_closure';
    public const FINANCE_TRANSFER = 'finance_transfer';
    public const FINANCE_BUDGET_PLAN = 'finance_budget_plan';
    public const FINANCE_EXPENDITURE_REQUEST = 'finance_expenditure_request';

    /** Names a client must never be able to use to influence privacy or scope. */
    public const NEVER_READ_PARAMETERS = ['sensitive', 'tier', 'representation', 'fields', 'organization_id'];

    /**
     * Allowed filter keys. NIK / phone / KK number are intentionally not filterable.
     *
     * @var array<string, list<string>>
     */
    private const FILTERS = [
        self::RESIDENT => ['status', 'rt_id', 'rw_id', 'household_id', 'gender', 'residency_type', 'marital_status', 'q'],
        self::HOUSEHOLD => ['status', 'rt_id', 'rw_id', 'head_availability', 'q'],
        self::FINANCE_ACCOUNT => ['status', 'rt_id', 'rw_id', 'q'],
        self::FINANCE_CATEGORY => ['type', 'is_active', 'q'],
        self::FINANCE_TRANSACTION => ['cash_account_id', 'category_id', 'type', 'is_reversal', 'date_from', 'date_to', 'rt_id', 'rw_id', 'q'],
        self::FINANCE_PERIOD_CLOSURE => ['cash_account_id', 'period_year', 'period_month', 'rt_id', 'rw_id'],
        self::FINANCE_TRANSFER => ['source_cash_account_id', 'destination_cash_account_id', 'cash_account_id', 'is_reversal', 'date_from', 'date_to', 'rt_id', 'rw_id', 'q'],
        self::FINANCE_BUDGET_PLAN => ['year', 'month', 'status', 'version', 'rt_id', 'rw_id', 'q'],
        self::FINANCE_EXPENDITURE_REQUEST => ['budget_line_id', 'cash_account_id', 'category_id', 'status', 'date_from', 'date_to', 'rt_id', 'rw_id', 'q'],
    ];

    /**
     * Fields a single `q` search may match (case-insensitive partial match).
     *
     * @var array<string, list<string>>
     */
    private const SEARCH_FIELDS = [
        self::RESIDENT => ['name'],
        self::HOUSEHOLD => ['address'],
        self::FINANCE_ACCOUNT => ['name', 'account_number'],
        self::FINANCE_CATEGORY => ['name', 'code'],
        self::FINANCE_TRANSACTION => ['description', 'reference_number'],
        self::FINANCE_PERIOD_CLOSURE => ['reconciliation_notes'],
        self::FINANCE_TRANSFER => ['description', 'reference_number'],
        self::FINANCE_BUDGET_PLAN => ['notes'],
        self::FINANCE_EXPENDITURE_REQUEST => ['title', 'description', 'reference_number'],
    ];

    /**
     * Whitelisted sortable fields. birth_date / nik / phone / kk_number are never sortable
     * (ordering would leak values the tier hides).
     *
     * @var array<string, list<string>>
     */
    private const SORTS = [
        self::RESIDENT => ['name', 'created_at', 'status'],
        self::HOUSEHOLD => ['address', 'created_at', 'status'],
        self::FINANCE_ACCOUNT => ['name', 'created_at', 'status'],
        self::FINANCE_CATEGORY => ['name', 'created_at', 'code'],
        self::FINANCE_TRANSACTION => ['transaction_date', 'created_at', 'amount'],
        self::FINANCE_PERIOD_CLOSURE => ['period_year', 'period_month', 'closed_at', 'created_at'],
        self::FINANCE_TRANSFER => ['transfer_date', 'created_at', 'amount'],
        self::FINANCE_BUDGET_PLAN => ['year', 'month', 'version', 'created_at', 'status'],
        self::FINANCE_EXPENDITURE_REQUEST => ['planned_date', 'created_at', 'proposed_amount', 'status'],
    ];

    /** @var array<string, string> */
    private const DEFAULT_SORT = [
        self::RESIDENT => 'name',
        self::HOUSEHOLD => '-created_at',
        self::FINANCE_ACCOUNT => 'name',
        self::FINANCE_CATEGORY => 'name',
        self::FINANCE_TRANSACTION => '-transaction_date',
        self::FINANCE_PERIOD_CLOSURE => '-closed_at',
        self::FINANCE_TRANSFER => '-transfer_date',
        self::FINANCE_BUDGET_PLAN => '-created_at',
        self::FINANCE_EXPENDITURE_REQUEST => '-created_at',
    ];

    /**
     * Allowed head_availability values for households.
     *
     * @var list<string>
     */
    public const HEAD_AVAILABILITY = ['present', 'vacant'];

    /**
     * @return list<string>
     */
    public static function filters(string $resource): array
    {
        return self::FILTERS[$resource] ?? [];
    }

    /**
     * @return list<string>
     */
    public static function searchFields(string $resource): array
    {
        return self::SEARCH_FIELDS[$resource] ?? [];
    }

    /**
     * @return list<string>
     */
    public static function sorts(string $resource): array
    {
        return self::SORTS[$resource] ?? [];
    }

    public static function defaultSort(string $resource): string
    {
        return self::DEFAULT_SORT[$resource] ?? '';
    }

    /**
     * Include token => the Eloquent relation the future query layer eager-loads and the
     * minimum PrivacyTier the caller must already hold to receive it. Clients send tokens
     * only; relation names are never accepted from the client.
     *
     * @return array<string, array{relation: string, min_tier: PrivacyTier}>
     */
    public static function includes(string $resource): array
    {
        return match ($resource) {
            self::RESIDENT => [
                'organization' => ['relation' => 'organization', 'min_tier' => PrivacyTier::Directory],
                'rw' => ['relation' => 'rw', 'min_tier' => PrivacyTier::Directory],
                'rt' => ['relation' => 'rt', 'min_tier' => PrivacyTier::Directory],
                'household' => ['relation' => 'currentHousehold', 'min_tier' => PrivacyTier::Normal],
                'memberships' => ['relation' => 'householdMemberships', 'min_tier' => PrivacyTier::Normal],
                'movements' => ['relation' => 'residencyMovements', 'min_tier' => PrivacyTier::Normal],
                'status_history' => ['relation' => 'statusHistory', 'min_tier' => PrivacyTier::Normal],
            ],
            self::HOUSEHOLD => [
                'organization' => ['relation' => 'organization', 'min_tier' => PrivacyTier::Normal],
                'rw' => ['relation' => 'rw', 'min_tier' => PrivacyTier::Normal],
                'rt' => ['relation' => 'rt', 'min_tier' => PrivacyTier::Normal],
                'head' => ['relation' => 'activeHead', 'min_tier' => PrivacyTier::Normal],
                'head.resident' => ['relation' => 'activeHead.resident', 'min_tier' => PrivacyTier::Normal],
                'members' => ['relation' => 'activeMembers', 'min_tier' => PrivacyTier::Normal],
                'members.resident' => ['relation' => 'activeMembers.resident', 'min_tier' => PrivacyTier::Normal],
            ],
            default => [],
        };
    }

    /**
     * Parse `?include=a,b` against the allowlist.
     *
     * @return list<string>|null the validated tokens, or null when anything is not allowed
     *                           (wildcards, unknown tokens, raw relation names, too deep, too many)
     */
    public static function parseIncludes(?string $raw, string $resource): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $allowed = self::includes($resource);
        $tokens = array_values(array_unique(array_map('trim', explode(',', $raw))));

        if (count($tokens) > self::MAX_INCLUDES) {
            return null;
        }

        foreach ($tokens as $token) {
            if ($token === '' || ! array_key_exists($token, $allowed)) {
                return null;
            }
            if (substr_count($token, '.') + 1 > self::MAX_INCLUDE_DEPTH) {
                return null;
            }
        }

        return $tokens;
    }

    /**
     * Parse `?sort=name` / `?sort=-created_at` (single field) against the whitelist.
     *
     * @return array{field: string, direction: 'asc'|'desc'}|null null when not whitelisted
     */
    public static function parseSort(?string $raw, string $resource): ?array
    {
        $raw = $raw === null || trim($raw) === '' ? self::defaultSort($resource) : trim($raw);

        $direction = 'asc';
        if (str_starts_with($raw, '-')) {
            $direction = 'desc';
            $raw = substr($raw, 1);
        }

        if (! in_array($raw, self::sorts($resource), true)) {
            return null;
        }

        return ['field' => $raw, 'direction' => $direction];
    }

    /**
     * Defence in depth for the paginator: never returns outside [MIN, MAX]. The HTTP
     * validation layer rejects out-of-range values with 422; this guarantees the data
     * layer can never be asked for `per_page=999999` even if validation is bypassed.
     */
    public static function resolvePerPage(mixed $requested): int
    {
        if ($requested === null || $requested === '') {
            return self::DEFAULT_PER_PAGE;
        }

        $value = filter_var($requested, FILTER_VALIDATE_INT);
        if ($value === false) {
            return self::DEFAULT_PER_PAGE;
        }

        return max(self::MIN_PER_PAGE, min(self::MAX_PER_PAGE, $value));
    }

    /**
     * Trim and collapse whitespace; null when empty.
     */
    public static function normalizeSearch(?string $q): ?string
    {
        if ($q === null) {
            return null;
        }

        $normalized = trim((string) preg_replace('/\s+/u', ' ', $q));

        return $normalized === '' ? null : $normalized;
    }

    public static function isValidSearch(?string $q): bool
    {
        $normalized = self::normalizeSearch($q);
        if ($normalized === null) {
            return false;
        }

        $length = mb_strlen($normalized);

        return $length >= self::MIN_SEARCH_LENGTH && $length <= self::MAX_SEARCH_LENGTH;
    }

    /**
     * Escape LIKE/ILIKE wildcards so user input is matched literally (`%` and `_` are data).
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
