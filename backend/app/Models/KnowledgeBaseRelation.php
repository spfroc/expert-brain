<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBaseRelation extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_knowledge_base_id',
        'related_knowledge_base_id',
        'relation_type',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function sourceKnowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class, 'source_knowledge_base_id');
    }

    public function relatedKnowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class, 'related_knowledge_base_id');
    }
}
