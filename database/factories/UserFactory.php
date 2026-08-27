<?php

namespace Database\Factories;

use App\Enums\PortalRoleIdentifier;
use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_organisation_id' => CustomerOrganisation::factory(),
            'portal_role_id' => PortalRole::query()->where('identifier', PortalRoleIdentifier::SiteManager->value)->value('id'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'is_preview_user' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function role(PortalRoleIdentifier $role): static
    {
        return $this->state(function (array $attributes) use ($role): array {
            $state = [
                'portal_role_id' => PortalRole::query()->where('identifier', $role->value)->value('id'),
            ];

            if ($role === PortalRoleIdentifier::FensterOfficeStaff) {
                $state['customer_organisation_id'] = null;
            }

            return $state;
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function preview(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_preview_user' => true,
        ]);
    }
}
