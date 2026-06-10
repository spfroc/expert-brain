<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\KnowledgeDocumentRequest;
use App\Http\Resources\KnowledgeDocumentResource;
use App\Models\AiModel;
use App\Models\KnowledgeDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class KnowledgeDocumentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $activeEmbeddingModel = AiModel::query()
            ->where('task_type', 'embedding')
            ->where('is_active', true)
            ->first();
        $activeModelKey = $activeEmbeddingModel?->model_key;

        $query = KnowledgeDocument::query()
            ->with(['tags', 'latestIngestionJob'])
            ->withCount([
                'files',
                'chunks',
                'chunks as legacy_embeddings_count' => fn ($query) => $query->whereNotNull('embedding'),
            ])
            ->latest('id');

        if ($activeModelKey) {
            $query->withCount([
                'chunks as active_model_embeddings_count' => fn ($query) => $query->whereHas(
                    'embeddings',
                    fn ($embeddingQuery) => $embeddingQuery->where('model_key', $activeModelKey)
                ),
            ]);
        }

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

        $documents = $query->paginate($request->integer('per_page', 15));
        $documents->getCollection()->each(function (KnowledgeDocument $document) use ($activeModelKey): void {
            $document->setAttribute('active_embedding_model_key', $activeModelKey);
            if (! $activeModelKey) {
                $document->setAttribute('active_model_embeddings_count', 0);
            }
        });

        return KnowledgeDocumentResource::collection($documents);
    }

    public function store(KnowledgeDocumentRequest $request): KnowledgeDocumentResource
    {
        $payload = collect($request->safe()->except('tag_ids'))
            ->filter(fn ($value) => $value !== null)
            ->all();

        $payload['source_type'] ??= 'manual';
        $payload['version'] ??= '1.0';
        $payload['status'] ??= 'draft';
        $payload['created_by'] = $request->user()?->id;

        $document = KnowledgeDocument::query()->create($payload);
        $document->tags()->sync($request->input('tag_ids', []));

        return new KnowledgeDocumentResource($document->load('tags'));
    }

    public function show(KnowledgeDocument $knowledgeDocument): KnowledgeDocumentResource
    {
        return new KnowledgeDocumentResource($knowledgeDocument->load(['tags', 'latestIngestionJob'])->loadCount(['files', 'chunks']));
    }

    public function update(KnowledgeDocumentRequest $request, KnowledgeDocument $knowledgeDocument): KnowledgeDocumentResource
    {
        $payload = collect($request->safe()->except('tag_ids'))
            ->filter(fn ($value) => $value !== null)
            ->all();

        $knowledgeDocument->update($payload);

        if ($request->has('tag_ids')) {
            $knowledgeDocument->tags()->sync($request->input('tag_ids', []));
        }

        return new KnowledgeDocumentResource($knowledgeDocument->refresh()->load('tags'));
    }

    public function destroy(KnowledgeDocument $knowledgeDocument): JsonResponse
    {
        $knowledgeDocument->load(['files', 'chunks.embeddings', 'ingestionJobs']);

        DB::transaction(function () use ($knowledgeDocument): void {
            $knowledgeDocument->tags()->detach();
            $knowledgeDocument->ingestionJobs()->delete();

            foreach ($knowledgeDocument->chunks as $chunk) {
                $chunk->embeddings()->delete();
            }
            $knowledgeDocument->chunks()->delete();

            $knowledgeDocument->files()->delete();
            $knowledgeDocument->delete();
        });

        foreach ($knowledgeDocument->files as $file) {
            if ($file->disk && $file->path && Storage::disk($file->disk)->exists($file->path)) {
                Storage::disk($file->disk)->delete($file->path);
            }
        }

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'deleted',
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
