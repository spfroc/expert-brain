<?php

namespace App\Jobs;

use App\Models\DocumentIngestionJob;
use App\Services\DocumentIngestion\DocumentEmbeddingService;
use App\Services\DocumentIngestion\DocumentIngestionProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessDocumentIngestionJob implements ShouldQueue
{
    use FoundationQueueable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(
        public int $documentIngestionJobId,
        public bool $embedAfterParse = false,
    ) {
        $this->onQueue('ingestion');
    }

    public function handle(
        DocumentIngestionProcessor $processor,
        DocumentEmbeddingService $embeddingService,
    ): void {
        $ingestionJob = DocumentIngestionJob::query()->findOrFail($this->documentIngestionJobId);
        $processedJob = $processor->process($ingestionJob);

        if ($this->embedAfterParse && $processedJob->status === 'completed') {
            $embeddingService->embedDocumentChunks($processedJob->knowledge_document_id);
        }
    }

    public function failed(Throwable $exception): void
    {
        DocumentIngestionJob::query()
            ->whereKey($this->documentIngestionJobId)
            ->update([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                'finished_at' => now(),
            ]);
    }
}
