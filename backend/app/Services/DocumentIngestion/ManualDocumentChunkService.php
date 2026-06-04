<?php

namespace App\Services\DocumentIngestion;

use App\Models\DocumentChunk;
use App\Models\KnowledgeDocument;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ManualDocumentChunkService
{
    /**
     * @return array<string, mixed>
     */
    public function chunkDocument(KnowledgeDocument $document): array
    {
        $content = trim((string) $document->content);

        if ($content === '') {
            return [
                'chunk_count' => 0,
                'message' => 'Document content is empty.',
            ];
        }

        $response = Http::timeout(60)->post($this->aiServiceUrl('/documents/parse-text'), [
            'filename' => $document->title,
            'content' => $content,
            'chunk_size' => 1200,
            'chunk_overlap' => 150,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('AI service parse-text failed: '.$response->body());
        }

        $payload = $response->json();
        $chunks = $payload['chunks'] ?? [];

        DocumentChunk::query()
            ->where('knowledge_document_id', $document->id)
            ->whereNull('document_file_id')
            ->delete();

        foreach ($chunks as $chunk) {
            DocumentChunk::query()->create([
                'knowledge_document_id' => $document->id,
                'document_file_id' => null,
                'chunk_index' => $chunk['index'],
                'chunk_type' => 'text',
                'title' => $chunk['title'] ?? null,
                'content' => $chunk['content'],
                'token_count' => $chunk['token_count'] ?? null,
                'metadata' => $chunk['metadata'] ?? [],
            ]);
        }

        $document->forceFill([
            'metadata' => array_merge($document->metadata ?? [], [
                'manual_chunked_at' => now()->toISOString(),
                'manual_chunk_count' => count($chunks),
                'parse_metadata' => $payload['metadata'] ?? [],
            ]),
        ])->save();

        return [
            'chunk_count' => count($chunks),
            'parser' => $payload['metadata']['parser'] ?? 'plain_text',
        ];
    }

    private function aiServiceUrl(string $path): string
    {
        return rtrim(config('services.ai_service.url', 'http://ai-service:8000'), '/').$path;
    }
}
