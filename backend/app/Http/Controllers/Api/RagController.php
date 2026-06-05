<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Rag\RagSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class RagController extends Controller
{
    public function search(Request $request, RagSearchService $searchService): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:2000'],
            'knowledge_base_id' => ['nullable', 'integer', 'exists:knowledge_bases,id'],
            'top_k' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        try {
            $startedAt = microtime(true);
            $results = $searchService->search(
                $validated['query'],
                $validated['knowledge_base_id'] ?? null,
                $validated['top_k'] ?? 5,
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'query' => $validated['query'],
                    'results' => $results,
                    'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ],
                'message' => 'ok',
                'errors' => null,
            ]);
        } catch (Throwable $exception) {
            Log::error('RAG search failed', [
                'query' => $validated['query'],
                'knowledge_base_id' => $validated['knowledge_base_id'] ?? null,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'data' => [
                    'query' => $validated['query'],
                    'results' => [],
                ],
                'message' => 'rag search failed',
                'errors' => [
                    'rag' => [$exception->getMessage()],
                ],
            ], 500);
        }
    }
}
