<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'model_key',
        'task_type',
        'provider',
        'model_id',
        'local_path',
        'dimension',
        'device',
        'status',
        'is_active',
        'description',
        'download_command',
        'error_message',
        'metadata',
        'last_checked_at',
        'created_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(AiModelEvent::class);
    }
}
