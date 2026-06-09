<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentFileResource;
use App\Http\Resources\DocumentIngestionJobResource;
use App\Jobs\RunDocumentIngestionJob;
use App\Models\DocumentFile;
use App\Models\DocumentIngestionJob;
use App\Models\KnowledgeDocument;
use App\Services\DocumentIngestion\DocumentEmbeddingService;
use App\Services\DocumentIngestion\ManualDocumentChunkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DocumentIngestionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = DocumentIngestionJob::query()
            ->with(['document:id,title,knowledge_base_id', 'file:id,original_name'])
            ->latest('id');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($jobType = $request->string('job_type')->toString()) {
            $query->where('job_type', $jobType);
        }

        if ($documentId = $request->integer('knowledge_document_id')) {
            $query->where('knowledge_document_id', $documentId);
        }

        return DocumentIngestionJobResource::collection($query->paginate($request->integer('per_page', 20)));
    }

    public function uploadFile(Request $request, KnowledgeDocument $knowledgeDocument): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'auto_process' => ['nullable', 'boolean'],
            'auto_embed' => ['nullable', 'boolean'],
        ]);

        $uploadedFile = $validated['file'];
        $path = $uploadedFile->store('knowledge-documents/'.$knowledgeDocument->id, 'local');
        $absolutePath = Storage::disk('local')->path($path);

        $file = DocumentFile::query()->create([
            'knowledge_document_id' => $knowledgeDocument->id,
            'original_name' => $uploadedFile->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $uploadedFile->getClientMimeType(),
            'size' => $uploadedFile->getSize(),
            'sha256' => hash_file('sha256', $absolutePath),
            'status' => 'uploaded',
            'uploaded_by' => $request->user()?->id,
        ]);

        $job = DocumentIngestionJob::query()->create([
            'knowledge_document_id' => $knowledgeDocument->id,
            'document_file_id' => $file->id,
            'job_type' => 'file_parse',
            'status' => 'pending',
            'progress' => 0,
            'created_by' => $request->user()?->id,
            'metadata' => [
                'original_name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'auto_process' => $request->boolean('auto_process', true),
                'auto_embed' => $request->boolean('auto_embed', true),
            ],
        ]);

        if ($request->boolean('auto_process', true)) {
            RunDocumentIngestionJob::dispatch($job->id, $request->boolean('auto_embed', true));
        }

        return response()->json([
            'success' => true,
            'data' => [
                'file' => new DocumentFileResource($file),
                'job' => new DocumentIngestionJobResource($job->refresh()),
            ],
            'message' => $request->boolean('auto_process', true)
                ? 'uploaded and queued for ingestion'
                : 'uploaded and ingestion job created',
            'errors' => null,
        ], 202);
    }

    public function importUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'knowledge_base_id' => ['required', 'integer', 'exists:knowledge_bases,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2000'],
            'source_type' => ['nullable', Rule::in(['url', 'policy', 'platform_doc', 'notice'])],
            'auto_process' => ['nullable', 'boolean'],
            'auto_embed' => ['nullable', 'boolean'],
        ]);

        [$document, $job] = $this->createUrlImportJob(
            knowledgeBaseId: (int) $validated['knowledge_base_id'],
            url: $validated['url'],
            title: $validated['title'] ?? null,
            sourceType: $validated['source_type'] ?? 'url',
            userId: $request->user()?->id,
            autoProcess: $request->boolean('auto_process', true),
            autoEmbed: $request->boolean('auto_embed', true),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'document' => $document,
                'job' => new DocumentIngestionJobResource($job),
            ],
            'message' => $request->boolean('auto_process', true)
                ? 'document created and queued for ingestion'
                : 'document created and ingestion job created',
            'errors' => null,
        ], 202);
    }

    public function importUrls(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'knowledge_base_id' => ['required', 'integer', 'exists:knowledge_bases,id'],
            'urls' => ['required_without:raw_urls', 'array', 'max:200'],
            'urls.*' => ['required', 'url', 'max:2000'],
            'raw_urls' => ['required_without:urls', 'nullable', 'string', 'max:200000'],
            'source_type' => ['nullable', Rule::in(['url', 'policy', 'platform_doc', 'notice'])],
            'auto_process' => ['nullable', 'boolean'],
            'auto_embed' => ['nullable', 'boolean'],
            'deduplicate' => ['nullable', 'boolean'],
        ]);

        $urls = $this->normalizeUrlList($validated['urls'] ?? [], $validated['raw_urls'] ?? null);
        if ($urls === []) {
            return response()->json([
                'success' => false,
                'data' => ['created_count' => 0, 'skipped_count' => 0, 'items' => []],
                'message' => 'no valid urls found',
                'errors' => ['urls' => ['没有找到有效 URL。']],
            ], 422);
        }

        $items = [];
        $createdCount = 0;
        $skippedCount = 0;
        $deduplicate = $request->boolean('deduplicate', true);

        foreach ($urls as $url) {
            if ($deduplicate) {
                $exists = KnowledgeDocument::query()
                    ->where('knowledge_base_id', $validated['knowledge_base_id'])
                    ->where('source_url', $url)
                    ->exists();

                if ($exists) {
                    $skippedCount++;
                    $items[] = [
                        'url' => $url,
                        'status' => 'skipped',
                        'reason' => 'source_url already exists in this knowledge base',
                    ];
                    continue;
                }
            }

            [$document, $job] = $this->createUrlImportJob(
                knowledgeBaseId: (int) $validated['knowledge_base_id'],
                url: $url,
                title: null,
                sourceType: $validated['source_type'] ?? 'url',
                userId: $request->user()?->id,
                autoProcess: $request->boolean('auto_process', true),
                autoEmbed: $request->boolean('auto_embed', true),
            );

            $createdCount++;
            $items[] = [
                'url' => $url,
                'status' => 'created',
                'document_id' => $document->id,
                'job_id' => $job->id,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'created_count' => $createdCount,
                'skipped_count' => $skippedCount,
                'items' => $items,
            ],
            'message' => 'batch import queued',
            'errors' => null,
        ], 202);
    }

    public function process(DocumentIngestionJob $documentIngestionJob): DocumentIngestionJobResource
    {
        if (in_array($documentIngestionJob->status, ['processing', 'completed'], true)) {
            return new DocumentIngestionJobResource($documentIngestionJob);
        }

        $documentIngestionJob->forceFill([
            'status' => 'pending',
            'progress' => 0,
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
        ])->save();

        RunDocumentIngestionJob::dispatch($documentIngestionJob->id, true);

        return new DocumentIngestionJobResource($documentIngestionJob->refresh());
    }

    public function chunk(KnowledgeDocument $knowledgeDocument, ManualDocumentChunkService $chunkService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $chunkService->chunkDocument($knowledgeDocument),
            'message' => 'ok',
            'errors' => null,
        ]);
    }

    public function embed(KnowledgeDocument $knowledgeDocument, DocumentEmbeddingService $embeddingService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $embeddingService->embedDocumentChunks($knowledgeDocument->id),
            'message' => 'ok',
            'errors' => null,
        ]);
    }

    public function indexDocument(
        KnowledgeDocument $knowledgeDocument,
        ManualDocumentChunkService $chunkService,
        DocumentEmbeddingService $embeddingService
    ): JsonResponse {
        $chunkResult = $chunkService->chunkDocument($knowledgeDocument);
        $embedResult = $embeddingService->embedDocumentChunks($knowledgeDocument->id);

        return response()->json([
            'success' => true,
            'data' => [
                'chunk' => $chunkResult,
                'embedding' => $embedResult,
            ],
            'message' => 'ok',
            'errors' => null,
        ]);
    }

    /**
     * @return array{0:KnowledgeDocument,1:DocumentIngestionJob}
     */
    private function createUrlImportJob(
        int $knowledgeBaseId,
        string $url,
        ?string $title,
        string $sourceType,
        ?int $userId,
        bool $autoProcess,
        bool $autoEmbed,
    ): array {
        $document = KnowledgeDocument::query()->create([
            'knowledge_base_id' => $knowledgeBaseId,
            'title' => $title ?: $this->guessTitleFromUrl($url),
            'source_type' => $sourceType,
            'source_url' => $url,
            'version' => '1.0',
            'status' => 'draft',
            'created_by' => $userId,
            'metadata' => [
                'ingestion_source' => 'url',
            ],
        ]);

        $job = DocumentIngestionJob::query()->create([
            'knowledge_document_id' => $document->id,
            'job_type' => 'url_fetch',
            'status' => 'pending',
            'progress' => 0,
            'source_url' => $url,
            'created_by' => $userId,
            'metadata' => [
                'auto_process' => $autoProcess,
                'auto_embed' => $autoEmbed,
            ],
        ]);

        if ($autoProcess) {
            RunDocumentIngestionJob::dispatch($job->id, $autoEmbed);
        }

        return [$document, $job];
    }

    /**
     * @param array<int, string> $urls
     * @return array<int, string>
     */
    private function normalizeUrlList(array $urls, ?string $rawUrls): array
    {
        $items = $urls;
        if ($rawUrls) {
            preg_match_all('/https?:\/\/[^\s,，;；"\'<>]+/u', $rawUrls, $matches);
            $items = array_merge($items, $matches[0] ?? []);
        }

        return array_values(array_unique(array_filter(array_map(function ($url): ?string {
            $url = trim((string) $url);
            if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                return null;
            }

            return $url;
        }, $items))));
    }

    private function guessTitleFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $basename = basename($path);
        $decoded = urldecode($basename ?: $url);

        return mb_substr($decoded, 0, 180) ?: $url;
    }
}
