<?php

namespace App\Services\DocumentIngestion;

use App\Models\DocumentChunk;
use App\Models\DocumentIngestionJob;
use App\Models\KnowledgeDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DocumentIngestionProcessor
{
    public function process(DocumentIngestionJob $job): DocumentIngestionJob
    {
        if (! in_array($job->status, ['pending', 'failed'], true)) {
            return $job;
        }

        $job->forceFill([
            'status' => 'processing',
            'progress' => 10,
            'started_at' => now(),
            'error_message' => null,
        ])->save();

        try {
            $result = match ($job->job_type) {
                'file_parse' => $this->parseFile($job),
                'url_fetch' => $this->parseUrl($job),
                default => throw new RuntimeException("Unsupported ingestion job type: {$job->job_type}"),
            };

            $this->persistParseResult($job, $result);

            $job->forceFill([
                'status' => 'completed',
                'progress' => 100,
                'finished_at' => now(),
                'metadata' => array_merge($job->metadata ?? [], [
                    'parse_metadata' => $result['metadata'] ?? [],
                    'chunk_count' => count($result['chunks'] ?? []),
                ]),
            ])->save();
        } catch (\Throwable $exception) {
            $job->forceFill([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();
        }

        return $job->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFile(DocumentIngestionJob $job): array
    {
        $file = $job->file;
        if (! $file) {
            throw new RuntimeException('Document file not found.');
        }

        $path = Storage::disk($file->disk)->path($file->path);
        if (! is_file($path)) {
            throw new RuntimeException('Stored file does not exist.');
        }

        $response = Http::timeout(60)
            ->attach('file', file_get_contents($path), $file->original_name)
            ->post($this->aiServiceUrl('/documents/parse-file'));

        if ($response->failed()) {
            throw new RuntimeException('AI service parse-file failed: '.$response->body());
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function parseUrl(DocumentIngestionJob $job): array
    {
        if (! $job->source_url) {
            throw new RuntimeException('Source URL is empty.');
        }

        $response = Http::timeout(60)
            ->post($this->aiServiceUrl('/documents/parse-url'), [
                'url' => $job->source_url,
                'chunk_size' => 1200,
                'chunk_overlap' => 150,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('AI service parse-url failed: '.$response->body());
        }

        return $response->json();
    }

    /**
     * @param array<string, mixed> $result
     */
    private function persistParseResult(DocumentIngestionJob $job, array $result): void
    {
        $document = $job->document;
        if (! $document instanceof KnowledgeDocument) {
            throw new RuntimeException('Knowledge document not found.');
        }

        $document->forceFill([
            'title' => $document->title ?: ($result['title'] ?? $document->title),
            'content' => $result['content'] ?? $document->content,
            'metadata' => array_merge($document->metadata ?? [], [
                'last_ingestion_job_id' => $job->id,
                'parse_metadata' => $result['metadata'] ?? [],
            ]),
        ])->save();

        DocumentChunk::query()
            ->where('knowledge_document_id', $document->id)
            ->when($job->document_file_id, fn ($query) => $query->where('document_file_id', $job->document_file_id))
            ->delete();

        foreach (($result['chunks'] ?? []) as $chunk) {
            DocumentChunk::query()->create([
                'knowledge_document_id' => $document->id,
                'document_file_id' => $job->document_file_id,
                'chunk_index' => $chunk['index'],
                'chunk_type' => 'text',
                'title' => $chunk['title'] ?? null,
                'content' => $chunk['content'],
                'token_count' => $chunk['token_count'] ?? null,
                'metadata' => $chunk['metadata'] ?? [],
            ]);
        }
    }

    private function aiServiceUrl(string $path): string
    {
        return rtrim(config('services.ai_service.url', 'http://ai-service:8000'), '/').$path;
    }
}
