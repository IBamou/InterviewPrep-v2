<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConceptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'min:3', 'max:255'],
            'explanation' => ['nullable', 'string', 'max:50000'],
            'status' => ['sometimes', 'string', 'in:to_review,in_progress,mastered'],
        ];
    }
}
