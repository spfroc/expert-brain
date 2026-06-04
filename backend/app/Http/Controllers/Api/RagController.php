<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Rag\RagSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RagController extends Controller
{
    public function search(Request $request, RagSearchService $searchService): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:2000'],
            'knowledge_base_id' => ['nullable', 'integer', 'exists:knowledge_bases,id'],
            'top_k' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

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
            ],
            'message' => 'ok',
            'errors' => null,
        ]);
    }
}
