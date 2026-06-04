<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentFileResource;
use App\Http\Resources\DocumentIngestionJobResource;
use App\Models\DocumentFile;
use App\Models\DocumentIngestionJob;
use App\Models\KnowledgeDocument;
use App\Services\DocumentIngestion\DocumentEmbeddingService;
use App\Services\DocumentIngestion\DocumentIngestionProcessor;
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

    public function uploadFile(
        Request $request,
        KnowledgeDocument $knowledgeDocument,
        DocumentIngestionProcessor $processor,
        DocumentEmbeddingService $embeddingService
    ): JsonResponse {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
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
                'auto_process' => true,
            ],
        ]);

        $processedJob = $processor->process($job);
        $embeddingResult = null;

        if ($processedJob->status === 'completed') {
            $embeddingResult = $embeddingService->embedDocumentChunks($knowledgeDocument->id);
        }

        return response()->json([
            'success' => $processedJob->status === 'completed',
            'data' => [
                'file' => new DocumentFileResource($file),
                'job' => new DocumentIngestionJobResource($processedJob),
                'embedding' => $embeddingResult,
            ],
            'message' => $processedJob->status === 'completed' ? 'uploaded, parsed, chunked and embedded' : 'upload succeeded but ingestion failed',
            'errors' => $processedJob->status === 'completed' ? null : [
                'ingestion' => [$processedJob->error_message],
            ],
        ], $processedJob->status === 'completed' ? 201 : 422);
    }

    public function importUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'knowledge_base_id' => ['required', 'integer', 'exists:knowledge_bases,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2000'],
            'source_type' => ['nullable', Rule::in(['url', 'policy', 'platform_doc', 'notice'])],
        ]);

        $document = KnowledgeDocument::query()->create([
            'knowledge_base_id' => $validated['knowledge_base_id'],
            'title' => $validated['title'] ?? $validated['url'],
            'source_type' => $validated['source_type'] ?? 'url',
            'source_url' => $validated['url'],
            'version' => '1.0',
            'status' => 'draft',
            'created_by' => $request->user()?->id,
            'metadata' => [
                'ingestion_source' => 'url',
            ],
        ]);

        $job = DocumentIngestionJob::query()->create([
            'knowledge_document_id' => $document->id,
            'job_type' => 'url_fetch',
            'status' => 'pending',
            'progress' => 0,
            'source_url' => $validated['url'],
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'document' => $document,
                'job' => new DocumentIngestionJobResource($job),
            ],
            'message' => 'ok',
            'errors' => null,
        ], 201);
    }

    public function process(DocumentIngestionJob $documentIngestionJob, DocumentIngestionProcessor $processor): DocumentIngestionJobResource
    {
        return new DocumentIngestionJobResource($processor->process($documentIngestionJob));
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
}
