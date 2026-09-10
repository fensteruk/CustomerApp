<?php

namespace App\Http\Requests\Office;

use App\Models\CustomerOrganisation;
use Illuminate\Foundation\Http\FormRequest;

class RenameCustomerOrganisationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customerOrganisation');

        return $customer instanceof CustomerOrganisation
            && ($this->user()?->can('update', $customer) ?? false);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
