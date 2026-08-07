<?php

namespace App\Http\Requests;

use App\Enums\CallOffServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NewCallOffRequest extends FormRequest
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
            'service_identifier' => ['required', 'string', Rule::enum(CallOffServiceType::class)],
            'requested_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'projected_plots' => ['required', 'array', 'min:1'],
            'projected_plots.*' => ['required', 'string', 'uuid', 'distinct'],
            'customer_response' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'service_identifier.required' => 'Choose the service you want to call off.',
            'service_identifier.enum' => 'Choose a supported call-off service.',
            'requested_date.required' => 'Choose the date you want to request.',
            'requested_date.date_format' => 'Enter the requested date in the expected format.',
            'requested_date.after_or_equal' => 'The requested date cannot be in the past.',
            'projected_plots.required' => 'Select at least one projected plot.',
            'projected_plots.min' => 'Select at least one projected plot.',
            'projected_plots.*.uuid' => 'A selected projected plot could not be recognised.',
            'projected_plots.*.distinct' => 'Each projected plot may only be selected once.',
            'customer_response.max' => 'Keep the message to Fenster under 2,000 characters.',
        ];
    }

    public function serviceType(): CallOffServiceType
    {
        return CallOffServiceType::from((string) $this->validated('service_identifier'));
    }
}
