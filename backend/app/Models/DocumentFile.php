<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'knowledge_document_id',
        'original_name',
        'disk',
        'path',
        'mime_type',
        'size',
        'sha256',
        'status',
        'uploaded_by',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'knowledge_document_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
