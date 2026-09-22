<?php

namespace App\Http\Requests;

use App\Enums\CallOffServiceType;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;

class BuildCallOffMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->attributes->get('activeSite');

        return $site instanceof Site && $this->user()?->can('submit-call-off', $site);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'plots' => ['required', 'array', 'min:1'],
            'plots.*' => ['required', 'string', 'uuid', 'distinct'],
            'service_dates' => ['required', 'array', 'min:1'],
            'service_dates.*' => ['required', 'date_format:Y-m-d'],
            'excluded' => ['nullable', 'array'],
            'excluded.*' => ['string', 'max:200'],
            'early_reasons' => ['nullable', 'array'],
            'early_reasons.*' => ['nullable', 'string', 'max:2000'],
            'cavity_early_reason' => ['nullable', 'string', 'max:2000'],
            'customer_response' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            foreach (array_keys($this->input('service_dates', [])) as $service) {
                if (! CallOffServiceType::tryFrom((string) $service)) {
                    $validator->errors()->add('service_dates', 'Choose one or more supported call-off services.');
                }
            }
        }];
    }
}
