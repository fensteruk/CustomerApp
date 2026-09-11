<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AgreeRequestedCallOffDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isFensterOfficeStaff() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'early_date_acknowledgement' => ['nullable', 'boolean'],
            'negotiation_uuid' => ['nullable', 'uuid'],
        ];
    }
}
