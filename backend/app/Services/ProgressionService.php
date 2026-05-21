<?php

namespace App\Services;

use App\Models\Concept;
use App\Models\Domain;
use App\Models\GeneratedQuestion;
use App\Models\User;
use App\Services\GamificationService;
use Illuminate\Support\Facades\DB;

class ProgressionService
{
    public function __construct(
        private readonly GamificationService $gamification,
    ) {}

    public function computeStats(Concept $concept): array
    {
        $questions = GeneratedQuestion::where('concept_id', $concept->id)
            ->whereNotNull('rating')
            ->get();

        $ratings = $questions->pluck('rating');
        $count = $ratings->count();
        $avg = $count > 0 ? round($ratings->avg(), 2) : null;

        $latestPractice = $questions->max('created_at');

        $sets = $questions->groupBy('set_number')->count();

        $streak = $this->computeStreak($questions);

        $concept->update([
            'total_practice_sessions' => $sets,
            'average_rating' => $avg,
            'last_practiced_at' => $latestPractice,
            'practice_streak' => $streak,
        ]);

        $nextTier = $this->gamification->nextTier($concept);
        $activeTier = $this->gamification->activeTier($concept);

        return [
            'total_sessions' => $sets,
            'total_questions_answered' => $count,
            'average_rating' => $avg,
            'last_practiced_at' => $latestPractice,
            'streak' => $streak,
            'xp' => $concept->xp,
            'mastery_score' => $concept->mastery_score,
            'active_tier' => $activeTier,
            'unlocked_tiers' => $concept->unlocked_tiers,
            'next_tier' => $nextTier,
        ];
    }

    public function weakAreas(Domain $domain, User $user): array
    {
        $stats = Concept::where('domain_id', $domain->id)
            ->where('user_id', $user->id)
            ->leftJoin('generated_questions', function ($join) {
                $join->on('concepts.id', '=', 'generated_questions.concept_id')
                    ->whereNotNull('generated_questions.rating');
            })
            ->select([
                'concepts.id',
                'concepts.title',
                DB::raw('AVG(generated_questions.rating) as average_rating'),
                DB::raw('COUNT(generated_questions.id) as answered_count'),
            ])
            ->groupBy('concepts.id', 'concepts.title')
            ->get();

        $weak = [];

        foreach ($stats as $row) {
            if ((int) $row->answered_count === 0) {
                $weak[] = [
                    'concept_id' => $row->id,
                    'title' => $row->title,
                    'reason' => 'Not practiced yet',
                    'average_rating' => null,
                ];
            } elseif ((float) $row->average_rating < 3.0) {
                $avg = round((float) $row->average_rating, 2);
                $weak[] = [
                    'concept_id' => $row->id,
                    'title' => $row->title,
                    'reason' => $avg < 2.0 ? 'Needs significant improvement' : 'Below average',
                    'average_rating' => $avg,
                ];
            }
        }

        usort($weak, fn ($a, $b) => ($a['average_rating'] ?? 0) <=> ($b['average_rating'] ?? 0));

        return $weak;
    }

    public function readyForQuiz(Concept $concept): array
    {
        $hasExplanation = !empty($concept->explanation);
        $practiceCount = GeneratedQuestion::where('concept_id', $concept->id)
            ->whereNotNull('rating')
            ->count();
        $avgRating = GeneratedQuestion::where('concept_id', $concept->id)
            ->whereNotNull('rating')
            ->avg('rating');

        $ready = $hasExplanation && $practiceCount >= 5 && ($avgRating && $avgRating >= 2.5);

        return [
            'ready' => $ready,
            'requirements' => [
                'has_explanation' => $hasExplanation,
                'practiced_questions' => $practiceCount,
                'minimum_required' => 5,
                'average_rating' => $avgRating ? round($avgRating, 2) : null,
                'minimum_rating' => 2.5,
            ],
        ];
    }

    private function computeStreak($questions): array
    {
        if ($questions->isEmpty()) {
            return ['current' => 0, 'longest' => 0];
        }

        $dates = $questions->pluck('created_at')
            ->map(fn ($d) => $d->toDateString())
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        if (empty($dates)) {
            return ['current' => 0, 'longest' => 0];
        }

        $longest = 1;
        $current = 1;

        for ($i = 1; $i < count($dates); $i++) {
            $prev = new \DateTime($dates[$i - 1]);
            $curr = new \DateTime($dates[$i]);
            $diff = $prev->diff($curr)->days;

            if ($diff === 1) {
                $current++;
            } else {
                $longest = max($longest, $current);
                $current = 1;
            }
        }

        $longest = max($longest, $current);

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $lastDate = end($dates);

        if ($lastDate !== $today && $lastDate !== $yesterday) {
            $current = 0;
        }

        return ['current' => $current, 'longest' => $longest];
    }
}
