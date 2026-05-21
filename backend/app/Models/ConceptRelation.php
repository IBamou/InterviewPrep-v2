<?php

namespace App\Models;

use App\Enums\RelationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConceptRelation extends Model
{
    protected $fillable = [
        'concept_id',
        'related_concept_id',
        'type',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'type' => RelationType::class,
        ];
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    public function relatedConcept(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'related_concept_id');
    }
}
