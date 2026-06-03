<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'knowledge_document_id',
        'document_file_id',
        'chunk_index',
        'chunk_type',
        'title',
        'content',
        'token_count',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'knowledge_document_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(DocumentFile::class, 'document_file_id');
    }
}
