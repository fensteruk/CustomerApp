<?php

namespace App\Domain\Administration;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

final class AdministrationRules
{
    public function name(string $name): string
    {
        $normalised = preg_replace('/\s+/u', ' ', trim($name));

        if (! is_string($normalised)
            || $normalised === ''
            || mb_strlen($normalised) > 255
            || preg_match('/[\x00-\x1f\x7f]/u', $normalised)) {
            throw ValidationException::withMessages(['name' => 'Enter a valid name of 255 characters or fewer.']);
        }

        return $normalised;
    }

    public function location(?string $location): ?string
    {
        if ($location === null || trim($location) === '') {
            return null;
        }

        $normalised = preg_replace('/\s+/u', ' ', trim($location));

        if (! is_string($normalised)
            || mb_strlen($normalised) > 255
            || preg_match('/[\x00-\x1f\x7f]/u', $normalised)) {
            throw ValidationException::withMessages(['location' => 'Enter a valid location of 255 characters or fewer.']);
        }

        return $normalised;
    }

    public function reason(string $reason): string
    {
        $normalised = trim($reason);

        if ($normalised === '' || mb_strlen($normalised) > 2000) {
            throw ValidationException::withMessages(['reason' => 'Enter a reason of 2,000 characters or fewer.']);
        }

        return $normalised;
    }

    public function expectedVersion(int $expectedVersion, int $currentVersion): void
    {
        if ($expectedVersion < 1 || $expectedVersion !== $currentVersion) {
            throw ValidationException::withMessages([
                'lock_version' => 'This record changed after it was loaded. Refresh it and try again.',
            ]);
        }
    }

    public function isCustomerNameConflict(QueryException $exception): bool
    {
        return $this->isUniqueConflict(
            $exception,
            'customer_organisations_name_unique',
            'customer_organisations.name',
        );
    }

    public function isSiteNameConflict(QueryException $exception): bool
    {
        return $this->isUniqueConflict(
            $exception,
            'sites_customer_organisation_id_name_unique',
            'sites.customer_organisation_id, sites.name',
        );
    }

    private function isUniqueConflict(QueryException $exception, string $mysqlIndex, string $sqliteColumns): bool
    {
        $message = mb_strtolower($exception->getMessage());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return ($driverCode === 1062 && str_contains($message, mb_strtolower($mysqlIndex)))
            || ($driverCode === 19 && str_contains($message, mb_strtolower($sqliteColumns)));
    }
}
