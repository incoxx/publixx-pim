<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * RFC 7807 Problem Details handler for all API responses.
 *
 * Every error response follows the format:
 * {
 *   "type": "urn:publixx:pim:error:<error-type>",
 *   "title": "Human-readable summary",
 *   "status": 422,
 *   "detail": "Detailed explanation",
 *   "errors": { ... }  // optional, for validation
 * }
 */
class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->renderable(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $this->renderApiException($e);
            }

            return null;
        });
    }

    private function renderApiException(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => $this->renderValidationException($e),
            $e instanceof ModelNotFoundException => $this->renderProblem(
                'urn:publixx:pim:error:not-found',
                'Resource not found',
                404,
                "The requested {$this->getModelName($e)} was not found."
            ),
            $e instanceof NotFoundHttpException => $this->renderProblem(
                'urn:publixx:pim:error:not-found',
                'Endpoint not found',
                404,
                'The requested URL does not match any API endpoint.'
            ),
            $e instanceof AuthenticationException => $this->renderProblem(
                'urn:publixx:pim:error:unauthenticated',
                'Unauthenticated',
                401,
                'A valid authentication token is required.'
            ),
            $e instanceof MethodNotAllowedHttpException => $this->renderProblem(
                'urn:publixx:pim:error:method-not-allowed',
                'Method not allowed',
                405,
                'The HTTP method is not supported for this endpoint.'
            ),
            $e instanceof TooManyRequestsHttpException => $this->renderProblem(
                'urn:publixx:pim:error:rate-limit',
                'Too many requests',
                429,
                'Rate limit exceeded. Please try again later.'
            ),
            $e instanceof HttpException => $this->renderProblem(
                'urn:publixx:pim:error:http',
                $e->getMessage() ?: 'HTTP Error',
                $e->getStatusCode(),
                $e->getMessage()
            ),
            default => $this->renderProblem(
                'urn:publixx:pim:error:internal',
                'Internal server error',
                500,
                config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.'
            ),
        };
    }

    private function renderValidationException(ValidationException $e): JsonResponse
    {
        return $this->renderProblem(
            'urn:publixx:pim:error:validation',
            'Validation failed',
            422,
            'One or more fields failed validation.',
            ['errors' => $e->errors()]
        );
    }

    private function renderProblem(string $type, string $title, int $status, string $detail, array $extra = []): JsonResponse
    {
        $body = array_merge([
            'type' => $type,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ], $extra);

        return response()->json($body, $status, [
            'Content-Type' => 'application/problem+json',
        ]);
    }

    private function getModelName(ModelNotFoundException $e): string
    {
        $model = $e->getModel();
        $parts = explode('\\', $model);

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', end($parts)));
    }
}
