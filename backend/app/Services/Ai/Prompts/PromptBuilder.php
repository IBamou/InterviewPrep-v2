<?php

namespace App\Services\Ai\Prompts;

use App\Models\Concept;
use App\Models\Domain;
use App\Models\User;
use App\Services\Ai\DTO\Message;
use App\Services\Ai\Prompts\Concerns\HasJsonEnforcement;
use App\Services\Ai\Prompts\Concerns\HasPersonas;
use Illuminate\Support\Collection;

class PromptBuilder
{
    use HasJsonEnforcement, HasPersonas;

    public function generateQuestions(Concept $concept, ?User $user = null, array $existingQuestions = []): array
    {
        $tier = $this->resolveTier($concept);
        $domainName = $concept->relationLoaded('domain') ? $concept->domain?->name : null;

        $systemPrompt = $this->enforceJson(
            $this->interviewCoach() . "\n\n" . implode("\n", [
                "You will generate exactly 5 interview questions for the given concept.",
                '',
                'Question format variety:',
                '- Conceptual: Tests core understanding of what it is and why it matters',
                '- Comparative: Tests awareness of alternatives and trade-offs',
                '- Practical: Tests ability to apply knowledge to real scenarios',
                '- Scenario: Tests debugging or working through a problem',
                '- Depth: Tests knowledge of internals or edge cases',
                '',
                "Tier distribution for {$tier} level:",
                $this->tierDistribution($tier),
                '',
                'Each question must be self-contained and specific.',
                'No markdown, no formatting inside questions.',
                'Cover different aspects — do not repeat the same sub-topic.',
            ]),
            json_encode(['questions' => ['Question 1?', 'Question 2?', 'Question 3?', 'Question 4?', 'Question 5?']])
        );

        $userContext = $this->buildUserContext($user);
        $dedupSection = $this->buildDedupSection($existingQuestions);

        $parts = array_filter([
            trim($userContext),
            $domainName ? "Domain: {$domainName}" : null,
            "Concept: {$concept->title}",
            "Tier: {$tier}",
            $concept->explanation ? "Explanation:\n{$concept->explanation}" : 'Note: No explanation available. Generate questions based on the title and tier.',
            '',
            "Generate 5 interview questions at the {$tier} level.",
            $dedupSection ?: null,
        ]);

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => implode("\n\n", $parts)],
        ];
    }

    public function evaluateAnswers(Concept $concept, array $qaPairs): array
    {
        $systemPrompt = $this->enforceJson(
            $this->evaluationCoach() . "\n\n" . implode("\n", [
                'Rating scale (integer 0-5):',
                '- 0: No answer / blank',
                '- 1: Completely wrong or major misconceptions',
                '- 2: Partially correct, significant gaps',
                '- 3: Basic understanding, lacks depth',
                '- 4: Strong answer, good detail',
                '- 5: Expert-level, comprehensive, covers edge cases',
                '',
                'If the answer is empty or blank, give 0 with: "No answer provided. Study the model answer below."',
                '',
                'For each question-answer pair, provide: rating, brief feedback (1-2 sentences), and a concise model answer (2-3 sentences).',
            ]),
            json_encode([
                "evaluations" => [
                    ["question_index" => 0, "rating" => 4, "feedback" => "...", "model_answer" => "..."],
                ]
            ])
        );

        $domainName = $concept->relationLoaded('domain') ? $concept->domain?->name : null;
        $tier = $this->resolveTier($concept);

        $pairsText = '';
        foreach ($qaPairs as $i => $qa) {
            $n = $i + 1;
            $pairsText .= "Q{$n}: {$qa['question']}\nA{$n}: " . ($qa['answer'] ?? '') . "\n\n";
        }

        $userPrompt = implode("\n", array_filter([
            $domainName ? "Domain: {$domainName}" : null,
            "Concept: {$concept->title}",
            "Difficulty Level: {$tier}",
            '',
            $pairsText,
        ]));

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];
    }

    public function improveExplanation(Concept $concept): array
    {
        $systemPrompt = $this->enforceJson(
            $this->educationExpert() . "\n\nCover what it is and why it matters for interviews. Keep it tight — 2-3 short sentences max. No fluff, no long examples, no history.",
            json_encode(['improved_explanation' => 'Your improved explanation here'])
        );

        $domainName = $concept->relationLoaded('domain') ? $concept->domain?->name : 'General';

        $parts = [
            "Domain: {$domainName}",
            "Concept: {$concept->title}",
        ];

        if ($concept->explanation) {
            $parts[] = '';
            $parts[] = 'Current explanation:';
            $parts[] = $concept->explanation;
            $parts[] = '';
            $parts[] = 'Rewrite this as a concise, solid definition (2-3 short sentences max).';
        } else {
            $parts[] = '';
            $parts[] = "Generate a concise, solid definition (2-3 short sentences max) for this concept in the context of {$domainName}.";
        }

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => implode("\n", $parts)],
        ];
    }

    public function generateExplanation(string $title, string $domainName): array
    {
        $systemPrompt = $this->enforceJson(
            $this->educationExpert() . "\n\n" . implode("\n", [
                "First, check if '{$title}' is a valid technical term related to '{$domainName}'.",
                'Be lenient with typos — try to interpret what the user meant.',
                'Only reject truly gibberish or completely unrelated input.',
                '',
                'If valid, generate a concise definition (2-3 short sentences max).',
                'Cover what it is and why it matters for interviews.',
            ]),
            implode("\n", [
                '{"error": "invalid", "message": "..."}',
                'OR',
                '{"explanation": "Your explanation here"}',
            ])
        );

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => implode("\n", [
                "Domain: {$domainName}",
                "Concept: {$title}",
                '',
                'Generate a concise, solid definition (2-3 short sentences max).',
            ])],
        ];
    }

    public function verifyTitle(string $title, string $domainName): array
    {
        $systemPrompt = $this->enforceJson(
            $this->educationExpert() . "\n\nCheck if the given concept title is valid for the domain. Be lenient with typos.",
            implode("\n", [
                '{"valid": true}',
                'OR',
                '{"valid": false, "suggestion": "Corrected Title", "message": "Did you mean \'Corrected Title\'?"}',
                'OR',
                '{"valid": false, "message": "This doesn\'t appear to be a valid technical concept for this domain."}',
            ])
        );

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Domain: {$domainName}\nConcept title to verify: {$title}"],
        ];
    }

    public function improveDomainDescription(Domain $domain): array
    {
        $systemPrompt = $this->enforceJson(
            $this->educationExpert() . "\n\nRewrite or generate a solid, concise definition (1-2 sentences max). Focus on what the domain is and its core purpose. No fluff.",
            json_encode(['improved_description' => 'Your improved text here'])
        );

        $parts = ["Domain: {$domain->name}"];

        if ($domain->description) {
            $parts[] = '';
            $parts[] = 'Current description:';
            $parts[] = $domain->description;
            $parts[] = '';
            $parts[] = 'Rewrite this as a concise, solid definition (1-2 sentences max).';
        } else {
            $parts[] = '';
            $parts[] = 'Generate a concise, solid definition (1-2 sentences max) for this domain based on its name.';
        }

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => implode("\n", $parts)],
        ];
    }

    public function quiz(Domain $domain, Collection $concepts, int $questionCount, array $existingQuestions = []): array
    {
        $conceptList = '';
        foreach ($concepts as $i => $c) {
            $tier = $this->resolveTier($c);
            $conceptList .= ($i + 1) . ". {$c->title} [{$tier}]" . ($c->explanation ? " — {$c->explanation}" : '') . "\n";
        }

        $systemPrompt = $this->enforceJson(
            $this->interviewCoach() . "\n\n" . implode("\n", [
                "Generate {$questionCount} interview questions covering ALL the concepts listed.",
                'Mix questions across concepts — do not ask about the same concept twice in a row.',
                '',
                'CRITICAL: Do NOT repeat or rephrase any questions from the "Previously generated" list.',
                'Every question must be new and unique.',
            ]),
            json_encode(['questions' => [['question' => 'What is X?', 'concept' => 'Concept Name']]])
        );

        $domainContext = "Domain: {$domain->name}\n";
        if ($domain->description) {
            $domainContext .= "Domain Description: {$domain->description}\n";
        }

        $dedupSection = $this->buildDedupSection($existingQuestions);

        $userPrompt = implode("\n\n", array_filter([
            $domainContext,
            "Concepts to cover:\n{$conceptList}",
            "Generate {$questionCount} interview questions that test understanding within {$domain->name}. Mix evenly across concepts. Set the 'concept' field to the EXACT title from the list.",
            $dedupSection ?: null,
        ]));

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];
    }

    private function buildUserContext(?User $user): string
    {
        if (!$user) {
            return '';
        }

        $parts = [];

        $attributes = [
            'status' => 'User Status',
            'specialization' => 'Specialization',
            'experience_years' => 'Experience',
            'interview_goal' => 'Goal',
        ];

        foreach ($attributes as $field => $label) {
            if ($user->{$field} ?? null) {
                $value = $user->{$field};
                $parts[] = "{$label}: " . (enum_exists($value::class) ? $value->label() : $value);
            }
        }

        if (!empty($user->tech_stack) && is_array($user->tech_stack)) {
            $parts[] = 'Tech Stack: ' . implode(', ', $user->tech_stack);
        }

        if (empty($parts)) {
            return '';
        }

        return "User Profile:\n" . implode("\n", $parts) . "\n";
    }

    private function buildDedupSection(array $existingQuestions): string
    {
        if (empty($existingQuestions)) {
            return '';
        }

        $list = implode("\n", array_map(fn ($q) => "- {$q}", $existingQuestions));

        return "\n\nPreviously generated questions — DO NOT repeat or rephrase these:\n{$list}";
    }

    private function tierDistribution(string $tier): string
    {
        return match ($tier) {
            'senior' => implode("\n", [
                '- Heavy on depth, scenario, and practical questions',
                '- Minimal purely conceptual questions',
                '- May include system design or architectural thinking',
            ]),
            'mid' => implode("\n", [
                '- Balanced mix across all question categories',
                '- Emphasis on comparative and practical questions',
                '- Some depth and scenario questions',
            ]),
            default => implode("\n", [
                '- Mostly conceptual and practical questions',
                '- Few depth or comparative questions',
                '- Focus on core understanding and fundamentals',
            ]),
        };
    }

    private function resolveTier(Concept $concept): string
    {
        return 'junior';
    }
}
