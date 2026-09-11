<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProposeAlternativeCallOffDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isFensterOfficeStaff() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'proposed_date' => ['required', 'date'],
            'customer_response' => ['nullable', 'string', 'max:2000'],
            'internal_reason' => ['nullable', 'string', 'max:2000'],
            'negotiation_uuid' => ['nullable', 'uuid'],
            'early_date_acknowledgement' => ['nullable', 'boolean'],
        ];
    }
}
