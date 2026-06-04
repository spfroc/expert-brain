<?php

namespace App\Console\Commands;

use App\Models\KnowledgeBase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class KnowledgeDiagnoseCommand extends Command
{
    protected $signature = 'knowledge:diagnose {--base= : Knowledge base name or ID} {--limit=30 : Max rows per section}';

    protected $description = 'Diagnose knowledge base issues such as duplicate titles, empty documents, missing chunks, and missing embeddings.';

    public function handle(): int
    {
        $baseId = $this->resolveBaseId($this->option('base'));
        $limit = max(1, min(200, (int) $this->option('limit')));

        if ($this->option('base') && $baseId === null) {
            $this->error('Knowledge base not found.');
            return self::FAILURE;
        }

        $this->info('Knowledge diagnosis'.($baseId ? ' for base ID '.$baseId : ''));

        $this->showDuplicateTitles($baseId, $limit);
        $this->showEmptyDocuments($baseId, $limit);
        $this->showDocumentsWithoutChunks($baseId, $limit);
        $this->showDocumentsWithMissingEmbeddings($baseId, $limit);
        $this->showLowChunkDocuments($baseId, $limit);

        return self::SUCCESS;
    }

    private function showDuplicateTitles(?int $baseId, int $limit): void
    {
        $query = DB::table('knowledge_documents')
            ->select('knowledge_base_id', 'title')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('knowledge_base_id', 'title')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('count')
            ->limit($limit);

        if ($baseId !== null) {
            $query->where('knowledge_base_id', $baseId);
        }

        $rows = $query->get()->map(fn ($row) => [$row->knowledge_base_id, $row->title, $row->count])->all();
        $this->sectionTable('Duplicate titles', ['Base', 'Title', 'Count'], $rows);
    }

    private function showEmptyDocuments(?int $baseId, int $limit): void
    {
        $query = DB::table('knowledge_documents')
            ->select('id', 'knowledge_base_id', 'title')
            ->where(function ($query): void {
                $query->whereNull('content')->orWhereRaw("trim(coalesce(content, '')) = ''");
            })
            ->orderBy('id')
            ->limit($limit);

        if ($baseId !== null) {
            $query->where('knowledge_base_id', $baseId);
        }

        $rows = $query->get()->map(fn ($row) => [$row->id, $row->knowledge_base_id, $row->title])->all();
        $this->sectionTable('Empty documents', ['ID', 'Base', 'Title'], $rows);
    }

    private function showDocumentsWithoutChunks(?int $baseId, int $limit): void
    {
        $query = DB::table('knowledge_documents as kd')
            ->leftJoin('document_chunks as dc', 'dc.knowledge_document_id', '=', 'kd.id')
            ->select('kd.id', 'kd.knowledge_base_id', 'kd.title')
            ->groupBy('kd.id', 'kd.knowledge_base_id', 'kd.title')
            ->havingRaw('COUNT(dc.id) = 0')
            ->orderBy('kd.id')
            ->limit($limit);

        if ($baseId !== null) {
            $query->where('kd.knowledge_base_id', $baseId);
        }

        $rows = $query->get()->map(fn ($row) => [$row->id, $row->knowledge_base_id, $row->title])->all();
        $this->sectionTable('Documents without chunks', ['ID', 'Base', 'Title'], $rows);
    }

    private function showDocumentsWithMissingEmbeddings(?int $baseId, int $limit): void
    {
        $query = DB::table('knowledge_documents as kd')
            ->join('document_chunks as dc', 'dc.knowledge_document_id', '=', 'kd.id')
            ->select('kd.id', 'kd.knowledge_base_id', 'kd.title')
            ->selectRaw('COUNT(dc.id) as chunks')
            ->selectRaw('COUNT(dc.embedding) as embedded')
            ->groupBy('kd.id', 'kd.knowledge_base_id', 'kd.title')
            ->havingRaw('COUNT(dc.embedding) < COUNT(dc.id)')
            ->orderBy('kd.id')
            ->limit($limit);

        if ($baseId !== null) {
            $query->where('kd.knowledge_base_id', $baseId);
        }

        $rows = $query->get()->map(fn ($row) => [$row->id, $row->knowledge_base_id, $row->title, $row->chunks, $row->embedded])->all();
        $this->sectionTable('Documents with missing embeddings', ['ID', 'Base', 'Title', 'Chunks', 'Embedded'], $rows);
    }

    private function showLowChunkDocuments(?int $baseId, int $limit): void
    {
        $query = DB::table('knowledge_documents as kd')
            ->join('document_chunks as dc', 'dc.knowledge_document_id', '=', 'kd.id')
            ->select('kd.id', 'kd.knowledge_base_id', 'kd.title')
            ->selectRaw('COUNT(dc.id) as chunks')
            ->selectRaw('char_length(coalesce(kd.content, \''\')) as content_length')
            ->groupBy('kd.id', 'kd.knowledge_base_id', 'kd.title', 'kd.content')
            ->havingRaw('COUNT(dc.id) <= 1 AND char_length(coalesce(kd.content, \'\')) > 3000')
            ->orderByDesc('content_length')
            ->limit($limit);

        if ($baseId !== null) {
            $query->where('kd.knowledge_base_id', $baseId);
        }

        $rows = $query->get()->map(fn ($row) => [$row->id, $row->knowledge_base_id, $row->title, $row->chunks, $row->content_length])->all();
        $this->sectionTable('Suspicious low chunk documents', ['ID', 'Base', 'Title', 'Chunks', 'ContentLength'], $rows);
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     */
    private function sectionTable(string $title, array $headers, array $rows): void
    {
        $this->line('');
        $this->info($title);
        if ($rows === []) {
            $this->line('  OK');
            return;
        }
        $this->table($headers, $rows);
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
