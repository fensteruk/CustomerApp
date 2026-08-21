<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RespondToAlternativeCallOffDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSiteRole() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_response' => ['required', 'string', 'max:2000'],
        ];
    }
}
