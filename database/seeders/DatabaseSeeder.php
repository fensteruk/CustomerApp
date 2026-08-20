<?php

namespace Database\Seeders;

use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (PortalRoleIdentifier::cases() as $role) {
            PortalRole::query()->updateOrCreate(
                ['identifier' => $role->value],
                ['name' => $role->label()],
            );
        }

        $organisation = CustomerOrganisation::query()->firstOrCreate([
            'name' => 'Fenster Preview Customer',
        ]);

        $sites = collect([
            ['name' => 'Meadow View', 'location' => 'Birmingham'],
            ['name' => 'Oaklands', 'location' => 'Coventry'],
            ['name' => 'Willow Park', 'location' => 'Solihull'],
        ])->map(fn (array $site): Site => Site::query()->updateOrCreate(
            [
                'customer_organisation_id' => $organisation->id,
                'name' => $site['name'],
            ],
            ['location' => $site['location']],
        ));

        foreach (PortalRoleIdentifier::cases() as $roleIdentifier) {
            $role = PortalRole::query()->where('identifier', $roleIdentifier->value)->firstOrFail();

            $user = User::query()->updateOrCreate(
                ['email' => 'preview.'.$roleIdentifier->value.'@example.test'],
                [
                    'customer_organisation_id' => $organisation->id,
                    'portal_role_id' => $role->id,
                    'name' => $roleIdentifier->label().' Preview',
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'is_preview_user' => true,
                ],
            );

            $user->assignedSites()->syncWithoutDetaching($sites->pluck('id')->all());
        }

        $sites->each(function (Site $site): void {
            foreach (range(1, 6) as $plotNumber) {
                $plot = ProjectedPlot::query()->firstOrNew([
                    'external_source' => 'local_preview',
                    'external_identifier' => $site->id.'-'.$plotNumber,
                ]);

                $plot->forceFill([
                    'uuid' => $plot->uuid ?? (string) Str::uuid(),
                    'site_id' => $site->id,
                    'plot_reference' => 'Plot '.str_pad((string) $plotNumber, 3, '0', STR_PAD_LEFT),
                    'is_completed' => false,
                    'source_updated_at' => now(),
                    'synchronised_at' => now(),
                ])->save();

                foreach (CallOffServiceType::cases() as $service) {
                    ProjectedPlotService::query()->firstOrCreate([
                        'projected_plot_id' => $plot->id,
                        'service_identifier' => $service->value,
                    ], [
                        'uuid' => (string) Str::uuid(),
                    ]);
                }
            }
        });
    }
}
