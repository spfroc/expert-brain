<?php

namespace App\Services\Rag;

use App\Models\AiModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RagSearchService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, ?int $knowledgeBaseId = null, int $topK = 5): array
    {
        $startedAt = microtime(true);
        $expandedQuery = $this->expandQuery($query);

        $embeddingStartedAt = microtime(true);
        $embedding = $this->embedQuery($expandedQuery);
        $embeddingElapsedMs = $this->elapsedMs($embeddingStartedAt);

        $vector = $this->toPgVector($embedding);
        $candidateLimit = max($topK * 4, 20);
        $activeModel = $this->activeEmbeddingModel();

        if ($activeModel) {
            [$sql, $bindings] = $this->buildMultiModelSearchSql($vector, $activeModel->model_key, $knowledgeBaseId, $candidateLimit);
        } else {
            [$sql, $bindings] = $this->buildLegacySearchSql($vector, $knowledgeBaseId, $candidateLimit);
        }

        $terms = $this->extractTerms($expandedQuery);

        $dbStartedAt = microtime(true);
        $rows = DB::transaction(function () use ($sql, $bindings) {
            DB::statement('SET LOCAL statement_timeout = 15000');
            return DB::select($sql, $bindings);
        });
        $dbElapsedMs = $this->elapsedMs($dbStartedAt);

        $rerankStartedAt = microtime(true);
        $results = array_map(function ($row) use ($terms, $query, $expandedQuery, $activeModel) {
            $distance = (float) $row->distance;
            $vectorScore = 1 - $distance;
            $keywordScore = $this->keywordScore($row->content, $row->document_title, $terms);
            $sectionScore = $this->sectionScore($row->content, $query, $expandedQuery);
            $policyScore = $this->policyRiskScore($row->content, $query);
            $finalScore = ($vectorScore * 0.55) + ($keywordScore * 0.20) + ($sectionScore * 0.10) + ($policyScore * 0.15);

            return [
                'chunk_id' => $row->chunk_id,
                'knowledge_document_id' => $row->knowledge_document_id,
                'chunk_index' => $row->chunk_index,
                'content' => $row->content,
                'token_count' => $row->token_count,
                'metadata' => $row->metadata ? json_decode($row->metadata, true) : null,
                'document_title' => $row->document_title,
                'source_type' => $row->source_type,
                'source_url' => $row->source_url,
                'knowledge_base_id' => $row->knowledge_base_id,
                'distance' => $distance,
                'score' => $finalScore,
                'vector_score' => $vectorScore,
                'keyword_score' => $keywordScore,
                'section_score' => $sectionScore,
                'policy_score' => $policyScore,
                'model_key' => $row->model_key ?? $activeModel?->model_key ?? 'legacy',
            ];
        }, $rows);

        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);
        $results = array_slice($results, 0, max(1, min($topK, 20)));

        Log::info('RAG search timing', [
            'knowledge_base_id' => $knowledgeBaseId,
            'top_k' => $topK,
            'candidate_limit' => $candidateLimit,
            'active_model_key' => $activeModel?->model_key ?? 'legacy',
            'embedding_ms' => $embeddingElapsedMs,
            'db_ms' => $dbElapsedMs,
            'rerank_ms' => $this->elapsedMs($rerankStartedAt),
            'total_ms' => $this->elapsedMs($startedAt),
            'row_count' => count($rows),
            'result_count' => count($results),
        ]);

        return $results;
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @return array<string, mixed>|null
     */
    public function buildAnswerDraft(string $query, array $results): ?array
    {
        if ($results === []) {
            return null;
        }

        $bullets = [];
        $citations = [];

        foreach ($results as $result) {
            $article = $result['metadata']['article_no'] ?? null;
            $documentTitle = $result['document_title'] ?? '知识文档';
            $content = $this->normalizeWhitespace((string) ($result['content'] ?? ''));
            $point = $this->summarizePolicyPoint($content, $query);

            if ($point === null) {
                continue;
            }

            $citation = $article ? "{$documentTitle}{$article}" : $documentTitle;
            $bullets[] = $point.'（依据：'.$citation.'）';
            $citations[] = [
                'document_title' => $documentTitle,
                'article_no' => $article,
                'chunk_id' => $result['chunk_id'] ?? null,
            ];

            if (count($bullets) >= 4) {
                break;
            }
        }

        if ($bullets === []) {
            return null;
        }

        return [
            'style' => 'extractive_policy_summary',
            'answer' => "建议重点注意：\n".implode("\n", array_map(fn ($line) => '1. '.$line, array_values($bullets))),
            'bullets' => $bullets,
            'citations' => $citations,
            'disclaimer' => '以上为基于已入库法规条文的检索式整理，不等同于正式法律意见。',
        ];
    }

    /**
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildMultiModelSearchSql(string $vector, string $modelKey, ?int $knowledgeBaseId, int $candidateLimit): array
    {
        $sql = <<<'SQL'
SELECT
    dc.id AS chunk_id,
    dc.knowledge_document_id,
    dc.chunk_index,
    dc.content,
    dc.token_count,
    dc.metadata,
    kd.title AS document_title,
    kd.source_type,
    kd.source_url,
    kd.knowledge_base_id,
    dce.model_key,
    (dce.embedding <=> ?::vector) AS distance
FROM document_chunk_embeddings dce
JOIN document_chunks dc ON dc.id = dce.document_chunk_id
JOIN knowledge_documents kd ON kd.id = dc.knowledge_document_id
WHERE dce.embedding IS NOT NULL AND dce.model_key = ?
SQL;

        $bindings = [$vector, $modelKey];

        if ($knowledgeBaseId !== null) {
            $sql .= ' AND kd.knowledge_base_id = ?';
            $bindings[] = $knowledgeBaseId;
        }

        $sql .= ' ORDER BY dce.embedding <=> ?::vector LIMIT ?';
        $bindings[] = $vector;
        $bindings[] = max(1, min($candidateLimit, 80));

        return [$sql, $bindings];
    }

    /**
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildLegacySearchSql(string $vector, ?int $knowledgeBaseId, int $candidateLimit): array
    {
        $sql = <<<'SQL'
SELECT
    dc.id AS chunk_id,
    dc.knowledge_document_id,
    dc.chunk_index,
    dc.content,
    dc.token_count,
    dc.metadata,
    kd.title AS document_title,
    kd.source_type,
    kd.source_url,
    kd.knowledge_base_id,
    'legacy' AS model_key,
    (dc.embedding <=> ?::vector) AS distance
FROM document_chunks dc
JOIN knowledge_documents kd ON kd.id = dc.knowledge_document_id
WHERE dc.embedding IS NOT NULL
SQL;

        $bindings = [$vector];

        if ($knowledgeBaseId !== null) {
            $sql .= ' AND kd.knowledge_base_id = ?';
            $bindings[] = $knowledgeBaseId;
        }

        $sql .= ' ORDER BY dc.embedding <=> ?::vector LIMIT ?';
        $bindings[] = $vector;
        $bindings[] = max(1, min($candidateLimit, 80));

        return [$sql, $bindings];
    }

    private function activeEmbeddingModel(): ?AiModel
    {
        return AiModel::query()
            ->where('task_type', 'embedding')
            ->where('is_active', true)
            ->first();
    }

    private function expandQuery(string $query): string
    {
        $map = [
            '路由' => ['route', 'routing', 'Route'],
            '路径参数' => ['route parameters', 'URI segments', 'parameters', '{id}', '{post}', '{comment}'],
            '参数' => ['parameters', 'route parameters', '{id}'],
            '示例' => ['example', 'for example', '```php', 'Route::get'],
            '控制器' => ['controller', 'controllers'],
            '中间件' => ['middleware'],
            '缓存' => ['cache'],
            '队列' => ['queue', 'queues', 'jobs'],
            '模型' => ['model', 'Eloquent'],
            '一对多' => ['one to many', 'hasMany'],
            '无线电' => ['无线电台', '无线电频率', '无线电发射设备', '电台执照', '有害干扰', '无线电管理机构'],
            '爱好者' => ['个人', '设置使用', '无线电台', '无线电设备'],
            '开发' => ['研制', '生产', '测试', '无线电发射设备'],
            '测试' => ['电波参数测试', '电子监测设备', '发射设备', '临时动用'],
            '触犯法律' => ['擅自设置', '使用无线电台', '干扰无线电业务', '罚则', '警告', '没收设备', '罚款'],
        ];

        $expanded = [$query];
        foreach ($map as $keyword => $terms) {
            if (mb_stripos($query, $keyword) !== false) {
                array_push($expanded, ...$terms);
            }
        }

        return implode(' ', array_values(array_unique($expanded)));
    }

    /**
     * @return array<int, string>
     */
    private function extractTerms(string $query): array
    {
        preg_match_all('/[a-zA-Z_:\{}]+|[\x{4e00}-\x{9fa5}]{2,}/u', mb_strtolower($query), $matches);

        return array_values(array_unique(array_filter($matches[0] ?? [], fn ($term) => mb_strlen($term) >= 2)));
    }

    /**
     * @param array<int, string> $terms
     */
    private function keywordScore(string $content, string $title, array $terms): float
    {
        if ($terms === []) {
            return 0.0;
        }

        $haystack = mb_strtolower($title.' '.$content);
        $matched = 0;
        $weighted = 0.0;

        foreach ($terms as $term) {
            if (str_contains($haystack, mb_strtolower($term))) {
                $matched++;
                $weighted += str_contains(mb_strtolower($title), mb_strtolower($term)) ? 1.5 : 1.0;
            }
        }

        return min(1.0, ($weighted / max(1, count($terms))) + ($matched >= 2 ? 0.15 : 0.0));
    }

    private function sectionScore(string $content, string $query, string $expandedQuery): float
    {
        $lower = mb_strtolower($content);
        $expanded = mb_strtolower($expandedQuery);

        $score = 0.0;
        if (str_contains($lower, '## route parameters') || str_contains($lower, '### required parameters')) {
            $score += 0.7;
        }
        if (str_contains($expanded, '路径参数') && (str_contains($lower, 'route parameters') || str_contains($lower, 'uri'))) {
            $score += 0.3;
        }
        if (str_contains($expanded, '示例') && str_contains($lower, 'route::get')) {
            $score += 0.2;
        }
        if (str_contains($expanded, '无线电') && str_contains($lower, '无线电')) {
            $score += 0.3;
        }
        if ((str_contains($expanded, '触犯法律') || str_contains($expanded, '罚则')) && (str_contains($lower, '罚款') || str_contains($lower, '没收') || str_contains($lower, '警告'))) {
            $score += 0.4;
        }

        return min(1.0, $score);
    }

    private function policyRiskScore(string $content, string $query): float
    {
        $score = 0.0;
        $lower = mb_strtolower($content);
        $queryLower = mb_strtolower($query);

        foreach (['未经', '不得', '擅自', '应当', '批准', '报告', '干扰', '罚款', '没收', '电台执照'] as $term) {
            if (str_contains($lower, $term)) {
                $score += 0.12;
            }
        }

        if (str_contains($queryLower, '开发') || str_contains($queryLower, '测试')) {
            foreach (['研制', '生产', '测试', '发射设备', '电波参数测试'] as $term) {
                if (str_contains($lower, $term)) {
                    $score += 0.15;
                }
            }
        }

        return min(1.0, $score);
    }

    private function summarizePolicyPoint(string $content, string $query): ?string
    {
        $text = $this->normalizeWhitespace($content);
        $queryLower = mb_strtolower($query);

        if (str_contains($text, '擅自设置') || str_contains($text, '使用无线电台')) {
            return '不要擅自设置、使用无线电台站；涉及电台站、频率、核定项目，应按规定办理批准或执照。';
        }
        if (str_contains($text, '发射设备') || str_contains($text, '研制') || str_contains($text, '生产') || str_contains($text, '进口')) {
            return '开发、研制、生产、进口或测试无线电发射设备时，要确认设备和测试行为符合无线电管理要求。';
        }
        if (str_contains($text, '有害干扰') || str_contains($text, '干扰无线电业务')) {
            return '测试和使用设备时要避免造成有害干扰；一旦发生干扰，应停止相关操作并配合处理。';
        }
        if (str_contains($text, '未经国家无线电管理机构批准') || str_contains($text, '电波参数测试')) {
            return '涉及电波参数测试、监测设备或特殊测试活动时，不要在未经批准的情况下开展。';
        }
        if (str_contains($text, '紧急情况') || str_contains($text, '及时向无线电管理机构报告')) {
            return '只有危及人民生命财产安全等紧急情况，才可临时动用未经批准设备，并应及时报告。';
        }
        if (str_contains($text, '罚款') || str_contains($text, '没收') || str_contains($text, '警告')) {
            return '违规可能面临警告、查封或没收设备、没收违法所得、罚款，严重时还可能吊销电台执照。';
        }

        if (str_contains($queryLower, '无线电')) {
            return mb_substr($text, 0, 90);
        }

        return null;
    }

    private function normalizeWhitespace(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    /**
     * @return array<int, float|int>
     */
    private function embedQuery(string $query): array
    {
        $timeout = max(5, (int) config('services.ai_service.embedding_timeout', 120));

        $response = Http::timeout($timeout)->post($this->aiServiceUrl('/embeddings/embed'), [
            'texts' => [$query],
            'normalize' => true,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('AI service embedding failed: HTTP '.$response->status().' '.mb_substr($response->body(), 0, 1000));
        }

        $embeddings = $response->json('embeddings');
        if (! is_array($embeddings) || ! isset($embeddings[0]) || ! is_array($embeddings[0])) {
            throw new RuntimeException('AI service returned invalid embedding payload.');
        }

        return $embeddings[0];
    }

    /**
     * @param array<int, float|int> $values
     */
    private function toPgVector(array $values): string
    {
        return '['.implode(',', array_map(
            fn ($value) => is_numeric($value) ? (string) ((float) $value) : '0',
            $values
        )).']';
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function aiServiceUrl(string $path): string
    {
        return rtrim(config('services.ai_service.url', 'http://ai-service:8000'), '/').$path;
    }
}
