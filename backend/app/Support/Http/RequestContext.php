<?php

declare(strict_types=1);

namespace App\Support\Http;

/**
 * Per-request correlation data (scoped singleton: reset between requests/jobs).
 * The request id appears in logs, error responses, audit entries and the
 * X-Request-Id response header, so one failure can be traced end to end.
 */
final class RequestContext
{
    private ?string $requestId = null;

    public function setRequestId(string $requestId): void
    {
        $this->requestId = $requestId;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }
}
