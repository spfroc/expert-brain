<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentIngestionJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'knowledge_document_id' => $this->knowledge_document_id,
            'document_file_id' => $this->document_file_id,
            'job_type' => $this->job_type,
            'status' => $this->status,
            'progress' => $this->progress,
            'source_url' => $this->source_url,
            'error_message' => $this->error_message,
            'metadata' => $this->metadata,
            'started_at' => optional($this->started_at)->toISOString(),
            'finished_at' => optional($this->finished_at)->toISOString(),
            'created_by' => $this->created_by,
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
