<?php

namespace App\SourceImport\Integration;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/** Independent fail-closed gate for the non-mutating paired-workbook report. */
final class PairedWorkbookAnalysePolicy
{
    public function authorize(User $actor): User
    {
        if (config('wald_import.paired_analyse_available', false) !== true || ! $actor->exists || $actor->is_preview_user) {
            throw new AuthorizationException('Paired workbook analysis is unavailable.');
        }
        $fresh = User::query()->whereKey($actor->getKey())->first();
        $role = $fresh ? DB::table('portal_roles')->where('id', $fresh->portal_role_id)->value('identifier') : null;
        if (! $fresh || ! $fresh->is_active || $fresh->is_preview_user || $role !== 'fenster_office_staff') {
            throw new AuthorizationException('Paired workbook analysis is Office-only.');
        }

        return $fresh;
    }
}
