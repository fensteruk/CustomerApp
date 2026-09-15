<?php

namespace App\SourceImport\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class WaldPilotAvailability
{
    public const KEY = 'wald_import_pilot_enabled';

    public function environmentAllows(): bool
    {
        return config('wald_import.pilot_available', false) === true;
    }

    public function applicationEnabled(): bool
    {
        if (! Schema::hasTable('wald_pilot_settings')) {
            return false;
        }

        return (bool) DB::table('wald_pilot_settings')->where('key', self::KEY)->value('enabled');
    }

    public function enabled(): bool
    {
        return $this->environmentAllows() && $this->applicationEnabled();
    }
}
