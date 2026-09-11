<?php

namespace App\Http\Requests\Office;

use App\Models\CustomerOrganisation;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerOrganisationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CustomerOrganisation::class) ?? false;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }
}
