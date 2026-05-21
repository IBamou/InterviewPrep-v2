<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->value ?? $this->type,
            'type_label' => $this->type?->label(),
            'title' => $this->title,
            'url' => $this->url,
            'description' => $this->description,
            'created_at' => $this->created_at,
        ];
    }
}
