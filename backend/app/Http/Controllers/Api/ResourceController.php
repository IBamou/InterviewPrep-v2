<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Concept;
use App\Models\Domain;
use App\Models\Resource;
use App\Services\ResourceService;
use Illuminate\Http\Request;

class ResourceController extends Controller
{
    public function __construct(
        private readonly ResourceService $resourceService,
    ) {}

    public function index(Domain $domain, Concept $concept, Request $request)
    {
        $this->authorize('view', $domain);
        $this->authorize('view', $concept);

        $resources = $this->resourceService->list($concept);

        return response()->json($resources->response()->getData(true));
    }

    public function store(Domain $domain, Concept $concept, Request $request)
    {
        $this->authorize('view', $domain);
        $this->authorize('update', $concept);

        $data = $request->validate([
            'type' => ['required', 'string', 'in:article,video,documentation,course,book,tool,other'],
            'title' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:2048'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $resource = $this->resourceService->create($concept, auth()->user(), $data);

        return response()->json([
            'data' => new \App\Http\Resources\ResourceResource($resource),
        ], 201);
    }

    public function destroy(Domain $domain, Concept $concept, Resource $resource)
    {
        $this->authorize('view', $domain);
        $this->authorize('update', $concept);

        abort_if($resource->concept_id !== $concept->id, 404);

        $this->resourceService->delete($resource);

        return response()->json(['message' => 'Resource deleted.']);
    }
}
