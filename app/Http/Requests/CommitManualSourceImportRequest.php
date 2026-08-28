<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CommitManualSourceImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isFensterOfficeStaff() === true && ! $this->user()->is_preview_user;
    }

    public function rules(): array
    {
        return [
            'confirm' => ['required', 'accepted'],
            'content_sha256' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
        ];
    }
}
