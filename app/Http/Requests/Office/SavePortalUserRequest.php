<?php

namespace App\Http\Requests\Office;

use App\Enums\PortalRoleIdentifier;
use App\Models\User;
use App\Policies\OfficeAdministrationPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SavePortalUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'email' => is_string($this->input('email')) ? strtolower(trim($this->input('email'))) : $this->input('email'),
        ]);
    }

    public function authorize(): bool
    {
        return app(OfficeAdministrationPolicy::class)->allows($this->user(), $this->route('user') ? 'user_update' : 'user_create');
    }

    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target instanceof User ? $target->id : null)],
            'role' => ['required', Rule::in(array_map(fn (PortalRoleIdentifier $role): string => $role->value, PortalRoleIdentifier::cases()))],
            'customer_organisation_id' => ['nullable', 'integer', 'exists:customer_organisations,id'],
            'site_ids' => ['nullable', 'array'],
            'site_ids.*' => ['integer', 'distinct', 'exists:sites,id'],
            'password' => [$target ? 'nullable' : 'required', 'confirmed', Password::min(12)->letters()->numbers()],
            'is_active' => [$target ? 'prohibited' : 'nullable', 'boolean'],
            'confirm_customer_change' => ['nullable', 'boolean'],
            'lock_version' => [$target ? 'required' : 'nullable', 'integer', 'min:1'],
        ];
    }
}
