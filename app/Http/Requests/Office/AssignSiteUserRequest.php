<?php

namespace App\Http\Requests\Office;

use App\Policies\OfficeAdministrationPolicy;
use Illuminate\Foundation\Http\FormRequest;

class AssignSiteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(OfficeAdministrationPolicy::class)->allows($this->user(), 'user_assign_sites');
    }

    public function rules(): array
    {
        return ['user_uuid' => ['required', 'uuid', 'exists:users,uuid']];
    }
}
