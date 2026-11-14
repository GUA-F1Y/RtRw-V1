<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;

final class AuditLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        ?User $actor,
        string $event,
        string $description,
        ?Model $subject = null,
        ?ScopeRef $scope = null,
        array $properties = []
    ): AuditLog {
        $activity = activity()
            ->event($event)
            ->withProperties($properties);

        if ($actor !== null) {
            $activity->causedBy($actor);
        }

        if ($subject !== null) {
            $activity->performedOn($subject);
        }

        if ($scope !== null) {
            $activity->tap(function (AuditLog $log) use ($scope): void {
                $log->organization_id = $scope->organizationId;
                $log->rw_id = $scope->rwId;
                $log->rt_id = $scope->rtId;
            });
        }

        /** @var AuditLog $log */
        $log = $activity->log($description);

        return $log;
    }
}
