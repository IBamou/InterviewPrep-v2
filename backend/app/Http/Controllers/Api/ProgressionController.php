<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Concept;
use App\Models\Domain;
use App\Services\ProgressionService;

class ProgressionController extends Controller
{
    public function __construct(
        private readonly ProgressionService $progressionService,
    ) {}

    public function show(Domain $domain, Concept $concept)
    {
        $this->authorize('view', $domain);
        $this->authorize('view', $concept);

        $stats = $this->progressionService->computeStats($concept);
        $quizEligibility = $this->progressionService->readyForQuiz($concept);

        return response()->json([
            'data' => [
                'stats' => $stats,
                'quiz_eligibility' => $quizEligibility,
            ],
        ]);
    }

    public function weakAreas(Domain $domain)
    {
        $this->authorize('view', $domain);

        $weak = $this->progressionService->weakAreas($domain, auth()->user());

        return response()->json([
            'data' => $weak,
        ]);
    }
}
