<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $filesCount = (int) ($this->files_count ?? 0);
        $chunksCount = (int) ($this->chunks_count ?? 0);
        $legacyEmbeddingsCount = (int) ($this->legacy_embeddings_count ?? 0);
        $activeModelEmbeddingsCount = (int) ($this->active_model_embeddings_count ?? 0);
        $activeEmbeddingModelKey = $this->active_embedding_model_key ?? null;
        $latestJob = $this->whenLoaded('latestIngestionJob');
        $diagnostics = $this->buildSearchDiagnostics(
            $filesCount,
            $chunksCount,
            $legacyEmbeddingsCount,
            $activeModelEmbeddingsCount,
            $activeEmbeddingModelKey,
            is_object($latestJob) ? $latestJob?->status : null,
            is_object($latestJob) ? $latestJob?->error_message : null,
        );

        return [
            'id' => $this->id,
            'knowledge_base_id' => $this->knowledge_base_id,
            'category_id' => $this->category_id,
            'title' => $this->title,
            'summary' => $this->summary,
            'content' => $this->content,
            'source_type' => $this->source_type,
            'source_url' => $this->source_url,
            'version' => $this->version,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'created_by' => $this->created_by,
            'published_at' => optional($this->published_at)->toISOString(),
            'tags' => KnowledgeTagResource::collection($this->whenLoaded('tags')),
            'files_count' => $filesCount,
            'chunks_count' => $chunksCount,
            'legacy_embeddings_count' => $legacyEmbeddingsCount,
            'active_model_embeddings_count' => $activeModelEmbeddingsCount,
            'active_embedding_model_key' => $activeEmbeddingModelKey,
            'latest_job' => is_object($latestJob) ? [
                'id' => $latestJob->id,
                'job_type' => $latestJob->job_type,
                'status' => $latestJob->status,
                'progress' => $latestJob->progress,
                'error_message' => $latestJob->error_message,
                'created_at' => optional($latestJob->created_at)->toISOString(),
                'started_at' => optional($latestJob->started_at)->toISOString(),
                'finished_at' => optional($latestJob->finished_at)->toISOString(),
            ] : null,
            'search_status' => $diagnostics['status'],
            'search_status_label' => $diagnostics['label'],
            'search_status_type' => $diagnostics['type'],
            'next_action' => $diagnostics['next_action'],
            'diagnostic_message' => $diagnostics['message'],
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }

    /**
     * @return array{status:string,label:string,type:string,next_action:string,message:string}
     */
    private function buildSearchDiagnostics(
        int $filesCount,
        int $chunksCount,
        int $legacyEmbeddingsCount,
        int $activeModelEmbeddingsCount,
        ?string $activeEmbeddingModelKey,
        ?string $latestJobStatus,
        ?string $latestJobError,
    ): array {
        $hasContent = filled($this->content);
        $embeddingCount = $activeEmbeddingModelKey ? $activeModelEmbeddingsCount : $legacyEmbeddingsCount;

        if ($latestJobStatus === 'failed') {
            return [
                'status' => 'parse_failed',
                'label' => '任务失败',
                'type' => 'danger',
                'next_action' => '查看任务错误并重试',
                'message' => $latestJobError ?: '最近一次入库任务失败。',
            ];
        }

        if (! $hasContent && $filesCount === 0) {
            return [
                'status' => 'no_source',
                'label' => '无内容',
                'type' => 'info',
                'next_action' => '填写正文或上传文件',
                'message' => '该文档没有正文，也没有上传文件，无法生成切片。',
            ];
        }

        if ($chunksCount === 0) {
            return [
                'status' => 'not_chunked',
                'label' => '未切片',
                'type' => 'warning',
                'next_action' => '生成切片',
                'message' => '该文档已有来源内容，但还没有生成切片，暂时不能被检索。',
            ];
        }

        if ($embeddingCount === 0) {
            return [
                'status' => 'not_embedded',
                'label' => '未向量化',
                'type' => 'warning',
                'next_action' => '补齐向量',
                'message' => $activeEmbeddingModelKey
                    ? "当前模型 {$activeEmbeddingModelKey} 还没有该文档的向量。"
                    : '该文档已有切片，但还没有可用向量。',
            ];
        }

        if ($embeddingCount < $chunksCount) {
            return [
                'status' => 'partial_embedded',
                'label' => '部分向量',
                'type' => 'warning',
                'next_action' => '补齐向量',
                'message' => "该文档共有 {$chunksCount} 个切片，其中 {$embeddingCount} 个已有当前模型向量。",
            ];
        }

        return [
            'status' => 'searchable',
            'label' => '可检索',
            'type' => 'success',
            'next_action' => '可直接问答',
            'message' => '该文档已经完成切片和向量化，可以参与 RAG 检索。',
        ];
    }
}
