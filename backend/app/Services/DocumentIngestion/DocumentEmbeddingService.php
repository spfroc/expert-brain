<?php

namespace App\Services\DocumentIngestion;

use App\Models\DocumentChunk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DocumentEmbeddingService
{
    /**
     * @return array<string, mixed>
     */
    public function embedDocumentChunks(int $documentId): array
    {
        $chunks = DocumentChunk::query()
            ->where('knowledge_document_id', $documentId)
            ->orderBy('chunk_index')
            ->get();

        if ($chunks->isEmpty()) {
            return [
                'embedded_count' => 0,
                'message' => 'No chunks found.',
            ];
        }

        $response = Http::timeout(120)->post($this->aiServiceUrl('/embeddings/embed'), [
            'texts' => $chunks->pluck('content')->values()->all(),
            'normalize' => true,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('AI service embedding failed: '.$response->body());
        }

        $payload = $response->json();
        $embeddings = $payload['embeddings'] ?? [];

        if (count($embeddings) !== $chunks->count()) {
            throw new RuntimeException('Embedding count does not match chunk count.');
        }

        foreach ($chunks->values() as $index => $chunk) {
            $vector = $this->toPgVector($embeddings[$index]);

            DB::update(
                'UPDATE document_chunks SET embedding = ?::vector, updated_at = NOW() WHERE id = ?',
                [$vector, $chunk->id]
            );
        }

        return [
            'embedded_count' => $chunks->count(),
            'provider' => $payload['provider'] ?? null,
            'model' => $payload['model'] ?? null,
            'dimension' => $payload['dimension'] ?? null,
        ];
    }

    /**
     * @param array<int, float|int> $values
     */
    private function toPgVector(array $values): string
    {
        return '['.implode(',', array_map(
            fn ($value) => is_numeric($value) ? (string) ((float) $value) : '0',
            $values
        )).']';
    }

    private function aiServiceUrl(string $path): string
    {
        return rtrim(config('services.ai_service.url', 'http://ai-service:8000'), '/').$path;
    }
}
