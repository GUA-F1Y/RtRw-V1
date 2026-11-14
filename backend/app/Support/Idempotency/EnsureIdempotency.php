<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use App\Support\Api\Exceptions\ApiException;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Makes unsafe requests (POST) safe to retry.
 *
 * Usage:  ->middleware('idempotent')          key optional
 *         ->middleware('idempotent:required') key mandatory
 *
 * Behaviour for a request carrying `Idempotency-Key`:
 *  - first request claims the key (atomic INSERT ... ON CONFLICT DO NOTHING), runs, and
 *    on a 2xx JSON result stores status + body;
 *  - same key + same payload  -> stored response is replayed (Idempotent-Replayed: true);
 *  - same key + different payload -> 422 idempotency_key_reused;
 *  - same key while the first request is still running -> 409 (retry shortly);
 *  - failed requests (non-2xx / exception) release the key so the client can retry.
 *
 * Keys are scoped per authenticated user, never shared across users.
 */
final class EnsureIdempotency
{
    public function handle(Request $request, Closure $next, string $mode = 'optional'): Response
    {
        $header = (string) config('rtrw.idempotency.header');
        $key = $request->header($header);
        $user = $request->user();

        if ($key === null || $key === '') {
            if ($mode === 'required') {
                throw new ApiException("The {$header} header is required.", 400, 'idempotency_key_required');
            }

            return $next($request);
        }

        if ($user === null) {
            return $next($request);
        }

        if (preg_match('/^[A-Za-z0-9_\-:.]{8,128}$/', $key) !== 1) {
            throw new ApiException("The {$header} header is malformed.", 400, 'idempotency_key_invalid');
        }

        $hash = $this->fingerprint($request);
        $userId = (string) $user->getAuthIdentifier();

        $claimed = $this->claim($userId, $key, $hash, $request);

        if (! $claimed) {
            return $this->replayOrReject($userId, $key, $hash, $request, $next, $mode);
        }

        return $this->execute($request, $next, $userId, $key);
    }

    private function execute(Request $request, Closure $next, string $userId, string $key): Response
    {
        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->release($userId, $key);

            throw $e;
        }

        if ($response->isSuccessful() && $response instanceof JsonResponse) {
            IdempotencyKey::query()
                ->where('user_id', $userId)
                ->where('key', $key)
                ->toBase()
                ->update([
                    'response_status' => $response->getStatusCode(),
                    'response_body' => $response->getContent(),
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);

            return $response;
        }

        // Not a stored outcome (error, redirect, stream): let the client retry.
        $this->release($userId, $key);

        return $response;
    }

    private function replayOrReject(string $userId, string $key, string $hash, Request $request, Closure $next, string $mode): Response
    {
        $existing = IdempotencyKey::query()->where('user_id', $userId)->where('key', $key)->first();

        if ($existing === null) {
            // Released between our failed claim and this read: start over.
            return $this->handle($request, $next, $mode);
        }

        if ($existing->expires_at->isPast()) {
            $this->release($userId, $key);

            return $this->handle($request, $next, $mode);
        }

        if (! hash_equals($existing->request_hash, $hash)) {
            throw new ApiException(
                'This Idempotency-Key was already used with a different request.',
                422,
                'idempotency_key_reused',
            );
        }

        if ($existing->completed_at === null) {
            throw new ApiException(
                'A request with this Idempotency-Key is still being processed.',
                409,
                'idempotency_request_in_progress',
                [],
                ['Retry-After' => '1'],
            );
        }

        return JsonResponse::fromJsonString(
            (string) $existing->getRawOriginal('response_body'),
            $existing->response_status,
            ['Idempotent-Replayed' => 'true'],
        );
    }

    private function claim(string $userId, string $key, string $hash, Request $request): bool
    {
        $inserted = DB::table('idempotency_keys')->insertOrIgnore([
            'id' => (string) Str::uuid7(),
            'user_id' => $userId,
            'key' => $key,
            'request_method' => $request->method(),
            'request_path' => mb_substr($request->path(), 0, 255),
            'request_hash' => $hash,
            'expires_at' => now()->addHours((int) config('rtrw.idempotency.ttl_hours')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $inserted === 1;
    }

    private function release(string $userId, string $key): void
    {
        IdempotencyKey::query()
            ->where('user_id', $userId)
            ->where('key', $key)
            ->toBase()
            ->delete();
    }

    /**
     * Stable hash of "what the client asked for": method, path, input and the
     * content of uploaded files (multipart boundaries differ between retries).
     */
    private function fingerprint(Request $request): string
    {
        $input = $request->except(array_keys($request->allFiles()));
        $this->sortRecursive($input);

        $files = [];
        foreach ($request->allFiles() as $name => $file) {
            $files[$name] = $this->describeFile($file);
        }
        ksort($files);

        return hash('sha256', json_encode([
            $request->method(),
            $request->path(),
            $request->query->all(),
            $input,
            $files,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<mixed>  $value
     */
    private function sortRecursive(array &$value): void
    {
        ksort($value);

        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortRecursive($item);
            }
        }
    }

    private function describeFile(mixed $file): mixed
    {
        if ($file instanceof UploadedFile) {
            return $file->getRealPath() !== false ? hash_file('sha256', $file->getRealPath()) : null;
        }

        return is_array($file) ? array_map(fn ($f) => $this->describeFile($f), $file) : null;
    }
}
