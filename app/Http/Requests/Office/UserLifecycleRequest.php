<?php

namespace App\Http\Requests\Office;

use App\Policies\OfficeAdministrationPolicy;
use Illuminate\Foundation\Http\FormRequest;

class UserLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->routeIs('portal.office.users.reactivate') ? 'user_reactivate' : 'user_deactivate';

        return app(OfficeAdministrationPolicy::class)->allows($this->user(), $ability);
    }

    public function rules(): array
    {
        return ['lock_version' => ['required', 'integer', 'min:1']];
    }
}
