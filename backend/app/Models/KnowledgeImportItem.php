<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeImportItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'knowledge_import_batch_id',
        'knowledge_document_id',
        'document_file_id',
        'source_path',
        'filename',
        'title',
        'sha256',
        'size',
        'status',
        'chunk_count',
        'embedded_count',
        'error_message',
        'metadata',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(KnowledgeImportBatch::class, 'knowledge_import_batch_id');
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
