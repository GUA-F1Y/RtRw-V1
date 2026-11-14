<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Finance\DTOs\CreateFinanceCategoryInput;
use App\Domain\Finance\Exceptions\FinanceScopeMismatch;
use App\Domain\Finance\Models\FinanceCategory;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Illuminate\Support\Facades\DB;

final class CreateFinanceCategoryAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CreateFinanceCategoryInput $input, ?User $actor = null): FinanceCategory
    {
        $organizationId = null;
        $rwId = null;
        $rtId = null;

        if ($input->rwId !== null) {
            /** @var Rw|null $rw */
            $rw = Rw::find($input->rwId);
            if ($rw === null) {
                throw new FinanceScopeMismatch("RW '{$input->rwId}' does not exist.");
            }
            $organizationId = $rw->organization_id;
            $rwId = $rw->id;

            if ($input->rtId !== null) {
                /** @var Rt|null $rt */
                $rt = Rt::find($input->rtId);
                if ($rt === null) {
                    throw new FinanceScopeMismatch("RT '{$input->rtId}' does not exist.");
                }
                if ($rt->rw_id !== $rw->id) {
                    throw new FinanceScopeMismatch("RT '{$input->rtId}' does not belong to RW '{$rw->id}'.");
                }
                $rtId = $rt->id;
            }
        } elseif ($actor !== null) {
            $membership = $actor->memberships()->whereNull('revoked_at')->first();
            $organizationId = $membership?->organization_id;
            if ($organizationId === null) {
                /** @var Organization|null $firstOrg */
                $firstOrg = Organization::first();
                $organizationId = $firstOrg?->id ?? throw new FinanceScopeMismatch('Organization scope could not be determined.');
            }
        } else {
            /** @var Organization|null $firstOrg */
            $firstOrg = Organization::first();
            $organizationId = $firstOrg?->id ?? throw new FinanceScopeMismatch('Organization scope could not be determined.');
        }

        return DB::transaction(function () use ($input, $organizationId, $rwId, $rtId, $actor): FinanceCategory {
            /** @var FinanceCategory $category */
            $category = FinanceCategory::create([
                'organization_id' => $organizationId,
                'rw_id' => $rwId,
                'rt_id' => $rtId,
                'name' => $input->name,
                'code' => $input->code,
                'type' => $input->type,
                'description' => $input->description,
                'is_active' => true,
            ]);

            $scope = match (true) {
                $rtId !== null && $rwId !== null => ScopeRef::rt($organizationId, $rwId, $rtId),
                $rwId !== null => ScopeRef::rw($organizationId, $rwId),
                default => ScopeRef::organization($organizationId),
            };

            $this->auditLogger->record(
                actor: $actor,
                event: 'finance.category.created',
                description: "Financial category created: {$category->name} ({$category->type->value})",
                subject: $category,
                scope: $scope,
                properties: [
                    'category_id' => $category->id,
                    'name' => $category->name,
                    'type' => $category->type->value,
                ]
            );

            return $category;
        });
    }
}
