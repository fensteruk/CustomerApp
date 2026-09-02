<?php

namespace App\Http\Requests;

use App\Enums\SourceImportScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PreviewManualSourceImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isFensterOfficeStaff() === true && ! $this->user()->is_preview_user;
    }

    public function rules(): array
    {
        return [
            'workbook' => [
                'required',
                'file',
                'extensions:xlsx',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/octet-stream',
                'max:'.(int) config('manual_source_import.max_upload_kilobytes', 10240),
            ],
            'import_scope' => ['nullable', Rule::enum(SourceImportScope::class)],
            'complete_site_identifiers' => ['nullable', 'array', 'max:100'],
            'complete_site_identifiers.*' => ['required', 'string', 'max:255', 'distinct:strict'],
            'confirm_scope' => ['sometimes', 'accepted'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $scope = SourceImportScope::tryFrom((string) $this->input('import_scope'))
                ?? SourceImportScope::PartialFilteredExport;
            $siteKeys = collect($this->input('complete_site_identifiers', []))
                ->map(fn ($key): string => trim((string) $key))
                ->filter();

            if ($scope !== SourceImportScope::PartialFilteredExport && ! $this->boolean('confirm_scope')) {
                $validator->errors()->add('confirm_scope', 'A stronger complete-snapshot scope must be explicitly confirmed.');
            }
            if ($scope === SourceImportScope::SiteCompleteSnapshot && $siteKeys->isEmpty()) {
                $validator->errors()->add('complete_site_identifiers', 'Select at least one explicitly complete source site.');
            }
            if ($scope !== SourceImportScope::SiteCompleteSnapshot && $siteKeys->isNotEmpty()) {
                $validator->errors()->add('complete_site_identifiers', 'Complete source sites are accepted only for a site-complete snapshot.');
            }
        }];
    }
}
