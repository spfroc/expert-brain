<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'knowledge_base_id' => $this->knowledge_base_id,
            'category_id' => $this->category_id,
            'title' => $this->title,
            'summary' => $this->summary,
            'content' => $this->content,
            'source_type' => $this->source_type,
            'source_url' => $this->source_url,
            'version' => $this->version,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'created_by' => $this->created_by,
            'published_at' => optional($this->published_at)->toISOString(),
            'tags' => KnowledgeTagResource::collection($this->whenLoaded('tags')),
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
