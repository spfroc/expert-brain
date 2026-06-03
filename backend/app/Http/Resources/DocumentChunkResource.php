<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentChunkResource extends JsonResource
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
            'chunk_index' => $this->chunk_index,
            'chunk_type' => $this->chunk_type,
            'title' => $this->title,
            'content' => $this->content,
            'token_count' => $this->token_count,
            'metadata' => $this->metadata,
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
