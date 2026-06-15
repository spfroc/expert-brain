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
        $queryType = $this->classifyQuery($query);
        $expandedQuery = $this->expandQuery($query, $queryType);

        $embeddingStartedAt = microtime(true);
        $embedding = $this->embedQuery($expandedQuery);
        $embeddingElapsedMs = $this->elapsedMs($embeddingStartedAt);

        $vector = $this->toPgVector($embedding);
        $candidateLimit = max($topK * 6, 30);
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
        $results = array_map(function ($row) use ($terms, $query, $expandedQuery, $activeModel, $queryType) {
            $distance = (float) $row->distance;
            $vectorScore = 1 - $distance;
            $keywordScore = $this->keywordScore($row->content, $row->document_title, $terms);
            $sectionScore = $this->sectionScore($row->content, $query, $expandedQuery, $queryType);
            $policyScore = $this->policyRiskScore($row->content, $query, $queryType);
            $intentScore = $this->intentScore($row->content, $row->document_title, $queryType);
            $finalScore = ($vectorScore * 0.45) + ($keywordScore * 0.25) + ($intentScore * 0.15) + ($sectionScore * 0.08) + ($policyScore * 0.07);

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
                'intent_score' => $intentScore,
                'query_type' => $queryType,
                'model_key' => $row->model_key ?? $activeModel?->model_key ?? 'legacy',
            ];
        }, $rows);

        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);
        $results = $this->deduplicateResults($results);
        $results = array_slice($results, 0, max(1, min($topK, 20)));

        Log::info('RAG search timing', [
            'knowledge_base_id' => $knowledgeBaseId,
            'query_type' => $queryType,
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

        $diagnostics = $this->buildRetrievalDiagnostics($query, $results);
        if (! $diagnostics['answerable']) {
            return [
                'style' => 'insufficient_evidence',
                'answer' => $diagnostics['reason'],
                'bullets' => [],
                'citations' => [],
                'disclaimer' => '当前回答被阻断，因为已召回证据不足或与问题主题不匹配。',
            ];
        }

        $bullets = [];
        $seenPoints = [];
        $citations = [];

        foreach ($results as $result) {
            $article = $result['metadata']['article_no'] ?? null;
            $documentTitle = $result['document_title'] ?? '知识文档';
            $content = $this->normalizeWhitespace((string) ($result['content'] ?? ''));
            $point = $this->summarizeEvidencePoint($content, $query, (string) ($diagnostics['query_type'] ?? 'general_policy'));

            if ($point === null || isset($seenPoints[$point])) {
                continue;
            }

            $seenPoints[$point] = true;
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
            return [
                'style' => 'insufficient_evidence',
                'answer' => '已检索到部分片段，但未能从片段中抽取出可支持回答的明确依据。建议换一种问法，或补充更匹配的知识文档后重试。',
                'bullets' => [],
                'citations' => [],
                'disclaimer' => '当前回答被阻断，因为没有足够明确的可引用依据。',
            ];
        }

        $numbered = [];
        foreach (array_values($bullets) as $index => $line) {
            $numbered[] = ($index + 1).'. '.$line;
        }

        return [
            'style' => 'extractive_policy_summary',
            'answer' => "根据当前知识库检索结果，建议重点注意：\n".implode("\n", $numbered),
            'bullets' => $bullets,
            'citations' => $citations,
            'disclaimer' => '以上为基于已入库知识片段的检索式整理，不等同于正式法律意见或最终业务结论。',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @return array<string, mixed>
     */
    public function buildRetrievalDiagnostics(string $query, array $results): array
    {
        $queryType = $this->classifyQuery($query);
        $top = $results[0] ?? null;
        $maxKeywordScore = $results ? max(array_map(fn ($item) => (float) ($item['keyword_score'] ?? 0), $results)) : 0.0;
        $maxIntentScore = $results ? max(array_map(fn ($item) => (float) ($item['intent_score'] ?? 0), $results)) : 0.0;
        $topScore = $top ? (float) ($top['score'] ?? 0) : 0.0;
        $topVectorScore = $top ? (float) ($top['vector_score'] ?? 0) : 0.0;
        $reasons = [];

        if ($results === []) {
            return [
                'status' => 'no_results',
                'query_type' => $queryType,
                'confidence' => 'none',
                'answerable' => false,
                'reason' => '当前知识库没有召回任何可用片段，无法回答该问题。',
                'reasons' => ['no retrieved chunks'],
                'next_action' => '检查知识库是否已有文档、切片和向量，或补充相关知识文档。',
            ];
        }

        if ($maxKeywordScore <= 0.0) {
            $reasons[] = '召回结果没有命中问题中的核心关键词。';
        }

        if ($topScore < 0.42) {
            $reasons[] = '最高综合分偏低。';
        }

        if ($queryType === 'criminal_law' && $maxIntentScore < 0.35) {
            $reasons[] = '问题属于刑事/量刑类，但召回结果没有命中刑法、贪污、受贿、判处、有期徒刑等直接依据。';
        }

        if ($queryType === 'criminal_law' && $maxKeywordScore <= 0.0) {
            $reason = '当前知识库中未检索到“贪污、受贿、刑法、判处、有期徒刑、无期徒刑”等直接依据。已召回内容与刑事量刑问题不匹配，不能支持可靠回答。建议导入《中华人民共和国刑法》及贪污贿赂犯罪相关司法解释后再查询。';

            return [
                'status' => 'off_topic_or_insufficient_evidence',
                'query_type' => $queryType,
                'confidence' => 'low',
                'answerable' => false,
                'reason' => $reason,
                'reasons' => $reasons,
                'next_action' => '补充刑事法律、职务犯罪、司法解释类知识库，或切换到包含刑法依据的知识库。',
                'top_score' => $topScore,
                'top_vector_score' => $topVectorScore,
                'max_keyword_score' => $maxKeywordScore,
                'max_intent_score' => $maxIntentScore,
            ];
        }

        if ($maxKeywordScore <= 0.0 && $topScore < 0.45) {
            return [
                'status' => 'low_confidence',
                'query_type' => $queryType,
                'confidence' => 'low',
                'answerable' => false,
                'reason' => '当前召回片段与问题的关键词和主题匹配度不足，无法基于知识库给出可靠回答。',
                'reasons' => $reasons,
                'next_action' => '换一种更贴近知识库原文的问法，或补充相关文档后重试。',
                'top_score' => $topScore,
                'top_vector_score' => $topVectorScore,
                'max_keyword_score' => $maxKeywordScore,
                'max_intent_score' => $maxIntentScore,
            ];
        }

        return [
            'status' => $reasons === [] ? 'ok' : 'weak_but_answerable',
            'query_type' => $queryType,
            'confidence' => $reasons === [] ? 'medium' : 'low',
            'answerable' => true,
            'reason' => $reasons === [] ? '召回结果具备可回答依据。' : '召回结果相关性偏弱，但仍有一定可引用依据。',
            'reasons' => $reasons,
            'next_action' => $reasons === [] ? null : '建议核对引用片段，必要时补充更直接的知识文档。',
            'top_score' => $topScore,
            'top_vector_score' => $topVectorScore,
            'max_keyword_score' => $maxKeywordScore,
            'max_intent_score' => $maxIntentScore,
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
        $bindings[] = max(1, min($candidateLimit, 100));

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
        $bindings[] = max(1, min($candidateLimit, 100));

        return [$sql, $bindings];
    }

    private function activeEmbeddingModel(): ?AiModel
    {
        return AiModel::query()
            ->where('task_type', 'embedding')
            ->where('is_active', true)
            ->first();
    }

    private function classifyQuery(string $query): string
    {
        $lower = mb_strtolower($query);

        foreach (['贪污', '受贿', '判刑', '判决', '量刑', '犯罪', '刑法', '有期徒刑', '无期徒刑', '死刑', '坐牢', '缓刑', '官员'] as $term) {
            if (str_contains($lower, $term)) {
                return 'criminal_law';
            }
        }

        foreach (['行政处罚', '许可', '监管', '罚款', '没收', '责令改正', '处分'] as $term) {
            if (str_contains($lower, $term)) {
                return 'administrative_policy';
            }
        }

        foreach (['政府采购', '采购人', '供应商', '投标', '招标', '质疑', '投诉', '中标'] as $term) {
            if (str_contains($lower, $term)) {
                return 'procurement';
            }
        }

        foreach (['无线电', '电台', '频率', '发射设备', '有害干扰'] as $term) {
            if (str_contains($lower, $term)) {
                return 'radio_policy';
            }
        }

        return 'general_policy';
    }

    private function expandQuery(string $query, ?string $queryType = null): string
    {
        $queryType ??= $this->classifyQuery($query);
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
        ];

        if ($queryType === 'radio_policy') {
            $map += [
                '无线电' => ['无线电台', '无线电频率', '无线电发射设备', '电台执照', '有害干扰', '无线电管理机构'],
                '爱好者' => ['个人', '设置使用', '无线电台', '无线电设备'],
                '开发' => ['研制', '生产', '测试', '无线电发射设备'],
                '测试' => ['电波参数测试', '电子监测设备', '发射设备', '临时动用'],
                '触犯法律' => ['擅自设置', '使用无线电台', '干扰无线电业务', '罚则', '警告', '没收设备', '罚款'],
            ];
        }

        if ($queryType === 'criminal_law') {
            $map += [
                '贪污' => ['贪污罪', '受贿', '刑法', '判处', '有期徒刑', '无期徒刑', '罚金', '没收财产', '数额特别巨大'],
                '判决' => ['量刑', '判处', '有期徒刑', '无期徒刑', '死刑', '缓刑'],
                '官员' => ['国家工作人员', '公职人员', '职务犯罪'],
            ];
        }

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
        preg_match_all('/[a-zA-Z_:\{}]+|[\x{4e00}-\x{9fa5}]{2,}|[0-9]+(?:万|亿|元)?/u', mb_strtolower($query), $matches);

        $terms = [];
        foreach ($matches[0] ?? [] as $term) {
            $term = trim($term);
            if (mb_strlen($term) >= 2) {
                $terms[] = $term;
            }
        }

        return array_values(array_unique($terms));
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
            $termLower = mb_strtolower($term);
            if (str_contains($haystack, $termLower)) {
                $matched++;
                $weighted += str_contains(mb_strtolower($title), $termLower) ? 1.5 : 1.0;
            }
        }

        return min(1.0, ($weighted / max(1, count($terms))) + ($matched >= 2 ? 0.15 : 0.0));
    }

    private function sectionScore(string $content, string $query, string $expandedQuery, string $queryType): float
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
        if ($queryType === 'radio_policy' && str_contains($lower, '无线电')) {
            $score += 0.3;
        }
        if ((str_contains($expanded, '触犯法律') || str_contains($expanded, '罚则')) && (str_contains($lower, '罚款') || str_contains($lower, '没收') || str_contains($lower, '警告'))) {
            $score += 0.3;
        }

        return min(1.0, $score);
    }

    private function policyRiskScore(string $content, string $query, string $queryType): float
    {
        $score = 0.0;
        $lower = mb_strtolower($content);
        $queryLower = mb_strtolower($query);

        foreach (['未经', '不得', '擅自', '应当', '批准', '报告', '罚款', '没收', '责令', '处分'] as $term) {
            if (str_contains($lower, $term)) {
                $score += 0.08;
            }
        }

        if ($queryType === 'radio_policy' && (str_contains($queryLower, '开发') || str_contains($queryLower, '测试'))) {
            foreach (['研制', '生产', '测试', '发射设备', '电波参数测试'] as $term) {
                if (str_contains($lower, $term)) {
                    $score += 0.15;
                }
            }
        }

        return min(1.0, $score);
    }

    private function intentScore(string $content, string $title, string $queryType): float
    {
        $haystack = mb_strtolower($title.' '.$content);
        $terms = match ($queryType) {
            'criminal_law' => ['刑法', '贪污', '受贿', '犯罪', '判处', '量刑', '有期徒刑', '无期徒刑', '死刑', '罚金', '数额特别巨大'],
            'administrative_policy' => ['行政处罚', '罚款', '没收', '责令改正', '处分', '警告', '许可'],
            'procurement' => ['政府采购', '采购人', '供应商', '投标', '招标', '中标', '质疑', '投诉'],
            'radio_policy' => ['无线电', '电台', '频率', '发射设备', '有害干扰', '电台执照'],
            default => [],
        };

        if ($terms === []) {
            return 0.0;
        }

        $matched = 0;
        foreach ($terms as $term) {
            if (str_contains($haystack, $term)) {
                $matched++;
            }
        }

        return min(1.0, $matched / 3);
    }

    private function summarizeEvidencePoint(string $content, string $query, string $queryType): ?string
    {
        $text = $this->normalizeWhitespace($content);

        if ($queryType === 'radio_policy') {
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
        }

        if ($queryType === 'criminal_law' && $this->intentScore($content, '', 'criminal_law') <= 0.0) {
            return null;
        }

        if (str_contains($text, '罚款') || str_contains($text, '没收') || str_contains($text, '警告') || str_contains($text, '处分')) {
            return mb_substr($text, 0, 140);
        }

        return mb_substr($text, 0, 140) ?: null;
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @return array<int, array<string, mixed>>
     */
    private function deduplicateResults(array $results): array
    {
        $seenContent = [];
        $seenArticle = [];
        $deduped = [];

        foreach ($results as $result) {
            $content = $this->normalizeWhitespace((string) ($result['content'] ?? ''));
            $contentKey = sha1($content);
            $articleKey = mb_strtolower((string) ($result['document_title'] ?? '')).'|'.($result['metadata']['article_no'] ?? '').'|'.mb_substr($content, 0, 80);

            if (isset($seenContent[$contentKey]) || isset($seenArticle[$articleKey])) {
                continue;
            }

            $seenContent[$contentKey] = true;
            $seenArticle[$articleKey] = true;
            $deduped[] = $result;
        }

        return $deduped;
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
