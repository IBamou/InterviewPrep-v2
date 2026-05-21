<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PracticeSetResource;
use App\Models\Concept;
use App\Models\Domain;
use App\Services\PracticeService;

class PracticeController extends Controller
{
    public function __construct(
        private readonly PracticeService $practiceService,
    ) {
        $this->middleware('throttle:ai-actions')->only(['store', 'submit']);
    }

    public function index(Domain $domain, Concept $concept)
    {
        $this->authorize('view', $domain);
        $this->authorize('view', $concept);

        $history = $this->practiceService->history($concept, auth()->user());

        return response()->json([
            'data' => $history,
        ]);
    }

    public function store(Domain $domain, Concept $concept)
    {
        $this->authorize('view', $domain);
        $this->authorize('view', $concept);

        $count = min((int) request('count', 5), 10);

        $questions = $this->practiceService->generate($concept, auth()->user(), $count);

        return response()->json([
            'data' => PracticeSetResource::collection($questions),
            'meta' => [
                'set_number' => $questions->first()?->set_number,
                'total' => $questions->count(),
            ],
        ], 201);
    }

    public function show(Domain $domain, Concept $concept, int $setNumber)
    {
        $this->authorize('view', $domain);
        $this->authorize('view', $concept);

        $questions = \App\Models\GeneratedQuestion::where('concept_id', $concept->id)
            ->where('set_number', $setNumber)
            ->where('user_id', auth()->id())
            ->get();

        abort_if($questions->isEmpty(), 404, 'Practice set not found.');

        return response()->json([
            'data' => PracticeSetResource::collection($questions),
        ]);
    }

    public function submit(Domain $domain, Concept $concept, int $setNumber)
    {
        $this->authorize('view', $domain);
        $this->authorize('view', $concept);

        $data = request()->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer', 'exists:generated_questions,id'],
            'answers.*.answer' => ['present', 'string', 'nullable'],
        ]);

        $result = $this->practiceService->submit(
            $concept,
            auth()->user(),
            $setNumber,
            $data['answers'],
        );

        return response()->json([
            'data' => [
                'set_number' => $result['set_number'],
                'questions' => PracticeSetResource::collection($result['questions']),
                'stats' => $result['stats'],
            ],
        ]);
    }
}
