<?php

namespace App\Http\Requests;

use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;

class DashboardCallOffSelectionRequest extends FormRequest
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
        ];
    }
}
