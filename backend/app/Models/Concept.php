<?php

namespace App\Models;

use App\Enums\ConceptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Concept extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'domain_id',
        'user_id',
        'title',
        'explanation',
        'status',
        'total_practice_sessions',
        'average_rating',
        'last_practiced_at',
        'practice_streak',
        'xp',
        'unlocked_tiers',
        'mastery_score',
        'tier_xp',
        'tier_ratings',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConceptStatus::class,
            'average_rating' => 'decimal:2',
            'last_practiced_at' => 'datetime',
            'practice_streak' => 'array',
            'xp' => 'integer',
            'unlocked_tiers' => 'array',
            'mastery_score' => 'decimal:2',
            'tier_xp' => 'array',
            'tier_ratings' => 'array',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function practiceQuestions(): HasMany
    {
        return $this->hasMany(GeneratedQuestion::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    public function relations(): HasMany
    {
        return $this->hasMany(ConceptRelation::class);
    }

    public function relatedToMe(): HasMany
    {
        return $this->hasMany(ConceptRelation::class, 'related_concept_id');
    }
}
