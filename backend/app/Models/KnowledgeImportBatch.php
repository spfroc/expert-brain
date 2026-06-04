<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeImportBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'knowledge_base_id',
        'source_path',
        'pattern',
        'status',
        'total_items',
        'pending_items',
        'processing_items',
        'completed_items',
        'failed_items',
        'metadata',
        'started_at',
        'finished_at',
        'created_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(KnowledgeImportItem::class);
    }
}
