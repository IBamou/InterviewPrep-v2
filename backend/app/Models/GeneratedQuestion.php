<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedQuestion extends Model
{
    protected $fillable = [
        'concept_id',
        'user_id',
        'question',
        'answer',
        'rating',
        'feedback',
        'model_answer',
        'set_number',
        'tier',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
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

    public function scopeWhereSet($query, int $setNumber)
    {
        return $query->where('set_number', $setNumber);
    }

    public function scopeLatestSet($query, int $conceptId)
    {
        $latest = static::where('concept_id', $conceptId)->max('set_number');

        return $query->where('concept_id', $conceptId)->where('set_number', $latest ?? 0);
    }
}
