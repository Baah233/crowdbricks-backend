<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvestmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // require authenticated user (controller route is protected by auth:sanctum)
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'project_id' => 'required|exists:projects,id',
            'amount' => 'required|numeric|min:1',
            'currency' => 'nullable|string|max:10',
            'payment_method' => 'required|string|in:momo,bank,card',
            'metadata' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'project_id.required' => 'Project is required.',
            'amount.required' => 'Amount is required.',
            'payment_method.required' => 'Please select a payment method.',
        ];
    }
}