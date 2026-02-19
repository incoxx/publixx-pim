<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\PqlQueryRequest;
use App\Services\Pql\PqlExecutor;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class PqlController extends Controller
{
    public function __construct(
        private readonly PqlExecutor $executor,
    ) {}

    /**
     * POST /api/v1/pql/query
     *
     * Execute a PQL query and return JSON results.
     */
    public function query(PqlQueryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->executor->execute(
                pql: $validated['pql'],
                languages: $validated['lang'] ?? ['de'],
                limit: $validated['limit'] ?? 50,
                offset: $validated['offset'] ?? 0,
                mappingId: $validated['mapping_id'] ?? null,
            );

            return response()->json($result, Response::HTTP_OK);
        } catch (\InvalidArgumentException $e) {
            return $this->problemResponse(
                title: 'PQL Query Error',
                detail: $e->getMessage(),
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }

    /**
     * POST /api/v1/pql/query/count
     *
     * Return only the total hit count for a PQL query.
     */
    public function count(PqlQueryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->executor->count(
                pql: $validated['pql'],
                languages: $validated['lang'] ?? ['de'],
                mappingId: $validated['mapping_id'] ?? null,
            );

            return response()->json($result, Response::HTTP_OK);
        } catch (\InvalidArgumentException $e) {
            return $this->problemResponse(
                title: 'PQL Count Error',
                detail: $e->getMessage(),
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }

    /**
     * POST /api/v1/pql/query/validate
     *
     * Validate a PQL query without executing it.
     */
    public function validate(PqlQueryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->executor->validate($validated['pql']);
        $status = $result['valid'] ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY;

        return response()->json($result, $status);
    }

    /**
     * POST /api/v1/pql/query/explain
     *
     * Return the query plan: AST, generated SQL, bindings, and estimated cost.
     */
    public function explain(PqlQueryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->executor->explain(
                pql: $validated['pql'],
                languages: $validated['lang'] ?? ['de'],
            );

            return response()->json($result, Response::HTTP_OK);
        } catch (\InvalidArgumentException $e) {
            return $this->problemResponse(
                title: 'PQL Explain Error',
                detail: $e->getMessage(),
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }

    /**
     * RFC 7807 Problem Details error response.
     */
    private function problemResponse(string $title, string $detail, int $status): JsonResponse
    {
        return response()->json([
            'type' => 'https://httpstatuses.com/' . $status,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ], $status, ['Content-Type' => 'application/problem+json']);
    }
}
