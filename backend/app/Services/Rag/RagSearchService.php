<?php

namespace App\Services\Rag;

use App\Models\AiModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RagSearchService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, ?int $knowledgeBaseId = null, int $topK = 5): array
    {
        $expandedQuery = $this->expandQuery($query);
        $embedding = $this->embedQuery($expandedQuery);
        $vector = $this->toPgVector($embedding);
        $candidateLimit = max($topK * 8, 30);
        $activeModel = $this->activeEmbeddingModel();

        if ($activeModel) {
            [$sql, $bindings] = $this->buildMultiModelSearchSql($vector, $activeModel->model_key, $knowledgeBaseId, $candidateLimit);
        } else {
            [$sql, $bindings] = $this->buildLegacySearchSql($vector, $knowledgeBaseId, $candidateLimit);
        }

        $terms = $this->extractTerms($expandedQuery);
        $rows = DB::select($sql, $bindings);

        $results = array_map(function ($row) use ($terms, $query, $expandedQuery, $activeModel) {
            $distance = (float) $row->distance;
            $vectorScore = 1 - $distance;
            $keywordScore = $this->keywordScore($row->content, $row->document_title, $terms);
            $sectionScore = $this->sectionScore($row->content, $query, $expandedQuery);
            $finalScore = ($vectorScore * 0.65) + ($keywordScore * 0.25) + ($sectionScore * 0.10);

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
                'model_key' => $row->model_key ?? $activeModel?->model_key ?? 'legacy',
            ];
        }, $rows);

        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, max(1, min($topK, 20)));
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
        preg_match_all('/[a-zA-Z_:\\{}]+|[\x{4e00}-\x{9fa5}]{2,}/u', mb_strtolower($query), $matches);

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

        return min(1.0, $score);
    }

    /**
     * @return array<int, float|int>
     */
    private function embedQuery(string $query): array
    {
        $response = Http::timeout(60)->post($this->aiServiceUrl('/embeddings/embed'), [
            'texts' => [$query],
            'normalize' => true,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('AI service embedding failed: '.$response->body());
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

    private function aiServiceUrl(string $path): string
    {
        return rtrim(config('services.ai_service.url', 'http://ai-service:8000'), '/').$path;
    }
}
