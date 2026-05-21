<?php

namespace App\Services;

use App\Enums\ConceptStatus;
use App\Models\Concept;
use App\Models\GeneratedQuestion;
use Illuminate\Support\Collection;

class GamificationService
{
    public function processSet(Concept $concept, Collection $questions, bool $isFirstToday): array
    {
        $ratings = $questions->pluck('rating')->filter();
        $xpAwarded = $this->awardXP($concept, $ratings->toArray());

        $bonus = 0;
        $bonuses = [];

        if ($isFirstToday) {
            $bonus += config('gamification.bonus.first_practice_of_day');
            $bonuses[] = 'first_practice_of_day';
        }

        if ($ratings->count() === $questions->count() && $ratings->every(fn ($r) => $r === 5)) {
            $bonus += config('gamification.bonus.perfect_set');
            $bonuses[] = 'perfect_set';
        }

        if ($bonus > 0) {
            $concept->increment('xp', $bonus);
            $xpAwarded += $bonus;
        }

        $this->syncTierXP($concept, $ratings);

        $tierChanges = $this->checkTierUnlocks($concept);

        $mastery = $this->computeMastery($concept);
        $concept->update(['mastery_score' => $mastery]);

        $statusChange = $this->autoStatus($concept);

        return [
            'xp_awarded' => $xpAwarded,
            'total_xp' => $concept->fresh()->xp,
            'bonuses' => $bonuses,
            'tier_changes' => $tierChanges,
            'mastery_score' => $mastery,
            'status_change' => $statusChange,
        ];
    }

    public function awardXP(Concept $concept, array $ratings): int
    {
        $table = config('gamification.xp_per_rating');
        $total = 0;

        foreach ($ratings as $rating) {
            $total += $table[(int) $rating] ?? 0;
        }

        if ($total > 0) {
            $concept->increment('xp', $total);
        }

        return $total;
    }

    public function syncTierXP(Concept $concept, Collection $ratings): void
    {
        $tier = $this->activeTier($concept);
        $tierXP = $concept->tier_xp ?? [];
        $current = $tierXP[$tier] ?? 0;

        $setTotal = 0;
        $table = config('gamification.xp_per_rating');
        foreach ($ratings as $r) {
            $setTotal += $table[(int) $r] ?? 0;
        }

        $tierXP[$tier] = $current + $setTotal;

        $tierRatings = $concept->tier_ratings ?? [];
        $tr = $tierRatings[$tier] ?? ['sum' => 0, 'count' => 0];
        foreach ($ratings as $r) {
            $tr['sum'] += (int) $r;
            $tr['count']++;
        }
        $tierRatings[$tier] = $tr;

        $concept->update([
            'tier_xp' => $tierXP,
            'tier_ratings' => $tierRatings,
        ]);
    }

    public function checkTierUnlocks(Concept $concept): array
    {
        $unlocked = $concept->unlocked_tiers ?? ['junior'];
        $changes = [];
        $tiers = config('gamification.tiers');
        $xp = $concept->xp;
        $sessions = $concept->total_practice_sessions;
        $avg = $concept->average_rating;

        foreach ($tiers as $key => $cfg) {
            if (in_array($key, $unlocked)) {
                continue;
            }

            if ($xp >= $cfg['xp_threshold']
                && $sessions >= $cfg['minimum_sessions']
                && ($avg === null || $avg >= $cfg['minimum_avg_rating'])) {
                $unlocked[] = $key;
                $changes[] = $key;
            }
        }

        if (!empty($changes)) {
            $concept->update(['unlocked_tiers' => $unlocked]);
        }

        return $changes;
    }

    public function computeMastery(Concept $concept): ?float
    {
        $sets = GeneratedQuestion::where('concept_id', $concept->id)
            ->whereNotNull('rating')
            ->selectRaw('set_number, AVG(rating) as avg_rating, MAX(created_at) as last_practiced')
            ->groupBy('set_number')
            ->orderByDesc('last_practiced')
            ->get();

        if ($sets->isEmpty()) {
            return null;
        }

        $recentCount = config('gamification.mastery.recent_session_count');
        $recentWeight = config('gamification.mastery.recent_session_weight');
        $standardWeight = config('gamification.mastery.standard_weight');
        $oldWeight = config('gamification.mastery.old_weight');

        $weightedSum = 0;
        $totalWeight = 0;

        foreach ($sets as $i => $set) {
            $weight = match (true) {
                $i < $recentCount => $recentWeight,
                $i < $recentCount + 8 => $standardWeight,
                default => $oldWeight,
            };

            $weightedSum += (float) $set->avg_rating * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0
            ? round($weightedSum / $totalWeight, 2)
            : null;
    }

    public function autoStatus(Concept $concept): ?string
    {
        $current = $concept->status;
        $from = null;
        $to = null;

        if ($current === ConceptStatus::ToReview) {
            $cfg = config('gamification.auto_status.to_review.to_in_progress');
            if ($concept->total_practice_sessions >= $cfg['min_sessions']) {
                $from = $current->value;
                $to = ConceptStatus::InProgress->value;
                $concept->update(['status' => ConceptStatus::InProgress]);
            }
        }

        if ($current === ConceptStatus::InProgress) {
            $cfg = config('gamification.auto_status.in_progress.to_mastered');
            $tierXP = $concept->tier_xp ?? [];
            $juniorXP = $tierXP['junior'] ?? 0;

            if ($juniorXP >= $cfg['min_junior_xp']
                && $concept->average_rating !== null
                && $concept->average_rating >= $cfg['min_avg_rating']) {
                $from = $current->value;
                $to = ConceptStatus::Mastered->value;
                $concept->update(['status' => ConceptStatus::Mastered]);
            }
        }

        return $from && $to ? ['from' => $from, 'to' => $to] : null;
    }

    public function nextTier(Concept $concept): ?array
    {
        $unlocked = $concept->unlocked_tiers ?? ['junior'];
        $tiers = config('gamification.tiers');

        $nextKey = null;
        $found = false;
        foreach (array_keys($tiers) as $key) {
            if ($found) {
                $nextKey = $key;
                break;
            }
            if ($key === end($unlocked)) {
                $found = true;
            }
        }

        if (!$nextKey || !isset($tiers[$nextKey])) {
            return null;
        }

        $cfg = $tiers[$nextKey];
        $xp = $concept->xp;
        $xpNeeded = $cfg['xp_threshold'];
        $progress = $xpNeeded > 0 ? min(100, round(($xp / $xpNeeded) * 100)) : 0;

        return [
            'tier' => $nextKey,
            'xp_threshold' => $xpNeeded,
            'xp_current' => $xp,
            'progress_percent' => $progress,
        ];
    }

    public function activeTier(Concept $concept): string
    {
        $unlocked = $concept->unlocked_tiers ?? ['junior'];

        return end($unlocked);
    }
}
