<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CallOffLifecycleSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operation' => ['required', 'string', Rule::in(['withdraw', 'trash', 'restore'])],
            'requests' => ['required', 'array', 'min:1'],
            'requests.*' => ['required', 'string', 'uuid', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'operation.in' => 'Choose a supported call-off action.',
            'requests.required' => 'Select at least one call-off request.',
            'requests.min' => 'Select at least one call-off request.',
            'requests.*.uuid' => 'A selected call-off request could not be recognised.',
            'requests.*.distinct' => 'Each call-off request may only be selected once.',
        ];
    }
}
