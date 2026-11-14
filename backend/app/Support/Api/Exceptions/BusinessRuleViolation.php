<?php

declare(strict_types=1);

namespace App\Support\Api\Exceptions;

/** 422: well-formed input that violates a business rule. */
final class BusinessRuleViolation extends ApiException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(string $message, array $errors = [])
    {
        parent::__construct($message, 422, 'business_rule_violation', $errors);
    }
}
