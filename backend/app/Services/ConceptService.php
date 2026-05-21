<?php

namespace App\Services;

use App\Models\Concept;
use App\Enums\ConceptStatus;
use Illuminate\Pagination\LengthAwarePaginator;

class ConceptService
{
    public function list(int $domainId, int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Concept::where('domain_id', $domainId)
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function listArchives(int $domainId, int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Concept::where('domain_id', $domainId)
            ->where('user_id', $userId)
            ->onlyTrashed()
            ->orderBy('deleted_at', 'desc')
            ->paginate($perPage);
    }

    public function create(int $domainId, int $userId, array $data): Concept
    {
        return Concept::create([
            'domain_id' => $domainId,
            'user_id' => $userId,
            'title' => $data['title'],
            'explanation' => $data['explanation'] ?? null,
            'status' => ConceptStatus::ToReview,
        ]);
    }

    public function update(Concept $concept, array $data): Concept
    {
        $concept->update($data);

        return $concept->fresh();
    }

    public function delete(Concept $concept): void
    {
        $concept->delete();
    }

    public function restore(int $id): Concept
    {
        $concept = Concept::onlyTrashed()->findOrFail($id);
        $concept->restore();

        return $concept;
    }

    public function forceDelete(int $id): void
    {
        $concept = Concept::onlyTrashed()->findOrFail($id);
        $concept->forceDelete();
    }
}
