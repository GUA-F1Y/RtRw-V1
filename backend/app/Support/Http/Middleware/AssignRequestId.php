<?php

declare(strict_types=1);

namespace App\Support\Http\Middleware;

use App\Domain\Authorization\Services\AccessResolver;
use App\Support\Http\RequestContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assigns a correlation id to every request. A client-supplied X-Request-Id is
 * honoured only if it looks like a safe identifier (log-injection protection).
 */
final class AssignRequestId
{
    public function __construct(
        private readonly RequestContext $context,
        private readonly AccessResolver $access
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->access->flush();
        $incoming = (string) $request->header('X-Request-Id', '');
        $requestId = preg_match('/^[A-Za-z0-9\-_.]{8,64}$/', $incoming) === 1
            ? $incoming
            : (string) Str::uuid();

        $this->context->setRequestId($requestId);
        $request->attributes->set('request_id', $requestId);

        Context::add('request_id', $requestId);
        Log::shareContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
