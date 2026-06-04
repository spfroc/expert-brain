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
        {--pattern=*.pdf : File pattern, for example *.pdf or *.docx}
        {--dry-run : Only print files, do not import}';

    protected $description = 'Import local policy/regulation files, parse them, create chunks, and embed them.';

    public function handle(DocumentIngestionProcessor $processor, DocumentEmbeddingService $embeddingService): int
    {
        $path = $this->stringOption('path');
        if ($path === '' || ! is_dir($path)) {
            $this->error('Please provide a valid --path directory.');
            return self::FAILURE;
        }

        $baseName = $this->stringOption('base', '国家行政法规库');
        $limit = max(0, (int) $this->stringOption('limit', '0'));
        $pattern = $this->stringOption('pattern', '*.pdf');
        $dryRun = (bool) $this->option('dry-run');

        $finder = Finder::create()
            ->files()
            ->in($path)
            ->name($pattern)
            ->sortByName();

        $files = iterator_to_array($finder, false);
        if ($limit > 0) {
            $files = array_slice($files, 0, $limit);
        }

        if ($files === []) {
            $this->warn('No files found.');
            return self::SUCCESS;
        }

        $this->info('Found '.count($files).' file(s).');

        if ($dryRun) {
            foreach ($files as $file) {
                $this->line($file->getRealPath());
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
        $failed = 0;

        foreach ($files as $file) {
            $realPath = $file->getRealPath();
            $originalName = $file->getFilename();
            $title = $this->titleFromFilename($originalName);

            $this->line("Importing: {$originalName}");

            try {
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

                $embedding = $embeddingService->embedDocumentChunks($document->id);
                $this->info("  ok: chunks={$embedding['embedded_count']}");
                $imported++;
            } catch (\Throwable $exception) {
                $this->error('  failed: '.$exception->getMessage());
                $failed++;
            }
        }

        $this->info("Done. imported={$imported}, failed={$failed}");

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
}
