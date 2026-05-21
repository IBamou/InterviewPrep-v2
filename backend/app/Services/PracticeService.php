<?php

namespace App\Services;

use App\Models\Concept;
use App\Models\GeneratedQuestion;
use App\Models\User;
use App\Services\Ai\AiGateway;
use App\Services\Ai\Prompts\PromptBuilder;
use Illuminate\Support\Collection;

class PracticeService
{
    public function __construct(
        private readonly AiGateway $ai,
        private readonly PromptBuilder $prompts,
    ) {}

    public function generate(Concept $concept, User $user, int $count = 5): Collection
    {
        $setNumber = GeneratedQuestion::where('concept_id', $concept->id)->max('set_number') + 1;

        $existingQuestions = GeneratedQuestion::where('concept_id', $concept->id)
            ->latest()
            ->limit(15)
            ->pluck('question')
            ->toArray();

        $messages = $this->prompts->generateQuestions($concept, $user, $existingQuestions);

        $result = $this->ai->sendJson($messages, [
            'temperature' => 0.7,
        ]);

        $questions = $result['questions'] ?? [];

        if (empty($questions)) {
            throw new \RuntimeException('AI returned no questions.');
        }

        $questions = array_slice($questions, 0, $count);
        $now = now();

        $records = [];
        foreach ($questions as $q) {
            $records[] = [
                'concept_id' => $concept->id,
                'user_id' => $user->id,
                'question' => is_array($q) ? ($q['question'] ?? '') : $q,
                'set_number' => $setNumber,
                'tier' => 'junior',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        GeneratedQuestion::insert($records);

        return GeneratedQuestion::where('concept_id', $concept->id)
            ->where('set_number', $setNumber)
            ->get();
    }

    public function submit(Concept $concept, User $user, int $setNumber, array $answers): array
    {
        $questions = GeneratedQuestion::where('concept_id', $concept->id)
            ->where('set_number', $setNumber)
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('id');

        if ($questions->isEmpty()) {
            throw new \RuntimeException('Practice set not found.');
        }

        $qaPairs = [];
        foreach ($answers as $answer) {
            $questionId = $answer['question_id'] ?? null;
            $question = $questions->get($questionId);

            if (!$question) {
                continue;
            }

            $qaPairs[] = [
                'question_id' => $questionId,
                'question' => $question->question,
                'answer' => $answer['answer'] ?? '',
            ];
        }

        $messages = $this->prompts->evaluateAnswers($concept, $qaPairs);

        $result = $this->ai->sendJson($messages, [
            'temperature' => 0.3,
        ]);

        $evaluations = $result['evaluations'] ?? [];

        foreach ($evaluations as $eval) {
            $idx = $eval['question_index'] ?? null;

            if ($idx !== null && isset($qaPairs[$idx])) {
                GeneratedQuestion::where('id', $qaPairs[$idx]['question_id'])
                    ->update([
                        'answer' => $qaPairs[$idx]['answer'],
                        'rating' => $eval['rating'] ?? null,
                        'feedback' => $eval['feedback'] ?? null,
                        'model_answer' => $eval['model_answer'] ?? null,
                    ]);
            }
        }

        $updated = GeneratedQuestion::where('concept_id', $concept->id)
            ->where('set_number', $setNumber)
            ->where('user_id', $user->id)
            ->get();

        $ratings = $updated->pluck('rating')->filter();

        return [
            'set_number' => $setNumber,
            'questions' => $updated,
            'stats' => [
                'total' => $updated->count(),
                'evaluated' => $ratings->count(),
                'average_rating' => $ratings->isNotEmpty()
                    ? round($ratings->avg(), 2) : null,
            ],
        ];
    }

    public function history(Concept $concept, User $user): Collection
    {
        return GeneratedQuestion::where('concept_id', $concept->id)
            ->where('user_id', $user->id)
            ->selectRaw('set_number, tier, COUNT(*) as question_count, AVG(rating) as average_rating, MAX(created_at) as last_practiced')
            ->whereNotNull('rating')
            ->groupBy('set_number', 'tier')
            ->orderByDesc('set_number')
            ->get()
            ->map(fn ($set) => [
                'set_number' => (int) $set->set_number,
                'tier' => $set->tier,
                'question_count' => (int) $set->question_count,
                'average_rating' => $set->average_rating ? round((float) $set->average_rating, 2) : null,
                'last_practiced' => $set->last_practiced,
            ]);
    }

    public function latestSet(Concept $concept, User $user): ?int
    {
        return GeneratedQuestion::where('concept_id', $concept->id)
            ->where('user_id', $user->id)
            ->max('set_number');
    }
}
