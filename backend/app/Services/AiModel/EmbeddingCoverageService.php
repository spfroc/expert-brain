<?php

namespace App\Services\AiModel;

use App\Models\AiModel;
use Illuminate\Support\Facades\DB;

class EmbeddingCoverageService
{
    /**
     * @return array<string, mixed>
     */
    public function knowledgeBaseCoverage(AiModel $model, ?int $knowledgeBaseId = null): array
    {
        $query = DB::table('document_chunks as dc')
            ->join('knowledge_documents as kd', 'kd.id', '=', 'dc.knowledge_document_id')
            ->leftJoin('document_chunk_embeddings as dce', function ($join) use ($model): void {
                $join->on('dce.document_chunk_id', '=', 'dc.id')
                    ->where('dce.model_key', '=', $model->model_key)
                    ->whereNotNull('dce.embedding');
            });

        if ($knowledgeBaseId !== null) {
            $query->where('kd.knowledge_base_id', $knowledgeBaseId);
        }

        $row = $query
            ->selectRaw('COUNT(dc.id) as total_chunks')
            ->selectRaw('COUNT(dce.id) as embedded_chunks')
            ->first();

        $total = (int) ($row->total_chunks ?? 0);
        $embedded = (int) ($row->embedded_chunks ?? 0);

        return [
            'model_key' => $model->model_key,
            'knowledge_base_id' => $knowledgeBaseId,
            'total_chunks' => $total,
            'embedded_chunks' => $embedded,
            'missing_chunks' => max(0, $total - $embedded),
            'coverage_rate' => $total > 0 ? round($embedded * 100 / $total, 2) : 0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function documentCoverage(AiModel $model, ?int $knowledgeBaseId = null, int $limit = 50, bool $missingOnly = false): array
    {
        $query = DB::table('knowledge_documents as kd')
            ->leftJoin('document_chunks as dc', 'dc.knowledge_document_id', '=', 'kd.id')
            ->leftJoin('document_chunk_embeddings as dce', function ($join) use ($model): void {
                $join->on('dce.document_chunk_id', '=', 'dc.id')
                    ->where('dce.model_key', '=', $model->model_key)
                    ->whereNotNull('dce.embedding');
            })
            ->select('kd.id', 'kd.knowledge_base_id', 'kd.title')
            ->selectRaw('COUNT(dc.id) as total_chunks')
            ->selectRaw('COUNT(dce.id) as embedded_chunks')
            ->groupBy('kd.id', 'kd.knowledge_base_id', 'kd.title')
            ->orderBy('kd.id')
            ->limit($limit);

        if ($knowledgeBaseId !== null) {
            $query->where('kd.knowledge_base_id', $knowledgeBaseId);
        }

        if ($missingOnly) {
            $query->havingRaw('COUNT(dce.id) < COUNT(dc.id)');
        }

        return $query->get()->map(function ($row): array {
            $total = (int) $row->total_chunks;
            $embedded = (int) $row->embedded_chunks;

            return [
                'knowledge_document_id' => (int) $row->id,
                'knowledge_base_id' => (int) $row->knowledge_base_id,
                'title' => $row->title,
                'total_chunks' => $total,
                'embedded_chunks' => $embedded,
                'missing_chunks' => max(0, $total - $embedded),
                'coverage_rate' => $total > 0 ? round($embedded * 100 / $total, 2) : 0,
            ];
        })->all();
    }
}
