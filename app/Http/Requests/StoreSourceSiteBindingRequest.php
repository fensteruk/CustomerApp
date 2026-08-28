<?php

namespace App\Http\Requests;

use App\Support\ManualSourceImport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSourceSiteBindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isFensterOfficeStaff() === true && ! $this->user()->is_preview_user;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source_namespace' => mb_strtolower(trim((string) $this->input('source_namespace'))),
            'source_site_key' => trim((string) $this->input('source_site_key')),
            'original_name' => trim((string) $this->input('original_name')),
            'display_name' => $this->filled('display_name') ? trim((string) $this->input('display_name')) : null,
            'portal_site_uuid' => trim((string) $this->input('portal_site_uuid')),
        ]);
    }

    public function rules(): array
    {
        return [
            'source_namespace' => ['required', 'string', Rule::in([ManualSourceImport::SOURCE_NAMESPACE])],
            'source_site_key' => ['required', 'string', 'max:255'],
            'original_name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'portal_site_uuid' => ['required', 'uuid', 'exists:sites,uuid'],
        ];
    }
}
