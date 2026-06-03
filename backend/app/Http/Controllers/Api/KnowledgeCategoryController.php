<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\KnowledgeCategoryRequest;
use App\Http\Resources\KnowledgeCategoryResource;
use App\Models\KnowledgeCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class KnowledgeCategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = KnowledgeCategory::query()
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($baseId = $request->integer('knowledge_base_id')) {
            $query->where('knowledge_base_id', $baseId);
        }

        return KnowledgeCategoryResource::collection($query->get());
    }

    public function store(KnowledgeCategoryRequest $request): KnowledgeCategoryResource
    {
        $category = KnowledgeCategory::query()->create($request->validated());

        return new KnowledgeCategoryResource($category);
    }

    public function show(KnowledgeCategory $knowledgeCategory): KnowledgeCategoryResource
    {
        return new KnowledgeCategoryResource($knowledgeCategory->load('children'));
    }

    public function update(KnowledgeCategoryRequest $request, KnowledgeCategory $knowledgeCategory): KnowledgeCategoryResource
    {
        $knowledgeCategory->update($request->validated());

        return new KnowledgeCategoryResource($knowledgeCategory->refresh());
    }

    public function destroy(KnowledgeCategory $knowledgeCategory): JsonResponse
    {
        $knowledgeCategory->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'ok',
            'errors' => null,
        ]);
    }
}
