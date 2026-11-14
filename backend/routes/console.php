<?php

declare(strict_types=1);

use App\Support\Idempotency\IdempotencyKey;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Periodic maintenance schedules for queues, tokens, and idempotency cleanup.
|
*/
Schedule::call(function (): void {
    IdempotencyKey::query()
        ->where('expires_at', '<', now())
        ->delete();
})->hourly()->name('idempotency:prune');

Schedule::command('sanctum:prune-expired --hours=168')->daily();
Schedule::command('queue:prune-failed --hours=72')->daily();
Schedule::command('queue:prune-batches --hours=72')->daily();
