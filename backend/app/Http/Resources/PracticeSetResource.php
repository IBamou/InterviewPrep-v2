<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PracticeSetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'answer' => $this->when($this->answer !== null, $this->answer),
            'rating' => $this->when($this->rating !== null, (int) $this->rating),
            'feedback' => $this->when($this->feedback !== null, $this->feedback),
            'model_answer' => $this->when($this->model_answer !== null, $this->model_answer),
            'tier' => $this->tier,
            'set_number' => (int) $this->set_number,
        ];
    }
}
