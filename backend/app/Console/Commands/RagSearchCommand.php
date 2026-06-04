<?php

namespace App\Console\Commands;

use App\Models\KnowledgeBase;
use App\Services\Rag\RagSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RagSearchCommand extends Command
{
    protected $signature = 'rag:search
        {query : Search question}
        {--base= : Knowledge base name or ID}
        {--top-k=5 : Number of results}
        {--answer : Print evidence-based answer draft before chunks}
        {--show-content=1 : Whether to print chunk content, 1 or 0}';

    protected $description = 'Run RAG retrieval from CLI and print matched chunks with scores.';

    public function handle(RagSearchService $searchService): int
    {
        $query = (string) $this->argument('query');
        $topK = max(1, min(20, (int) $this->option('top-k')));
        $baseId = $this->resolveBaseId($this->option('base'));
        $showContent = ((string) $this->option('show-content')) !== '0';
        $answer = (bool) $this->option('answer');

        $this->info('Query: '.$query);
        if ($baseId !== null) {
            $this->line('Knowledge base ID: '.$baseId);
        }

        $results = $searchService->search($query, $baseId, $topK);
        if ($results === []) {
            $this->warn('No results. Check whether documents have embedded chunks.');
            return self::SUCCESS;
        }

        if ($answer) {
            $this->line('');
            $this->info('Answer draft:');
            $this->line($this->buildAnswerDraft($query, $results));
        }

        foreach ($results as $index => $item) {
            $this->line('');
            $this->info(sprintf(
                '#%d chunk=%s doc=%s score=%.4f vector=%.4f keyword=%.4f section=%.4f distance=%.4f',
                $index + 1,
                $item['chunk_id'],
                $item['knowledge_document_id'],
                $item['score'],
                $item['vector_score'] ?? 0,
                $item['keyword_score'] ?? 0,
                $item['section_score'] ?? 0,
                $item['distance']
            ));
            $this->line('Document: '.$item['document_title']);
            if (! empty($item['source_url'])) {
                $this->line('Source: '.$item['source_url']);
            }

            if ($showContent) {
                $content = Str::limit(preg_replace('/\s+/u', ' ', $item['content']), 600);
                $this->line('Content: '.$content);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param array<int, array<string, mixed>> $results
     */
    private function buildAnswerDraft(string $query, array $results): string
    {
        $lines = [
            '问题：'.$query,
            '',
            '根据当前知识库召回内容，可先整理如下：',
        ];

        foreach (array_slice($results, 0, 3) as $index => $item) {
            $content = trim(preg_replace('/\s+/u', ' ', $item['content']));
            $lines[] = sprintf(
                '%d. %s（来源：《%s》，chunk #%s，score %.4f）',
                $index + 1,
                Str::limit($content, 260),
                $item['document_title'],
                $item['chunk_index'],
                $item['score']
            );
        }

        $lines[] = '';
        $lines[] = '说明：这是基于召回切片的回答草稿，不是大模型生成；正式回答应接入 LLM 后基于这些证据组织语言。';

        return implode(PHP_EOL, $lines);
    }

    private function resolveBaseId(mixed $value): ?int
    {
        if ($value === null || $value === false || $value === '') {
            return null;
        }

        if (is_array($value)) {
            $value = reset($value);
        }

        $base = (string) $value;
        if (ctype_digit($base)) {
            return (int) $base;
        }

        return KnowledgeBase::query()->where('name', $base)->value('id');
    }
}
