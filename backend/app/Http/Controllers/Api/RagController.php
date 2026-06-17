<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\DocumentChunk;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use App\Services\Rag\RagSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class RagController extends Controller
{
    public function search(Request $request, RagSearchService $searchService): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:2000'],
            'knowledge_base_id' => ['nullable', 'integer', 'exists:knowledge_bases,id'],
            'top_k' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        try {
            $startedAt = microtime(true);
            $knowledgeBaseId = $validated['knowledge_base_id'] ?? null;
            $results = $searchService->search(
                $validated['query'],
                $knowledgeBaseId,
                $validated['top_k'] ?? 5,
            );
            $answerDraft = $searchService->buildAnswerDraft($validated['query'], $results);
            [$results, $evidenceResults] = $this->markEvidenceResults($results, $answerDraft);
            $retrievalDiagnostics = $results === []
                ? $this->buildNoResultDiagnostics($knowledgeBaseId)
                : $searchService->buildRetrievalDiagnostics($validated['query'], $results);

            return response()->json([
                'success' => true,
                'data' => [
                    'query' => $validated['query'],
                    'answer_draft' => $answerDraft,
                    'evidence_results' => $evidenceResults,
                    'results' => $results,
                    'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'diagnostics' => $retrievalDiagnostics,
                ],
                'message' => 'ok',
                'errors' => null,
            ]);
        } catch (Throwable $exception) {
            Log::error('RAG search failed', [
                'query' => $validated['query'],
                'knowledge_base_id' => $validated['knowledge_base_id'] ?? null,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'data' => [
                    'query' => $validated['query'],
                    'answer_draft' => null,
                    'evidence_results' => [],
                    'results' => [],
                    'diagnostics' => $this->buildNoResultDiagnostics($validated['knowledge_base_id'] ?? null),
                ],
                'message' => 'rag search failed',
                'errors' => [
                    'rag' => [$exception->getMessage()],
                ],
            ], 500);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @param array<string, mixed>|null $answerDraft
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function markEvidenceResults(array $results, ?array $answerDraft): array
    {
        $usedChunkIds = [];
        foreach (($answerDraft['citations'] ?? []) as $citation) {
            $chunkId = $citation['chunk_id'] ?? null;
            if ($chunkId !== null) {
                $usedChunkIds[(int) $chunkId] = true;
            }
        }

        $evidenceResults = [];
        foreach ($results as &$result) {
            $chunkId = (int) ($result['chunk_id'] ?? 0);
            $used = isset($usedChunkIds[$chunkId]);
            $result['used_in_answer'] = $used;

            if ($used) {
                $evidenceResults[] = $result;
            }
        }
        unset($result);

        return [$results, $evidenceResults];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildNoResultDiagnostics(?int $knowledgeBaseId): array
    {
        $activeModel = AiModel::query()
            ->where('task_type', 'embedding')
            ->where('is_active', true)
            ->first();

        $documentQuery = KnowledgeDocument::query();
        $chunkQuery = DocumentChunk::query();

        if ($knowledgeBaseId) {
            $documentQuery->where('knowledge_base_id', $knowledgeBaseId);
            $chunkQuery->whereHas('document', function ($query) use ($knowledgeBaseId): void {
                $query->where('knowledge_base_id', $knowledgeBaseId);
            });
        }

        $documents = (clone $documentQuery)->count();
        $chunks = (clone $chunkQuery)->count();
        $legacyEmbeddings = (clone $chunkQuery)->whereNotNull('embedding')->count();
        $activeModelEmbeddings = $activeModel
            ? (clone $chunkQuery)->whereHas('embeddings', function ($query) use ($activeModel): void {
                $query->where('model_key', $activeModel->model_key);
            })->count()
            : 0;

        $knowledgeBase = $knowledgeBaseId ? KnowledgeBase::query()->find($knowledgeBaseId) : null;
        $embeddingCount = $activeModel ? $activeModelEmbeddings : $legacyEmbeddings;

        if ($documents === 0) {
            $reason = $knowledgeBaseId ? '当前选择的知识库没有文档。' : '系统中还没有知识文档。';
            $nextAction = '先在知识中心创建文档、上传文件或导入链接。';
            $status = 'no_documents';
        } elseif ($chunks === 0) {
            $reason = '当前检索范围内有文档，但没有切片，因此无法召回内容。';
            $nextAction = '到知识中心对文档执行“生成切片”或“一键入库”。';
            $status = 'no_chunks';
        } elseif ($embeddingCount === 0) {
            $reason = $activeModel
                ? "当前检索范围内有 {$chunks} 个切片，但 active 模型 {$activeModel->model_key} 没有对应向量。"
                : "当前检索范围内有 {$chunks} 个切片，但没有可用向量。";
            $nextAction = '到知识中心执行“向量化”，或在模型管理中补齐当前模型向量。';
            $status = 'no_embeddings';
        } else {
            $reason = '当前检索范围内有切片和向量，但没有达到召回条件，可能是问题与知识库内容不匹配或相似度过低。';
            $nextAction = '换一种问法、扩大知识库范围，或检查召回分数阈值和切片质量。';
            $status = 'low_similarity';
        }

        return [
            'status' => $status,
            'reason' => $reason,
            'next_action' => $nextAction,
            'knowledge_base_id' => $knowledgeBaseId,
            'knowledge_base_name' => $knowledgeBase?->name,
            'active_embedding_model_key' => $activeModel?->model_key,
            'documents_count' => $documents,
            'chunks_count' => $chunks,
            'legacy_embeddings_count' => $legacyEmbeddings,
            'active_model_embeddings_count' => $activeModelEmbeddings,
            'effective_embeddings_count' => $embeddingCount,
        ];
    }
}
