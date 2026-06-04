<?php

namespace App\Console\Commands;

use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use App\Services\DocumentIngestion\DocumentEmbeddingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildEmbeddingsCommand extends Command
{
    protected $signature = 'knowledge:rebuild-embeddings
        {--base= : Knowledge base name or ID}
        {--document-id= : Single knowledge document ID}
        {--limit=0 : Max documents to process, 0 means all}
        {--offset=0 : Number of documents to skip}
        {--force : Rebuild documents even if all chunks are already embedded}';

    protected $description = 'Rebuild embeddings for document chunks in a knowledge base or a single document.';

    public function handle(DocumentEmbeddingService $embeddingService): int
    {
        $query = KnowledgeDocument::query()->orderBy('id');

        $documentId = $this->stringOption('document-id');
        if ($documentId !== '') {
            $query->where('id', (int) $documentId);
        }

        $baseOption = $this->stringOption('base');
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

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($documents as $document) {
            $chunkCount = $document->chunks()->count();
            $embeddedCount = $document->chunks()->whereNotNull('embedding')->count();

            if ($chunkCount === 0) {
                $this->warn("skip document {$document->id}: no chunks - {$document->title}");
                $skipped++;
                continue;
            }

            if (! $force && $embeddedCount >= $chunkCount) {
                $this->warn("skip document {$document->id}: already embedded {$embeddedCount}/{$chunkCount} - {$document->title}");
                $skipped++;
                continue;
            }

            $this->line("Embedding document {$document->id}: {$document->title} chunks={$chunkCount}");

            try {
                if ($force) {
                    DB::table('document_chunks')->where('knowledge_document_id', $document->id)->update(['embedding' => null]);
                }

                $result = $embeddingService->embedDocumentChunks($document->id);
                $this->info("  ok: embedded={$result['embedded_count']} model=".($result['model'] ?? 'unknown'));
                $processed++;
            } catch (\Throwable $exception) {
                $this->error('  failed: '.$exception->getMessage());
                $failed++;
            }
        }

        $this->info("Done. processed={$processed}, skipped={$skipped}, failed={$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
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
