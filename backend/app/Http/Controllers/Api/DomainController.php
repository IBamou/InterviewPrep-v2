<?php

namespace App\Http\Controllers\Api;

use App\Models\Domain;
use App\Services\DomainService;
use App\Http\Resources\DomainResource;
use App\Http\Requests\StoreDomainRequest;
use App\Http\Requests\UpdateDomainRequest;
use App\Http\Controllers\Controller;

class DomainController extends Controller
{
    public function __construct(
        private readonly DomainService $domainService
    ) {}

    public function index()
    {
        $domains = $this->domainService->list(
            auth()->id(),
            request()->integer('per_page', 15)
        );

        return DomainResource::collection($domains);
    }

    public function archives()
    {
        $domains = $this->domainService->listArchives(
            auth()->id(),
            request()->integer('per_page', 15)
        );

        return DomainResource::collection($domains);
    }

    public function store(StoreDomainRequest $request)
    {
        $domain = $this->domainService->create(
            auth()->id(),
            $request->validated()
        );

        return new DomainResource($domain);
    }

    public function show(Domain $domain)
    {
        $this->authorize('view', $domain);

        return new DomainResource($domain);
    }

    public function update(UpdateDomainRequest $request, Domain $domain)
    {
        $this->authorize('update', $domain);

        $domain = $this->domainService->update(
            $domain,
            $request->validated()
        );

        return new DomainResource($domain);
    }

    public function destroy(Domain $domain)
    {
        $this->authorize('delete', $domain);

        $this->domainService->delete($domain);

        return response()->json([
            'message' => 'Domain archived successfully.',
        ]);
    }

    public function restore(int $id)
    {
        $domain = $this->domainService->restore($id);

        $this->authorize('restore', $domain);

        return new DomainResource($domain);
    }

    public function forceDelete(int $id)
    {
        $domain = Domain::onlyTrashed()->findOrFail($id);

        $this->authorize('forceDelete', $domain);

        $this->domainService->forceDelete($id);

        return response()->json([
            'message' => 'Domain permanently deleted.',
        ]);
    }
}
