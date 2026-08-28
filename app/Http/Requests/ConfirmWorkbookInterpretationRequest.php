<?php

namespace App\Http\Requests;

use App\Enums\WorkbookColumnRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmWorkbookInterpretationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasCompletePortalProfile()
            && $this->user()->isFensterOfficeStaff()
            && ! $this->user()->is_preview_user;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sheet' => ['required', 'string', 'max:255'],
            'header_row' => ['required', 'integer', 'min:1', 'max:1000'],
            'columns' => ['required', 'array', 'min:4'],
            'columns.*.source_index' => ['required', 'integer', 'min:1', 'distinct'],
            'columns.*.semantic_role' => ['required', Rule::enum(WorkbookColumnRole::class)],
            'columns.*.subtype' => ['nullable', 'string', 'max:64'],
            'confirm' => ['required', 'accepted'],
        ];
    }
}
