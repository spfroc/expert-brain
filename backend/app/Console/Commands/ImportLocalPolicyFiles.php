<?php

namespace App\Console\Commands;

use App\Models\DocumentFile;
use App\Models\DocumentIngestionJob;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use App\Services\DocumentIngestion\DocumentEmbeddingService;
use App\Services\DocumentIngestion\DocumentIngestionProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class ImportLocalPolicyFiles extends Command
{
    protected $signature = 'knowledge:import-local-policy-files
        {--path= : Directory containing PDF/DOCX/TXT/MD files}
        {--base=国家行政法规库 : Knowledge base name}
        {--limit=0 : Max files to import, 0 means all}
        {--offset=0 : Number of matched files to skip before importing}
        {--pattern=*.pdf : File pattern, for example *.pdf or *.docx}
        {--force : Re-import documents that already have embedded chunks}
        {--no-embed : Parse and chunk only, do not generate embeddings}
        {--fail-log= : CSV file path for failed imports}
        {--dry-run : Only print files, do not import}';

    protected $description = 'Import local policy/regulation files, parse them, create chunks, and optionally embed them.';

    public function handle(DocumentIngestionProcessor $processor, DocumentEmbeddingService $embeddingService): int
    {
        $path = $this->stringOption('path');
        if ($path === '' || ! is_dir($path)) {
            $this->error('Please provide a valid --path directory.');
            return self::FAILURE;
        }

        $baseName = $this->stringOption('base', '国家行政法规库');
        $limit = max(0, (int) $this->stringOption('limit', '0'));
        $offset = max(0, (int) $this->stringOption('offset', '0'));
        $pattern = $this->stringOption('pattern', '*.pdf');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $noEmbed = (bool) $this->option('no-embed');
        $failLog = $this->stringOption('fail-log');

        $finder = Finder::create()
            ->files()
            ->in($path)
            ->name($pattern)
            ->sortByName();

        $allFiles = iterator_to_array($finder, false);
        $files = $offset > 0 ? array_slice($allFiles, $offset) : $allFiles;
        if ($limit > 0) {
            $files = array_slice($files, 0, $limit);
        }

        if ($files === []) {
            $this->warn('No files found.');
            return self::SUCCESS;
        }

        $this->info('Matched '.count($allFiles).' file(s).');
        $this->info('Selected '.count($files).' file(s). offset='.$offset.', limit='.$limit.', pattern='.$pattern);

        if ($dryRun) {
            foreach ($files as $index => $file) {
                $this->line(sprintf('%d %s', $offset + $index + 1, $file->getRealPath()));
            }
            return self::SUCCESS;
        }

        $base = KnowledgeBase::query()->firstOrCreate(
            ['name' => $baseName],
            [
                'description' => '本地批量导入的法律法规与行政法规文件',
                'industry' => '法律法规',
                'status' => 'active',
                'created_by' => 1,
            ]
        );

        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $failures = [];

        foreach ($files as $file) {
            $realPath = $file->getRealPath();
            $originalName = $file->getFilename();
            $title = $this->titleFromFilename($originalName);

            $this->line("Importing: {$originalName}");

            try {
                $existingDocument = KnowledgeDocument::query()
                    ->where('knowledge_base_id', $base->id)
                    ->where('title', $title)
                    ->first();

                if ($existingDocument && ! $force) {
                    $embeddedCount = $existingDocument->chunks()->whereNotNull('embedding')->count();
                    if ($embeddedCount > 0) {
                        $this->warn("  skipped: already embedded chunks={$embeddedCount}. Use --force to re-import.");
                        $skipped++;
                        continue;
                    }
                }

                $document = KnowledgeDocument::query()->updateOrCreate(
                    [
                        'knowledge_base_id' => $base->id,
                        'title' => $title,
                    ],
                    [
                        'source_type' => 'policy',
                        'version' => '1.0',
                        'status' => 'draft',
                        'created_by' => 1,
                        'metadata' => [
                            'importer' => 'knowledge:import-local-policy-files',
                            'original_path' => $realPath,
                            'original_name' => $originalName,
                            'imported_at' => now()->toISOString(),
                        ],
                    ]
                );

                $storedPath = 'knowledge-documents/'.$document->id.'/'.Str::uuid().'_'.$originalName;
                Storage::disk('local')->put($storedPath, file_get_contents($realPath));
                $absolutePath = Storage::disk('local')->path($storedPath);

                $documentFile = DocumentFile::query()->create([
                    'knowledge_document_id' => $document->id,
                    'original_name' => $originalName,
                    'disk' => 'local',
                    'path' => $storedPath,
                    'mime_type' => $this->mimeType($realPath),
                    'size' => filesize($realPath),
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
                        'original_name' => $originalName,
                    ],
                ]);

                $processedJob = $processor->process($job);
                if ($processedJob->status !== 'completed') {
                    throw new \RuntimeException($processedJob->error_message ?: 'Parse failed.');
                }

                if ($noEmbed) {
                    $chunkCount = $document->chunks()->count();
                    $this->info("  ok: chunks={$chunkCount}, embedding=skipped");
                } else {
                    $embedding = $embeddingService->embedDocumentChunks($document->id);
                    $this->info("  ok: chunks={$embedding['embedded_count']}");
                }

                $imported++;
            } catch (\Throwable $exception) {
                $message = $exception->getMessage();
                $this->error('  failed: '.$message);
                $failed++;
                $failures[] = [
                    'filename' => $originalName,
                    'title' => $title,
                    'path' => $realPath,
                    'error' => $message,
                ];
            }
        }

        if ($failLog !== '' && $failures !== []) {
            $this->writeFailureCsv($failLog, $failures);
            $this->warn('Failure log written to: '.$failLog);
        }

        $this->info("Done. imported={$imported}, skipped={$skipped}, failed={$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
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

    /**
     * @param array<int, array<string, string>> $failures
     */
    private function writeFailureCsv(string $path, array $failures): void
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Unable to write failure log: '.$path);
        }

        fputcsv($handle, ['filename', 'title', 'path', 'error']);
        foreach ($failures as $failure) {
            fputcsv($handle, [$failure['filename'], $failure['title'], $failure['path'], $failure['error']]);
        }
        fclose($handle);
    }
}
