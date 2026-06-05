<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunkEmbedding extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_chunk_id',
        'ai_model_id',
        'model_key',
        'provider',
        'model',
        'dimension',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(DocumentChunk::class, 'document_chunk_id');
    }

    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }
}
