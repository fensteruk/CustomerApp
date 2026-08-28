<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSourceSiteBindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isFensterOfficeStaff() === true && ! $this->user()->is_preview_user;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'display_name' => $this->filled('display_name') ? trim((string) $this->input('display_name')) : null,
            'portal_site_uuid' => trim((string) $this->input('portal_site_uuid')),
        ]);
    }

    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:255'],
            'portal_site_uuid' => ['required', 'uuid', 'exists:sites,uuid'],
        ];
    }
}
