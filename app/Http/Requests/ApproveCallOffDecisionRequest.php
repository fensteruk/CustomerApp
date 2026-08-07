<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveCallOffDecisionRequest extends FormRequest
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
            'customer_response' => ['nullable', 'string', 'max:2000'],
            'internal_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_response.max' => 'Keep the customer response under 2,000 characters.',
            'internal_reason.max' => 'Keep the internal reason under 2,000 characters.',
        ];
    }
}
