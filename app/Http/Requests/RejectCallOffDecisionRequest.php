<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectCallOffDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isFensterOfficeStaff() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_response' => ['required', 'string', 'max:2000'],
            'internal_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_response.required' => 'Enter the customer-visible rejection response.',
            'customer_response.max' => 'Keep the customer response under 2,000 characters.',
            'internal_reason.max' => 'Keep the internal reason under 2,000 characters.',
        ];
    }
}
