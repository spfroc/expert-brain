<?php

namespace App\Console\Commands;

use App\Models\AiModel;
use App\Models\KnowledgeBase;
use App\Services\AiModel\AiModelRegistryService;
use App\Services\AiModel\EmbeddingCoverageService;
use Illuminate\Console\Command;

class AiModelCommand extends Command
{
    protected $signature = 'ai-model
        {action : install-recommended|list|check|activate|download-command|coverage|missing-documents}
        {--model= : Model ID, model_key, or model name}
        {--task= : Filter by task type}
        {--base= : Knowledge base name or ID for coverage checks}
        {--limit=50 : Max missing documents to show}';

    protected $description = 'Manage AI model registry, activation, download commands, and embedding coverage.';

    public function handle(AiModelRegistryService $service, EmbeddingCoverageService $coverageService): int
    {
        return match ((string) $this->argument('action')) {
            'install-recommended' => $this->installRecommended($service),
            'list' => $this->listModels(),
            'check' => $this->checkModel($service),
            'activate' => $this->activateModel($service, $coverageService),
            'download-command' => $this->showDownloadCommand(),
            'coverage' => $this->showCoverage($coverageService),
            'missing-documents' => $this->showMissingDocuments($coverageService),
            default => $this->invalidAction(),
        };
    }

    private function installRecommended(AiModelRegistryService $service): int
    {
        $count = $service->installRecommended();
        $this->info("Recommended models installed or updated: {$count}");
        return self::SUCCESS;
    }

    private function listModels(): int
    {
        $query = AiModel::query()->orderBy('task_type')->orderBy('id');
        if ($task = $this->stringOption('task')) {
            $query->where('task_type', $task);
        }

        $models = $query->get();
        if ($models->isEmpty()) {
            $this->warn('No AI models registered. Run: php artisan ai-model install-recommended');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Active', 'Task', 'Status', 'Provider', 'Key', 'Model', 'Path', 'Dim'],
            $models->map(fn (AiModel $model) => [
                $model->id,
                $model->is_active ? 'yes' : 'no',
                $model->task_type,
                $model->status,
                $model->provider,
                $model->model_key,
                $model->model_id,
                $model->local_path,
                $model->dimension,
            ])->all()
        );

        return self::SUCCESS;
    }

    private function checkModel(AiModelRegistryService $service): int
    {
        $model = $this->findModel();
        if (! $model) {
            return self::FAILURE;
        }

        $result = $service->check($model);
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    private function activateModel(AiModelRegistryService $service, EmbeddingCoverageService $coverageService): int
    {
        $model = $this->findModel();
        if (! $model) {
            return self::FAILURE;
        }

        if ($model->task_type === 'embedding') {
            $baseId = $this->resolveBaseId($this->stringOption('base'));
            $coverage = $coverageService->knowledgeBaseCoverage($model, $baseId);
            $this->line(sprintf(
                'Target model coverage before activation: %s/%s chunks, missing=%s, rate=%s%%',
                $coverage['embedded_chunks'],
                $coverage['total_chunks'],
                $coverage['missing_chunks'],
                $coverage['coverage_rate']
            ));

            if ($coverage['total_chunks'] > 0 && $coverage['missing_chunks'] > 0) {
                $this->warn('Target embedding model does not cover all existing chunks. Search results may be incomplete until missing embeddings are generated.');
            }
        }

        $model = $service->activate($model);
        $this->info("Activated {$model->task_type} model: {$model->model_key}");

        if ($model->task_type === 'embedding') {
            $this->warn('Embedding runtime is still controlled by ai-service env. Restart ai-service with matching model config.');
            $this->line('Suggested env:');
            $this->line('EMBEDDING_PROVIDER='.$model->provider);
            $this->line('EMBEDDING_MODEL='.$model->model_id);
            if ($model->local_path) {
                $this->line('EMBEDDING_MODEL_PATH='.$model->local_path);
            }
            if ($model->dimension) {
                $this->line('EMBEDDING_DIMENSION='.$model->dimension);
            }
            if ($model->device) {
                $this->line('EMBEDDING_DEVICE='.$model->device);
            }
        }

        return self::SUCCESS;
    }

    private function showDownloadCommand(): int
    {
        $model = $this->findModel();
        if (! $model) {
            return self::FAILURE;
        }

        if (! $model->download_command) {
            $this->warn('No download command configured for this model.');
            return self::SUCCESS;
        }

        $this->line($model->download_command);
        return self::SUCCESS;
    }

    private function showCoverage(EmbeddingCoverageService $coverageService): int
    {
        $model = $this->findModel();
        if (! $model) {
            return self::FAILURE;
        }

        $baseId = $this->resolveBaseId($this->stringOption('base'));
        $coverage = $coverageService->knowledgeBaseCoverage($model, $baseId);

        $this->table(
            ['Model', 'Base', 'Total Chunks', 'Embedded', 'Missing', 'Coverage'],
            [[
                $coverage['model_key'],
                $coverage['knowledge_base_id'] ?? 'all',
                $coverage['total_chunks'],
                $coverage['embedded_chunks'],
                $coverage['missing_chunks'],
                $coverage['coverage_rate'].'%',
            ]]
        );

        return self::SUCCESS;
    }

    private function showMissingDocuments(EmbeddingCoverageService $coverageService): int
    {
        $model = $this->findModel();
        if (! $model) {
            return self::FAILURE;
        }

        $baseId = $this->resolveBaseId($this->stringOption('base'));
        $limit = max(1, min(500, (int) $this->stringOption('limit', '50')));
        $rows = $coverageService->documentCoverage($model, $baseId, $limit, missingOnly: true);

        if ($rows === []) {
            $this->info('No missing documents for this model.');
            return self::SUCCESS;
        }

        $this->table(
            ['Document ID', 'Base', 'Title', 'Chunks', 'Embedded', 'Missing', 'Coverage'],
            array_map(fn ($row) => [
                $row['knowledge_document_id'],
                $row['knowledge_base_id'],
                $row['title'],
                $row['total_chunks'],
                $row['embedded_chunks'],
                $row['missing_chunks'],
                $row['coverage_rate'].'%',
            ], $rows)
        );

        return self::SUCCESS;
    }

    private function invalidAction(): int
    {
        $this->error('Invalid action. Use install-recommended|list|check|activate|download-command|coverage|missing-documents.');
        return self::FAILURE;
    }

    private function findModel(): ?AiModel
    {
        $value = $this->stringOption('model');
        if ($value === '') {
            $this->error('Please provide --model.');
            return null;
        }

        $query = AiModel::query();
        $model = ctype_digit($value)
            ? $query->where('id', (int) $value)->first()
            : $query->where('model_key', $value)->orWhere('name', $value)->first();

        if (! $model) {
            $this->error('AI model not found: '.$value);
            return null;
        }

        return $model;
    }

    private function resolveBaseId(string $base): ?int
    {
        if ($base === '') {
            return null;
        }

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
