<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'knowledge_base_id',
        'category_id',
        'title',
        'summary',
        'content',
        'source_type',
        'source_url',
        'file_path',
        'file_mime',
        'file_size',
        'version',
        'status',
        'effective_from',
        'effective_to',
        'metadata',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KnowledgeCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(KnowledgeTag::class, 'knowledge_document_tags', 'document_id', 'tag_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DocumentFile::class, 'knowledge_document_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class, 'knowledge_document_id');
    }

    protected function contentPreview(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->content ? mb_substr($this->content, 0, 200) : null);
    }
}
