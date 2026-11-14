<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rt;
use App\Domain\Organization\Models\Rw;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class DatabaseIntegrityTest extends TestCase
{
    public function test_organization_deletion_is_restricted_when_rws_exist(): void
    {
        $organization = Organization::factory()->create();
        Rw::factory()->create(['organization_id' => $organization->id]);

        $this->expectException(QueryException::class);
        $organization->delete();
    }

    public function test_duplicate_rw_number_within_same_organization_is_rejected_by_database_constraint(): void
    {
        $organization = Organization::factory()->create();
        Rw::factory()->create(['organization_id' => $organization->id, 'number' => 1]);

        $this->expectException(QueryException::class);
        // Duplicate number 1 in same organization
        Rw::factory()->create(['organization_id' => $organization->id, 'number' => 1]);
    }

    public function test_duplicate_rt_number_within_same_rw_is_rejected_by_database_constraint(): void
    {
        $rw = Rw::factory()->create();
        Rt::factory()->create(['rw_id' => $rw->id, 'number' => 3]);

        $this->expectException(QueryException::class);
        // Duplicate number 3 in same RW
        Rt::factory()->create(['rw_id' => $rw->id, 'number' => 3]);
    }

    public function test_audit_log_trigger_prevents_updates_and_deletes(): void
    {
        /** @var AuditLog $log */
        $log = AuditLog::query()->create([
            'description' => 'Security sensitive event',
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('The audit log is append-only.');

        // Attempting to update the audit record triggers the database restriction
        $log->update(['description' => 'Tampered event']);
    }
}
