<?php

namespace App\Console\Commands;

use App\Models\AiModel;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use App\Services\AiModel\EmbeddingCoverageService;
use App\Services\DocumentIngestion\DocumentEmbeddingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildEmbeddingsCommand extends Command
{
    protected $signature = 'knowledge:rebuild-embeddings
        {--base= : Knowledge base name or ID}
        {--document-id= : Single knowledge document ID}
        {--model= : AI model ID or model_key, default active embedding model}
        {--limit=0 : Max documents to process, 0 means all}
        {--offset=0 : Number of documents to skip}
        {--force : Rebuild documents even if all chunks are already embedded for the model}';

    protected $description = 'Build or rebuild embeddings for document chunks in a knowledge base or a single document, using a specified model key.';

    public function handle(DocumentEmbeddingService $embeddingService, EmbeddingCoverageService $coverageService): int
    {
        $model = $this->resolveEmbeddingModel($this->stringOption('model'));
        if (! $model) {
            $this->error('Embedding model not found. Activate/register an embedding model or pass --model.');
            return self::FAILURE;
        }

        $query = KnowledgeDocument::query()->orderBy('id');

        $documentId = $this->stringOption('document-id');
        if ($documentId !== '') {
            $query->where('id', (int) $documentId);
        }

        $baseOption = $this->stringOption('base');
        $baseId = null;
        if ($baseOption !== '') {
            $baseId = $this->resolveBaseId($baseOption);
            if ($baseId === null) {
                $this->error('Knowledge base not found: '.$baseOption);
                return self::FAILURE;
            }
            $query->where('knowledge_base_id', $baseId);
        }

        $offset = max(0, (int) $this->stringOption('offset', '0'));
        $limit = max(0, (int) $this->stringOption('limit', '0'));
        $force = (bool) $this->option('force');

        if ($offset > 0) {
            $query->skip($offset);
        }
        if ($limit > 0) {
            $query->take($limit);
        }

        $documents = $query->get();
        if ($documents->isEmpty()) {
            $this->warn('No documents found.');
            return self::SUCCESS;
        }

        $this->info("Embedding model: {$model->model_key}");
        $coverage = $coverageService->knowledgeBaseCoverage($model, $baseId);
        $this->line(sprintf(
            'Current coverage: %s/%s chunks, missing=%s, rate=%s%%',
            $coverage['embedded_chunks'],
            $coverage['total_chunks'],
            $coverage['missing_chunks'],
            $coverage['coverage_rate']
        ));

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($documents as $document) {
            $chunkCount = $document->chunks()->count();
            $embeddedCount = DB::table('document_chunk_embeddings as dce')
                ->join('document_chunks as dc', 'dc.id', '=', 'dce.document_chunk_id')
                ->where('dc.knowledge_document_id', $document->id)
                ->where('dce.model_key', $model->model_key)
                ->whereNotNull('dce.embedding')
                ->count();

            if ($chunkCount === 0) {
                $this->warn("skip document {$document->id}: no chunks - {$document->title}");
                $skipped++;
                continue;
            }

            if (! $force && $embeddedCount >= $chunkCount) {
                $this->warn("skip document {$document->id}: already embedded {$embeddedCount}/{$chunkCount} for {$model->model_key} - {$document->title}");
                $skipped++;
                continue;
            }

            $this->line("Embedding document {$document->id}: {$document->title} chunks={$chunkCount} existing={$embeddedCount}");

            try {
                if ($force) {
                    DB::table('document_chunk_embeddings')
                        ->whereIn('document_chunk_id', $document->chunks()->select('id'))
                        ->where('model_key', $model->model_key)
                        ->delete();

                    if ($model->is_active) {
                        DB::table('document_chunks')->where('knowledge_document_id', $document->id)->update(['embedding' => null]);
                    }
                }

                $result = $embeddingService->embedDocumentChunks($document->id, $model->model_key, $force);
                $this->info("  ok: embedded={$result['embedded_count']} model_key=".($result['model_key'] ?? $model->model_key));
                $processed++;
            } catch (\Throwable $exception) {
                $this->error('  failed: '.$exception->getMessage());
                $failed++;
            }
        }

        $this->info("Done. processed={$processed}, skipped={$skipped}, failed={$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveEmbeddingModel(string $value = ''): ?AiModel
    {
        if ($value !== '') {
            return ctype_digit($value)
                ? AiModel::query()->where('id', (int) $value)->where('task_type', 'embedding')->first()
                : AiModel::query()->where('model_key', $value)->where('task_type', 'embedding')->first();
        }

        return AiModel::query()
            ->where('task_type', 'embedding')
            ->where('is_active', true)
            ->first();
    }

    private function resolveBaseId(string $base): ?int
    {
        if (ctype_digit($base)) {
            return (int) $base;
        }

        return KnowledgeBase::query()->where('name', $base)->value('id');
    }

    private function stringOption(string $name, string $default = ''): string
    {
        $value = $this->option($name);
        if (is_array($value)) {
            $value = reset($value);
        }
        if ($value === null || $value === false || $value === '') {
            return $default;
        }
        return (string) $value;
    }
}
