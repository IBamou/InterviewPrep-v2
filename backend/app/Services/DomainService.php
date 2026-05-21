<?php

namespace App\Services;

use App\Models\Domain;
use Illuminate\Pagination\LengthAwarePaginator;

class DomainService
{
    public function list(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Domain::where('user_id', $userId)
            ->withCount('concepts')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function listArchives(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Domain::where('user_id', $userId)
            ->onlyTrashed()
            ->withCount('concepts')
            ->orderBy('deleted_at', 'desc')
            ->paginate($perPage);
    }

    public function create(int $userId, array $data): Domain
    {
        return Domain::create([
            'user_id' => $userId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);
    }

    public function update(Domain $domain, array $data): Domain
    {
        $domain->update($data);

        return $domain->fresh();
    }

    public function delete(Domain $domain): void
    {
        $domain->delete();
    }

    public function restore(int $id): Domain
    {
        $domain = Domain::onlyTrashed()->findOrFail($id);
        $domain->restore();

        return $domain;
    }

    public function forceDelete(int $id): void
    {
        $domain = Domain::onlyTrashed()->findOrFail($id);
        $domain->forceDelete();
    }
}
