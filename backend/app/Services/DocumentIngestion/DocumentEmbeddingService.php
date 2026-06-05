<?php

namespace App\Services\DocumentIngestion;

use App\Models\AiModel;
use App\Models\DocumentChunk;
use App\Models\DocumentChunkEmbedding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DocumentEmbeddingService
{
    /**
     * @return array<string, mixed>
     */
    public function embedDocumentChunks(int $documentId, ?string $modelKey = null, bool $force = false): array
    {
        $activeModel = $this->resolveEmbeddingModel($modelKey);
        $effectiveModelKey = $activeModel?->model_key ?? $modelKey ?? 'runtime-default';

        $chunksQuery = DocumentChunk::query()
            ->where('knowledge_document_id', $documentId)
            ->orderBy('chunk_index');

        if (! $force) {
            $chunksQuery->whereNotExists(function ($query) use ($effectiveModelKey): void {
                $query->selectRaw('1')
                    ->from('document_chunk_embeddings as dce')
                    ->whereColumn('dce.document_chunk_id', 'document_chunks.id')
                    ->where('dce.model_key', $effectiveModelKey);
            });
        }

        $chunks = $chunksQuery->get();

        if ($chunks->isEmpty()) {
            return [
                'embedded_count' => 0,
                'model_key' => $effectiveModelKey,
                'message' => 'No chunks found or all chunks already embedded for this model.',
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

        $provider = $payload['provider'] ?? null;
        $runtimeModel = $payload['model'] ?? null;
        $dimension = (int) ($payload['dimension'] ?? (isset($embeddings[0]) ? count($embeddings[0]) : 0));

        foreach ($chunks->values() as $index => $chunk) {
            $vector = $this->toPgVector($embeddings[$index]);

            $embeddingRow = DocumentChunkEmbedding::query()->updateOrCreate(
                [
                    'document_chunk_id' => $chunk->id,
                    'model_key' => $effectiveModelKey,
                ],
                [
                    'ai_model_id' => $activeModel?->id,
                    'provider' => $provider,
                    'model' => $runtimeModel,
                    'dimension' => $dimension,
                    'metadata' => [
                        'embedded_at' => now()->toISOString(),
                        'runtime_provider' => $provider,
                        'runtime_model' => $runtimeModel,
                    ],
                ]
            );

            DB::update(
                'UPDATE document_chunk_embeddings SET embedding = ?::vector, updated_at = NOW() WHERE id = ?',
                [$vector, $embeddingRow->id]
            );

            // Backward compatibility for the current single-vector search/index path.
            // This can be removed after all search/stat commands are migrated to document_chunk_embeddings.
            if ($activeModel?->is_active || $effectiveModelKey === 'runtime-default') {
                DB::update(
                    'UPDATE document_chunks SET embedding = ?::vector, updated_at = NOW() WHERE id = ?',
                    [$vector, $chunk->id]
                );
            }
        }

        return [
            'embedded_count' => $chunks->count(),
            'model_key' => $effectiveModelKey,
            'provider' => $provider,
            'model' => $runtimeModel,
            'dimension' => $dimension,
        ];
    }

    private function resolveEmbeddingModel(?string $modelKey = null): ?AiModel
    {
        if ($modelKey) {
            return AiModel::query()->where('model_key', $modelKey)->first();
        }

        return AiModel::query()
            ->where('task_type', 'embedding')
            ->where('is_active', true)
            ->first();
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
