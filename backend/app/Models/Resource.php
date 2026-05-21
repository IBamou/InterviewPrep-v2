<?php

namespace App\Models;

use App\Enums\ResourceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resource extends Model
{
    protected $fillable = [
        'concept_id',
        'user_id',
        'type',
        'title',
        'url',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'type' => ResourceType::class,
        ];
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOfType($query, ResourceType $type)
    {
        return $query->where('type', $type);
    }
}
