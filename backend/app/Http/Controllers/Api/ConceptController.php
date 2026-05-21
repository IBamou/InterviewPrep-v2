<?php

namespace App\Http\Controllers\Api;

use App\Models\Concept;
use App\Models\Domain;
use App\Services\ConceptService;
use App\Http\Resources\ConceptResource;
use App\Http\Requests\StoreConceptRequest;
use App\Http\Requests\UpdateConceptRequest;
use App\Http\Controllers\Controller;

class ConceptController extends Controller
{
    public function __construct(
        private readonly ConceptService $conceptService
    ) {}

    public function index(Domain $domain)
    {
        $this->authorize('view', $domain);

        $concepts = $this->conceptService->list(
            $domain->id,
            auth()->id(),
            request()->integer('per_page', 15)
        );

        return ConceptResource::collection($concepts);
    }

    public function archives(Domain $domain)
    {
        $this->authorize('view', $domain);

        $concepts = $this->conceptService->listArchives(
            $domain->id,
            auth()->id(),
            request()->integer('per_page', 15)
        );

        return ConceptResource::collection($concepts);
    }

    public function store(StoreConceptRequest $request, Domain $domain)
    {
        $this->authorize('view', $domain);

        $concept = $this->conceptService->create(
            $domain->id,
            auth()->id(),
            $request->validated()
        );

        return new ConceptResource($concept);
    }

    public function show(Domain $domain, Concept $concept)
    {
        $this->authorize('view', $concept);

        return new ConceptResource($concept);
    }

    public function update(UpdateConceptRequest $request, Domain $domain, Concept $concept)
    {
        $this->authorize('update', $concept);

        $concept = $this->conceptService->update(
            $concept,
            $request->validated()
        );

        return new ConceptResource($concept);
    }

    public function destroy(Domain $domain, Concept $concept)
    {
        $this->authorize('delete', $concept);

        $this->conceptService->delete($concept);

        return response()->json([
            'message' => 'Concept archived successfully.',
        ]);
    }

    public function restore(int $id)
    {
        $concept = $this->conceptService->restore($id);

        $this->authorize('restore', $concept);

        return new ConceptResource($concept);
    }

    public function forceDelete(int $id)
    {
        $concept = Concept::onlyTrashed()->findOrFail($id);

        $this->authorize('forceDelete', $concept);

        $this->conceptService->forceDelete($id);

        return response()->json([
            'message' => 'Concept permanently deleted.',
        ]);
    }
}
