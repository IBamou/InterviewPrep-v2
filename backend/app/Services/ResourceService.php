<?php

namespace App\Services;

use App\Enums\ResourceType;
use App\Models\Concept;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ResourceService
{
    public function list(Concept $concept, ?ResourceType $type = null): AnonymousResourceCollection
    {
        $query = Resource::where('concept_id', $concept->id);

        if ($type) {
            $query->ofType($type);
        }

        return \App\Http\Resources\ResourceResource::collection($query->latest()->paginate(20));
    }

    public function create(Concept $concept, User $user, array $data): Resource
    {
        return Resource::create([
            'concept_id' => $concept->id,
            'user_id' => $user->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'url' => $data['url'] ?? null,
            'description' => $data['description'] ?? null,
        ]);
    }

    public function update(Resource $resource, array $data): Resource
    {
        $resource->update($data);

        return $resource->fresh();
    }

    public function delete(Resource $resource): void
    {
        $resource->delete();
    }
}
