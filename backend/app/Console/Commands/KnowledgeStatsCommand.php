<?php

namespace App\Console\Commands;

use App\Models\KnowledgeBase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class KnowledgeStatsCommand extends Command
{
    protected $signature = 'knowledge:stats {--base= : Knowledge base name or ID}';

    protected $description = 'Show document, chunk, and embedding statistics for knowledge bases.';

    public function handle(): int
    {
        $baseFilter = $this->option('base');

        $query = KnowledgeBase::query()->orderBy('id');
        if ($baseFilter !== null && $baseFilter !== false && $baseFilter !== '') {
            $base = is_array($baseFilter) ? reset($baseFilter) : $baseFilter;
            if (ctype_digit((string) $base)) {
                $query->where('id', (int) $base);
            } else {
                $query->where('name', (string) $base);
            }
        }

        $bases = $query->get();
        if ($bases->isEmpty()) {
            $this->warn('No knowledge bases found.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($bases as $base) {
            $stats = DB::table('knowledge_documents as kd')
                ->leftJoin('document_chunks as dc', 'dc.knowledge_document_id', '=', 'kd.id')
                ->where('kd.knowledge_base_id', $base->id)
                ->selectRaw('COUNT(DISTINCT kd.id) as documents')
                ->selectRaw('COUNT(dc.id) as chunks')
                ->selectRaw('COUNT(dc.embedding) as embedded_chunks')
                ->first();

            $documents = (int) ($stats->documents ?? 0);
            $chunks = (int) ($stats->chunks ?? 0);
            $embedded = (int) ($stats->embedded_chunks ?? 0);

            $rows[] = [
                'id' => $base->id,
                'name' => $base->name,
                'documents' => $documents,
                'chunks' => $chunks,
                'embedded' => $embedded,
                'missing' => max(0, $chunks - $embedded),
                'coverage' => $chunks > 0 ? round($embedded * 100 / $chunks, 2).'%' : '0%',
            ];
        }

        $this->table(['ID', 'Name', 'Documents', 'Chunks', 'Embedded', 'Missing', 'Coverage'], $rows);

        return self::SUCCESS;
    }
}
