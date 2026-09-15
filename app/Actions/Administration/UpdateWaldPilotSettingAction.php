<?php

namespace App\Actions\Administration;

use App\Models\User;
use App\Policies\OfficeAdministrationPolicy;
use App\SourceImport\Integration\WaldPilotAvailability;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateWaldPilotSettingAction
{
    public const ENABLE_CONFIRMATION = 'I understand this enables the supervised Wald import pilot for authorised Fenster Office Staff.';

    public function handle(User $actor, bool $enabled, int $expectedVersion, string $reason, ?string $confirmation): array
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['reason' => 'Enter a reason of 2,000 characters or fewer.']);
        }

        return DB::transaction(function () use ($actor, $enabled, $expectedVersion, $reason, $confirmation): array {
            $fresh = (new OfficeAdministrationPolicy)->authorize($actor, 'settings_update', true);
            $setting = DB::table('wald_pilot_settings')->where('key', WaldPilotAvailability::KEY)->lockForUpdate()->firstOrFail();
            if ((int) $setting->lock_version !== $expectedVersion) {
                throw ValidationException::withMessages(['lock_version' => 'This setting changed after the page was loaded. Refresh and try again.']);
            }
            $old = (bool) $setting->enabled;
            if (! $old && $enabled && $confirmation !== self::ENABLE_CONFIRMATION) {
                throw ValidationException::withMessages(['confirmation' => 'Confirm that you understand this enables the supervised pilot.']);
            }
            if ($old === $enabled) {
                return $this->state($setting);
            }

            $now = now('UTC');
            DB::table('wald_pilot_settings')->where('id', $setting->id)->update([
                'enabled' => $enabled,
                'lock_version' => (int) $setting->lock_version + 1,
                'updated_by_user_id' => $fresh->id,
                'updated_at' => $now,
            ]);
            DB::table('wald_pilot_setting_events')->insert([
                'setting_id' => $setting->id,
                'actor_user_id' => $fresh->id,
                'actor_name' => $fresh->name,
                'actor_role' => (string) $fresh->portalRole->identifier,
                'old_value' => $old,
                'new_value' => $enabled,
                'reason' => $reason,
                'created_at' => $now,
            ]);

            return ['key' => $setting->key, 'enabled' => $enabled, 'lock_version' => (int) $setting->lock_version + 1];
        }, 3);
    }

    private function state(object $setting): array
    {
        return ['key' => $setting->key, 'enabled' => (bool) $setting->enabled, 'lock_version' => (int) $setting->lock_version];
    }
}
