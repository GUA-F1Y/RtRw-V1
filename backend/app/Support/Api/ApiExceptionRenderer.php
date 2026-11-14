<?php

declare(strict_types=1);

namespace App\Support\Api;

use App\Support\Api\Exceptions\ApiException;
use App\Support\Http\RequestContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\ForeignKeyConstraintViolationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Converts any exception into the standard API error envelope:
 *
 *   { "error": { "code": "...", "message": "...", "errors": { field: [..] }, "request_id": "..." } }
 *
 * Internal details (SQL, stack traces, file paths) are never put in the response,
 * even when APP_DEBUG is on; they only go to the logs, tagged with the request id.
 */
final class ApiExceptionRenderer
{
    public function __construct(private readonly RequestContext $context) {}

    public function __invoke(Throwable $e, Request $request): JsonResponse
    {
        $errors = [];
        $headers = [];

        [$status, $code, $message] = match (true) {
            $e instanceof ApiException => [$e->status, $e->errorCode, $e->getMessage()],
            $e instanceof ValidationException => [422, 'validation_failed', 'The given data was invalid.'],
            $e instanceof AuthenticationException => [401, 'unauthenticated', 'Unauthenticated.'],
            $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => [403, 'forbidden', 'This action is unauthorized.'],
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => [404, 'not_found', 'Resource not found.'],
            $e instanceof TokenMismatchException => [419, 'csrf_token_mismatch', 'CSRF token mismatch.'],
            $e instanceof UniqueConstraintViolationException => [409, 'conflict', 'The request conflicts with an existing resource.'],
            $e instanceof ForeignKeyConstraintViolationException => [409, 'conflict', 'The request conflicts with related resources.'],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(),
                $e->getStatusCode() === 429 ? 'too_many_requests' : 'http_error',
                $this->statusText($e->getStatusCode(), $e->getMessage()),
            ],
            default => [500, 'server_error', 'Server error.'],
        };

        if ($e instanceof ApiException) {
            $errors = $e->errors;
            $headers = $e->headers;
        } elseif ($e instanceof ValidationException) {
            $errors = $e->errors();
        } elseif ($e instanceof HttpExceptionInterface) {
            $headers = $e->getHeaders();
        }

        $payload = [
            'code' => $code,
            'message' => $message,
            'request_id' => $this->context->requestId(),
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return new JsonResponse(['error' => $payload], $status, $headers);
    }

    private function statusText(int $status, string $message): string
    {
        // Messages of framework HTTP exceptions can contain internals; use the
        // standard status text for 5xx, and the message only for 4xx.
        if ($status >= 500) {
            return Response::$statusTexts[$status] ?? 'Server error.';
        }

        return $message !== '' ? $message : (Response::$statusTexts[$status] ?? 'Error');
    }
}
