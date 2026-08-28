<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
