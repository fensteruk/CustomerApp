<?php

namespace App\Http\Requests\Office;

use App\Policies\OfficeAdministrationPolicy;
use Illuminate\Foundation\Http\FormRequest;

class SiteLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->routeIs('portal.office.sites.deactivate')
            ? 'site_deactivate'
            : 'site_reactivate';

        return $this->user() !== null
            && app(OfficeAdministrationPolicy::class)->allows($this->user(), $ability);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
