<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Concept;
use App\Models\Domain;
use App\Services\ExplanationService;
use Illuminate\Http\Request;

class ExplanationController extends Controller
{
    public function __construct(
        private readonly ExplanationService $explanationService,
    ) {
        $this->middleware('throttle:ai-actions')->only(['generate', 'improve']);
    }

    public function generate(Domain $domain, Concept $concept)
    {
        $this->authorize('view', $domain);
        $this->authorize('view', $concept);

        $result = $this->explanationService->generate($concept->title, $domain->name);

        return response()->json(['data' => $result]);
    }

    public function improve(Domain $domain, Concept $concept)
    {
        $this->authorize('view', $domain);
        $this->authorize('view', $concept);

        $result = $this->explanationService->improve($concept);

        return response()->json(['data' => $result]);
    }

    public function accept(Domain $domain, Concept $concept, Request $request)
    {
        $this->authorize('update', $domain);
        $this->authorize('update', $concept);

        $data = $request->validate([
            'explanation' => ['required', 'string', 'max:5000'],
        ]);

        $concept->update(['explanation' => $data['explanation']]);

        return response()->json([
            'data' => ['explanation' => $concept->explanation],
            'message' => 'Explanation updated.',
        ]);
    }
}
