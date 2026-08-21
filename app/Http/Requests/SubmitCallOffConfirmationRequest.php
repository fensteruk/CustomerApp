<?php

namespace App\Http\Requests;

use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;

class SubmitCallOffConfirmationRequest extends FormRequest
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
            'confirmation_signature' => ['nullable', 'string', 'size:64'],
        ];
    }
}
