<?php

namespace App\Services\AiModel;

use App\Models\AiModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AiModelRegistryService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function recommendedModels(): array
    {
        return [
            [
                'name' => 'BAAI bge-small-zh-v1.5',
                'model_key' => 'embedding-bge-small-zh-v1.5',
                'task_type' => 'embedding',
                'provider' => 'sentence-transformers',
                'model_id' => 'BAAI/bge-small-zh-v1.5',
                'local_path' => '/models/bge-small-zh-v1.5',
                'dimension' => 512,
                'device' => 'cpu',
                'status' => 'registered',
                'description' => '中文知识库 MVP 推荐 embedding 模型，体积小，适合 X230 / CPU 调试。',
                'download_command' => 'modelscope download --model BAAI/bge-small-zh-v1.5 --local_dir /models/bge-small-zh-v1.5',
                'metadata' => [
                    'recommended_for' => ['中文法规', '政府采购', '业务知识库'],
                    'notes' => '当前项目已验证该模型可返回 512 维向量。',
                ],
            ],
            [
                'name' => 'BAAI bge-m3',
                'model_key' => 'embedding-bge-m3',
                'task_type' => 'embedding',
                'provider' => 'sentence-transformers',
                'model_id' => 'BAAI/bge-m3',
                'local_path' => '/models/bge-m3',
                'dimension' => 1024,
                'device' => 'cpu',
                'status' => 'registered',
                'description' => '更强的通用 embedding 模型，适合后续提升检索质量。切换后必须重建向量。',
                'download_command' => 'modelscope download --model BAAI/bge-m3 --local_dir /models/bge-m3',
                'metadata' => [
                    'recommended_for' => ['中文法规', '多领域文档', '后续生产测试'],
                    'requires_reindex' => true,
                ],
            ],
            [
                'name' => 'BAAI bge-reranker-v2-m3',
                'model_key' => 'reranker-bge-v2-m3',
                'task_type' => 'reranker',
                'provider' => 'sentence-transformers',
                'model_id' => 'BAAI/bge-reranker-v2-m3',
                'local_path' => '/models/bge-reranker-v2-m3',
                'dimension' => null,
                'device' => 'cpu',
                'status' => 'registered',
                'description' => '重排模型。用于向量/BM25 召回后重排 topK，暂未接入推理链路。',
                'download_command' => 'modelscope download --model BAAI/bge-reranker-v2-m3 --local_dir /models/bge-reranker-v2-m3',
                'metadata' => [
                    'stage' => 'planned',
                ],
            ],
            [
                'name' => 'Ollama Qwen2.5 7B Instruct',
                'model_key' => 'llm-ollama-qwen2.5-7b-instruct',
                'task_type' => 'llm',
                'provider' => 'ollama',
                'model_id' => 'qwen2.5:7b-instruct',
                'local_path' => null,
                'dimension' => null,
                'device' => null,
                'status' => 'registered',
                'description' => '轻量中文问答生成模型候选。适合先接 RAG Answer API。',
                'download_command' => 'ollama pull qwen2.5:7b-instruct',
                'metadata' => [
                    'openai_compatible' => false,
                ],
            ],
        ];
    }

    public function installRecommended(): int
    {
        $count = 0;
        foreach ($this->recommendedModels() as $model) {
            AiModel::query()->updateOrCreate(
                ['model_key' => $model['model_key']],
                $model
            );
            $count++;
        }

        return $count;
    }

    public function activate(AiModel $model, ?int $userId = null): AiModel
    {
        DB::transaction(function () use ($model, $userId): void {
            AiModel::query()
                ->where('task_type', $model->task_type)
                ->where('id', '!=', $model->id)
                ->update(['is_active' => false]);

            $model->forceFill([
                'is_active' => true,
                'status' => $model->status === 'registered' ? 'ready' : $model->status,
            ])->save();

            $model->events()->create([
                'event_type' => 'activated',
                'message' => 'Model activated for task type: '.$model->task_type,
                'created_by' => $userId,
            ]);
        });

        return $model->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function check(AiModel $model, ?int $userId = null): array
    {
        $result = match ($model->task_type) {
            'embedding' => $this->checkEmbeddingModel($model),
            'llm' => $this->checkLlmModel($model),
            default => ['ok' => $model->local_path ? is_dir($model->local_path) : false, 'message' => 'Basic local path check only.'],
        };

        $model->forceFill([
            'status' => $result['ok'] ? 'ready' : 'failed',
            'error_message' => $result['ok'] ? null : ($result['message'] ?? 'Model check failed.'),
            'last_checked_at' => now(),
        ])->save();

        $model->events()->create([
            'event_type' => 'checked',
            'message' => $result['message'] ?? null,
            'metadata' => $result,
            'created_by' => $userId,
        ]);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function checkEmbeddingModel(AiModel $model): array
    {
        if ($model->local_path && ! is_dir($model->local_path)) {
            return ['ok' => false, 'message' => 'Local path does not exist: '.$model->local_path];
        }

        return [
            'ok' => true,
            'message' => 'Embedding model registry check passed. Restart ai-service with matching env to actually switch runtime model.',
            'provider' => $model->provider,
            'model_id' => $model->model_id,
            'local_path' => $model->local_path,
            'dimension' => $model->dimension,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkLlmModel(AiModel $model): array
    {
        if ($model->provider === 'ollama') {
            $baseUrl = rtrim((string) config('services.ollama.url', 'http://localhost:11434'), '/');
            try {
                $response = Http::timeout(10)->get($baseUrl.'/api/tags');
                if ($response->failed()) {
                    return ['ok' => false, 'message' => 'Ollama check failed: HTTP '.$response->status()];
                }

                $models = collect($response->json('models') ?? [])->pluck('name')->all();
                return [
                    'ok' => in_array($model->model_id, $models, true),
                    'message' => in_array($model->model_id, $models, true) ? 'Ollama model found.' : 'Ollama model not found. Run: '.$model->download_command,
                    'models' => $models,
                ];
            } catch (\Throwable $exception) {
                return ['ok' => false, 'message' => 'Ollama check exception: '.$exception->getMessage()];
            }
        }

        return ['ok' => true, 'message' => 'LLM model registered. Provider-specific runtime check not implemented yet.'];
    }
}
