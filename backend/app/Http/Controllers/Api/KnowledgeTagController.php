<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\KnowledgeTagRequest;
use App\Http\Resources\KnowledgeTagResource;
use App\Models\KnowledgeTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class KnowledgeTagController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = KnowledgeTag::query()->orderBy('name');

        if ($keyword = $request->string('keyword')->toString()) {
            $query->where('name', 'like', "%{$keyword}%");
        }

        if ($tagType = $request->string('tag_type')->toString()) {
            $query->where('tag_type', $tagType);
        }

        return KnowledgeTagResource::collection($query->paginate($request->integer('per_page', 50)));
    }

    public function store(KnowledgeTagRequest $request): KnowledgeTagResource
    {
        $tag = KnowledgeTag::query()->create($request->validated());

        return new KnowledgeTagResource($tag);
    }

    public function show(KnowledgeTag $knowledgeTag): KnowledgeTagResource
    {
        return new KnowledgeTagResource($knowledgeTag);
    }

    public function update(KnowledgeTagRequest $request, KnowledgeTag $knowledgeTag): KnowledgeTagResource
    {
        $knowledgeTag->update($request->validated());

        return new KnowledgeTagResource($knowledgeTag->refresh());
    }

    public function destroy(KnowledgeTag $knowledgeTag): JsonResponse
    {
        $knowledgeTag->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'ok',
            'errors' => null,
        ]);
    }
}
