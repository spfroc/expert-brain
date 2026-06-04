<?php

namespace App\Console\Commands;

use App\Models\DocumentFile;
use App\Models\DocumentIngestionJob;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeImportBatch;
use App\Models\KnowledgeImportItem;
use App\Services\DocumentIngestion\DocumentEmbeddingService;
use App\Services\DocumentIngestion\DocumentIngestionProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class ImportBatchCommand extends Command
{
    protected $signature = 'knowledge:import-batch
        {action : create|run|retry|status|reset-failed}
        {--batch= : Batch ID or batch name}
        {--path= : Directory containing files, required for create}
        {--base=国家行政法规库 : Knowledge base name or ID}
        {--name= : Batch name, default generated from base and time}
        {--pattern=*.pdf : File pattern}
        {--limit=0 : Max items to run, 0 means all available}
        {--no-embed : Parse and chunk only, do not generate embeddings}
        {--force : Reprocess completed items}';

    protected $description = 'Create, run, retry, and inspect resumable local file import batches.';

    public function handle(DocumentIngestionProcessor $processor, DocumentEmbeddingService $embeddingService): int
    {
        return match ((string) $this->argument('action')) {
            'create' => $this->createBatch(),
            'run' => $this->runBatch($processor, $embeddingService, onlyFailed: false),
            'retry' => $this->runBatch($processor, $embeddingService, onlyFailed: true),
            'status' => $this->showStatus(),
            'reset-failed' => $this->resetFailed(),
            default => $this->invalidAction(),
        };
    }

    private function createBatch(): int
    {
        $path = $this->stringOption('path');
        if ($path === '' || ! is_dir($path)) {
            $this->error('Please provide a valid --path directory.');
            return self::FAILURE;
        }

        $base = $this->resolveOrCreateBase($this->stringOption('base', '国家行政法规库'));
        $pattern = $this->stringOption('pattern', '*.pdf');
        $name = $this->stringOption('name');
        if ($name === '') {
            $name = $base->name.' '.now()->format('Ymd-His');
        }

        $batch = KnowledgeImportBatch::query()->create([
            'name' => $name,
            'knowledge_base_id' => $base->id,
            'source_path' => $path,
            'pattern' => $pattern,
            'status' => 'pending',
            'metadata' => [
                'created_by_command' => 'knowledge:import-batch create',
            ],
            'created_by' => 1,
        ]);

        $finder = Finder::create()
            ->files()
            ->in($path)
            ->name($pattern)
            ->sortByName();

        $count = 0;
        foreach ($finder as $file) {
            $realPath = $file->getRealPath();
            KnowledgeImportItem::query()->updateOrCreate(
                [
                    'knowledge_import_batch_id' => $batch->id,
                    'source_path' => $realPath,
                ],
                [
                    'filename' => $file->getFilename(),
                    'title' => $this->titleFromFilename($file->getFilename()),
                    'sha256' => hash_file('sha256', $realPath),
                    'size' => $file->getSize(),
                    'status' => 'pending',
                    'metadata' => [
                        'relative_path' => $file->getRelativePathname(),
                    ],
                ]
            );
            $count++;
        }

        $this->refreshBatchCounts($batch->refresh());
        $this->info("Batch created: id={$batch->id}, name={$batch->name}, items={$count}");

        return self::SUCCESS;
    }

    private function runBatch(DocumentIngestionProcessor $processor, DocumentEmbeddingService $embeddingService, bool $onlyFailed): int
    {
        $batch = $this->findBatch();
        if (! $batch) {
            return self::FAILURE;
        }

        $limit = max(0, (int) $this->stringOption('limit', '0'));
        $noEmbed = (bool) $this->option('no-embed');
        $force = (bool) $this->option('force');

        $query = $batch->items()->orderBy('id');
        if ($onlyFailed) {
            $query->where('status', 'failed');
        } elseif (! $force) {
            $query->whereIn('status', ['pending', 'failed']);
        }
        if ($limit > 0) {
            $query->limit($limit);
        }

        $items = $query->get();
        if ($items->isEmpty()) {
            $this->warn('No import items to process.');
            $this->refreshBatchCounts($batch);
            return self::SUCCESS;
        }

        $batch->forceFill(['status' => 'processing', 'started_at' => $batch->started_at ?: now()])->save();

        $processed = 0;
        $failed = 0;
        foreach ($items as $item) {
            $this->line("Importing item {$item->id}: {$item->filename}");
            try {
                $this->processItem($batch, $item, $processor, $embeddingService, $noEmbed);
                $processed++;
            } catch (\Throwable $exception) {
                $item->forceFill([
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                    'finished_at' => now(),
                ])->save();
                $this->error('  failed: '.$exception->getMessage());
                $failed++;
            }
            $this->refreshBatchCounts($batch);
        }

        $batch->refresh();
        $batch->forceFill([
            'status' => $batch->failed_items > 0 ? 'completed_with_errors' : 'completed',
            'finished_at' => $batch->pending_items === 0 && $batch->processing_items === 0 ? now() : null,
        ])->save();

        $this->info("Done. processed={$processed}, failed={$failed}");
        $this->showBatchTable($batch->refresh());

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function processItem(
        KnowledgeImportBatch $batch,
        KnowledgeImportItem $item,
        DocumentIngestionProcessor $processor,
        DocumentEmbeddingService $embeddingService,
        bool $noEmbed
    ): void {
        if (! is_file($item->source_path)) {
            throw new \RuntimeException('Source file not found: '.$item->source_path);
        }

        DB::transaction(function () use ($batch, $item): void {
            $item->forceFill([
                'status' => 'processing',
                'started_at' => now(),
                'finished_at' => null,
                'error_message' => null,
            ])->save();
        });

        $document = KnowledgeDocument::query()->updateOrCreate(
            [
                'knowledge_base_id' => $batch->knowledge_base_id,
                'title' => $item->title,
            ],
            [
                'source_type' => 'policy',
                'version' => '1.0',
                'status' => 'draft',
                'created_by' => 1,
                'metadata' => [
                    'import_batch_id' => $batch->id,
                    'import_item_id' => $item->id,
                    'original_path' => $item->source_path,
                    'original_name' => $item->filename,
                    'law_id' => $this->lawIdFromFilename($item->filename),
                    'imported_at' => now()->toISOString(),
                ],
            ]
        );

        $storedPath = 'knowledge-documents/'.$document->id.'/'.Str::uuid().'_'.$item->filename;
        Storage::disk('local')->put($storedPath, file_get_contents($item->source_path));
        $absolutePath = Storage::disk('local')->path($storedPath);

        $documentFile = DocumentFile::query()->create([
            'knowledge_document_id' => $document->id,
            'original_name' => $item->filename,
            'disk' => 'local',
            'path' => $storedPath,
            'mime_type' => $this->mimeType($item->source_path),
            'size' => filesize($item->source_path),
            'sha256' => hash_file('sha256', $absolutePath),
            'status' => 'uploaded',
            'uploaded_by' => 1,
        ]);

        $job = DocumentIngestionJob::query()->create([
            'knowledge_document_id' => $document->id,
            'document_file_id' => $documentFile->id,
            'job_type' => 'file_parse',
            'status' => 'pending',
            'progress' => 0,
            'created_by' => 1,
            'metadata' => [
                'auto_process' => true,
                'batch_import' => true,
                'import_batch_id' => $batch->id,
                'import_item_id' => $item->id,
                'original_name' => $item->filename,
            ],
        ]);

        $processedJob = $processor->process($job);
        if ($processedJob->status !== 'completed') {
            throw new \RuntimeException($processedJob->error_message ?: 'Parse failed.');
        }

        $chunkCount = $document->chunks()->count();
        $embeddedCount = 0;
        if (! $noEmbed) {
            $embedding = $embeddingService->embedDocumentChunks($document->id);
            $embeddedCount = (int) ($embedding['embedded_count'] ?? 0);
        }

        $item->forceFill([
            'knowledge_document_id' => $document->id,
            'document_file_id' => $documentFile->id,
            'status' => 'completed',
            'chunk_count' => $chunkCount,
            'embedded_count' => $embeddedCount,
            'error_message' => null,
            'finished_at' => now(),
        ])->save();

        $this->info("  ok: chunks={$chunkCount}, embedded={$embeddedCount}");
    }

    private function showStatus(): int
    {
        $batch = $this->findBatch();
        if (! $batch) {
            return self::FAILURE;
        }

        $this->refreshBatchCounts($batch);
        $this->showBatchTable($batch->refresh());

        $failedItems = $batch->items()->where('status', 'failed')->latest('id')->limit(10)->get();
        if ($failedItems->isNotEmpty()) {
            $this->line('');
            $this->warn('Latest failed items');
            $this->table(
                ['ID', 'Filename', 'Error'],
                $failedItems->map(fn ($item) => [$item->id, $item->filename, mb_substr((string) $item->error_message, 0, 160)])->all()
            );
        }

        return self::SUCCESS;
    }

    private function resetFailed(): int
    {
        $batch = $this->findBatch();
        if (! $batch) {
            return self::FAILURE;
        }

        $count = $batch->items()->where('status', 'failed')->update([
            'status' => 'pending',
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
            'updated_at' => now(),
        ]);

        $this->refreshBatchCounts($batch);
        $this->info("Reset failed items: {$count}");
        $this->showBatchTable($batch->refresh());

        return self::SUCCESS;
    }

    private function invalidAction(): int
    {
        $this->error('Invalid action. Use create|run|retry|status|reset-failed.');
        return self::FAILURE;
    }

    private function findBatch(): ?KnowledgeImportBatch
    {
        $value = $this->stringOption('batch');
        if ($value === '') {
            $this->error('Please provide --batch ID or name.');
            return null;
        }

        $query = KnowledgeImportBatch::query();
        $batch = ctype_digit($value)
            ? $query->where('id', (int) $value)->first()
            : $query->where('name', $value)->first();

        if (! $batch) {
            $this->error('Import batch not found: '.$value);
            return null;
        }

        return $batch;
    }

    private function refreshBatchCounts(KnowledgeImportBatch $batch): void
    {
        $counts = $batch->items()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $batch->forceFill([
            'total_items' => (int) $counts->sum(),
            'pending_items' => (int) ($counts['pending'] ?? 0),
            'processing_items' => (int) ($counts['processing'] ?? 0),
            'completed_items' => (int) ($counts['completed'] ?? 0),
            'failed_items' => (int) ($counts['failed'] ?? 0),
        ])->save();
    }

    private function showBatchTable(KnowledgeImportBatch $batch): void
    {
        $this->table(
            ['ID', 'Name', 'Status', 'Total', 'Pending', 'Processing', 'Completed', 'Failed'],
            [[
                $batch->id,
                $batch->name,
                $batch->status,
                $batch->total_items,
                $batch->pending_items,
                $batch->processing_items,
                $batch->completed_items,
                $batch->failed_items,
            ]]
        );
    }

    private function resolveOrCreateBase(string $base): KnowledgeBase
    {
        if (ctype_digit($base)) {
            return KnowledgeBase::query()->findOrFail((int) $base);
        }

        return KnowledgeBase::query()->firstOrCreate(
            ['name' => $base],
            [
                'description' => '本地批量导入的法律法规与行政法规文件',
                'industry' => '法律法规',
                'status' => 'active',
                'created_by' => 1,
            ]
        );
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

    private function titleFromFilename(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = preg_replace('/^\d+[_-]+/u', '', $name) ?: $name;
        return trim($name);
    }

    private function lawIdFromFilename(string $filename): ?string
    {
        return preg_match('/^(\d+)[_-]/u', $filename, $matches) ? $matches[1] : null;
    }

    private function mimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'md' => 'text/markdown',
            default => mime_content_type($path) ?: 'application/octet-stream',
        };
    }
}
