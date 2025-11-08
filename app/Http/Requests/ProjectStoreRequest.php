<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProjectStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // developers must be authenticated
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $rules = [
            'title' => 'required|string|max:255',
            'short_description' => 'required|string|max:512',
            'description' => 'nullable|string',
            'minimum_investment' => 'required|numeric|min:0',
            'target_funding' => 'required|numeric|min:1',
            'expected_yield' => 'nullable|numeric|min:0',
            'timeline' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'categories' => 'nullable|json',
            'tags' => 'nullable|json',
            'milestones' => 'nullable|json',
            'images.*' => 'nullable|image|max:5120',
            'documents.*' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'submit_for_approval' => 'sometimes|boolean',
        ];

        return $rules;
    }

    public function prepareForValidation()
    {
        // ensure booleans are normalized
        if ($this->has('submit_for_approval')) {
            $this->merge(['submit_for_approval' => filter_var($this->input('submit_for_approval'), FILTER_VALIDATE_BOOLEAN)]);
        }
    }
}