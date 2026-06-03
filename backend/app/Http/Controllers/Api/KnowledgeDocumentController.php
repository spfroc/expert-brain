<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\KnowledgeDocumentRequest;
use App\Http\Resources\KnowledgeDocumentResource;
use App\Models\KnowledgeDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class KnowledgeDocumentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = KnowledgeDocument::query()
            ->with('tags')
            ->latest('id');

        if ($baseId = $request->integer('knowledge_base_id')) {
            $query->where('knowledge_base_id', $baseId);
        }

        if ($categoryId = $request->integer('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($keyword = $request->string('keyword')->toString()) {
            $query->where(function ($inner) use ($keyword): void {
                $inner->where('title', 'like', "%{$keyword}%")
                    ->orWhere('summary', 'like', "%{$keyword}%")
                    ->orWhere('content', 'like', "%{$keyword}%");
            });
        }

        return KnowledgeDocumentResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function store(KnowledgeDocumentRequest $request): KnowledgeDocumentResource
    {
        $payload = $request->safe()->except('tag_ids');
        $document = KnowledgeDocument::query()->create($payload + [
            'created_by' => $request->user()?->id,
        ]);

        $document->tags()->sync($request->input('tag_ids', []));

        return new KnowledgeDocumentResource($document->load('tags'));
    }

    public function show(KnowledgeDocument $knowledgeDocument): KnowledgeDocumentResource
    {
        return new KnowledgeDocumentResource($knowledgeDocument->load('tags'));
    }

    public function update(KnowledgeDocumentRequest $request, KnowledgeDocument $knowledgeDocument): KnowledgeDocumentResource
    {
        $payload = $request->safe()->except('tag_ids');
        $knowledgeDocument->update($payload);

        if ($request->has('tag_ids')) {
            $knowledgeDocument->tags()->sync($request->input('tag_ids', []));
        }

        return new KnowledgeDocumentResource($knowledgeDocument->refresh()->load('tags'));
    }

    public function destroy(KnowledgeDocument $knowledgeDocument): JsonResponse
    {
        $knowledgeDocument->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'ok',
            'errors' => null,
        ]);
    }

    public function publish(KnowledgeDocument $knowledgeDocument): KnowledgeDocumentResource
    {
        $knowledgeDocument->forceFill([
            'status' => 'published',
            'published_at' => now(),
            'reviewed_by' => request()->user()?->id,
            'reviewed_at' => now(),
        ])->save();

        return new KnowledgeDocumentResource($knowledgeDocument->refresh()->load('tags'));
    }

    public function expire(KnowledgeDocument $knowledgeDocument): KnowledgeDocumentResource
    {
        $knowledgeDocument->forceFill(['status' => 'expired'])->save();

        return new KnowledgeDocumentResource($knowledgeDocument->refresh()->load('tags'));
    }

    public function archive(KnowledgeDocument $knowledgeDocument): KnowledgeDocumentResource
    {
        $knowledgeDocument->forceFill(['status' => 'archived'])->save();

        return new KnowledgeDocumentResource($knowledgeDocument->refresh()->load('tags'));
    }
}
