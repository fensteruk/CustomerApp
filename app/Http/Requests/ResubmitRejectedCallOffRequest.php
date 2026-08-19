<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResubmitRejectedCallOffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'requested_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'customer_response' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'requested_date.required' => 'Choose the new requested date.',
            'requested_date.date_format' => 'Enter the requested date in the expected format.',
            'requested_date.after_or_equal' => 'The requested date cannot be in the past.',
            'customer_response.max' => 'Keep the message to Fenster under 2,000 characters.',
        ];
    }
}
