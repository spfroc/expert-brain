<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class KnowledgeTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'tag_type',
    ];

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(KnowledgeDocument::class, 'knowledge_document_tags', 'tag_id', 'document_id');
    }
}
