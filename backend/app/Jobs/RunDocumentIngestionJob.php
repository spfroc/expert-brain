<?php

namespace App\Jobs;

use App\Models\DocumentIngestionJob;
use App\Services\DocumentIngestion\DocumentEmbeddingService;
use App\Services\DocumentIngestion\DocumentIngestionProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunDocumentIngestionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 900;

    public function __construct(public int $ingestionJobId, public bool $autoEmbed = true)
    {
        $this->onQueue('default');
    }

    public function handle(DocumentIngestionProcessor $processor, DocumentEmbeddingService $embeddingService): void
    {
        $ingestionJob = DocumentIngestionJob::query()->find($this->ingestionJobId);
        if (! $ingestionJob) {
            return;
        }

        $processedJob = $processor->process($ingestionJob);

        if ($this->autoEmbed && $processedJob->status === 'completed') {
            $embeddingResult = $embeddingService->embedDocumentChunks($processedJob->knowledge_document_id);
            $processedJob->forceFill([
                'metadata' => array_merge($processedJob->metadata ?? [], [
                    'embedding_result' => $embeddingResult,
                    'embedded_at' => now()->toISOString(),
                ]),
            ])->save();
        }
    }

    public function failed(Throwable $exception): void
    {
        $ingestionJob = DocumentIngestionJob::query()->find($this->ingestionJobId);
        if (! $ingestionJob) {
            return;
        }

        $ingestionJob->forceFill([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'finished_at' => now(),
        ])->save();
    }
}
