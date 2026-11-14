<?php

declare(strict_types=1);

namespace App\Support\Http\Middleware;

use App\Domain\Identity\Models\User;
use App\Support\Api\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects authenticated requests of accounts that are not active. Without this a
 * suspended user's existing session/token would stay usable until it expires.
 */
final class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->isActive()) {
            throw new ApiException('This account is not active.', 403, 'account_inactive');
        }

        return $next($request);
    }
}
