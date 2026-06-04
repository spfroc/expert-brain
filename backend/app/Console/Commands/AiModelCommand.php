<?php

namespace App\Console\Commands;

use App\Models\AiModel;
use App\Services\AiModel\AiModelRegistryService;
use Illuminate\Console\Command;

class AiModelCommand extends Command
{
    protected $signature = 'ai-model
        {action : install-recommended|list|check|activate|download-command}
        {--model= : Model ID, model_key, or model name}
        {--task= : Filter by task type}';

    protected $description = 'Manage AI model registry, activation, and download commands.';

    public function handle(AiModelRegistryService $service): int
    {
        return match ((string) $this->argument('action')) {
            'install-recommended' => $this->installRecommended($service),
            'list' => $this->listModels(),
            'check' => $this->checkModel($service),
            'activate' => $this->activateModel($service),
            'download-command' => $this->showDownloadCommand(),
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

    private function activateModel(AiModelRegistryService $service): int
    {
        $model = $this->findModel();
        if (! $model) {
            return self::FAILURE;
        }

        $model = $service->activate($model);
        $this->info("Activated {$model->task_type} model: {$model->model_key}");

        if ($model->task_type === 'embedding') {
            $this->warn('Embedding runtime is still controlled by ai-service env. Restart ai-service with matching model config, then rebuild embeddings if dimension/model changed.');
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

    private function invalidAction(): int
    {
        $this->error('Invalid action. Use install-recommended|list|check|activate|download-command.');
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
