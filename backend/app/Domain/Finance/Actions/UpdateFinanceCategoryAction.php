<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Finance\DTOs\UpdateFinanceCategoryInput;
use App\Domain\Finance\Models\FinanceCategory;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateFinanceCategoryAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(FinanceCategory $category, UpdateFinanceCategoryInput $input, ?User $actor = null): FinanceCategory
    {
        return DB::transaction(function () use ($category, $input, $actor): FinanceCategory {
            $changes = [];

            if ($input->name !== null && $input->name !== $category->name) {
                $changes['name'] = ['from' => $category->name, 'to' => $input->name];
                $category->name = $input->name;
            }

            if ($input->code !== null && $input->code !== $category->code) {
                $changes['code'] = ['from' => $category->code, 'to' => $input->code];
                $category->code = $input->code;
            }

            if ($input->description !== null && $input->description !== $category->description) {
                $changes['description'] = ['from' => $category->description, 'to' => $input->description];
                $category->description = $input->description;
            }

            if ($input->isActive !== null && $input->isActive !== $category->is_active) {
                $changes['is_active'] = ['from' => $category->is_active, 'to' => $input->isActive];
                $category->is_active = $input->isActive;
            }

            if ($changes !== []) {
                $category->save();

                $this->auditLogger->record(
                    actor: $actor,
                    event: 'finance.category.updated',
                    description: "Financial category updated: {$category->name}",
                    subject: $category,
                    scope: $category->scopeRef(),
                    properties: [
                        'category_id' => $category->id,
                        'changes' => $changes,
                    ]
                );
            }

            return $category;
        });
    }
}
