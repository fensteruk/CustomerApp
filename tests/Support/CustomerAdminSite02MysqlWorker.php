<?php

use App\Actions\Administration\CreateSiteAction;
use App\Actions\Administration\DeactivateCustomerAction;
use App\Actions\Administration\DeactivateSiteAction;
use App\Actions\Administration\ReactivateCustomerAction;
use App\Actions\Administration\RenameCustomerAction;
use App\Actions\Administration\UpdateSiteAction;
use App\Models\CustomerOrganisation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$operation = $argv[1] ?? '';
$arguments = array_slice($argv, 2);
$barrier = array_pop($arguments);

try {
    $deadline = microtime(true) + 20;
    while (! file_exists($barrier)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('The ADMIN-SITE02 MySQL concurrency barrier was not released.');
        }

        usleep(10_000);
    }

    $actor = User::query()->findOrFail($arguments[0]);

    match ($operation) {
        'customer-rename' => app(RenameCustomerAction::class)->handle(
            $actor,
            CustomerOrganisation::query()->findOrFail($arguments[1]),
            $arguments[2],
            (int) $arguments[3],
        ),
        'customer-deactivate' => app(DeactivateCustomerAction::class)->handle(
            $actor,
            CustomerOrganisation::query()->findOrFail($arguments[1]),
            $arguments[2],
            (int) $arguments[3],
        ),
        'customer-reactivate' => app(ReactivateCustomerAction::class)->handle(
            $actor,
            CustomerOrganisation::query()->findOrFail($arguments[1]),
            $arguments[2],
            (int) $arguments[3],
        ),
        'site-create' => app(CreateSiteAction::class)->handle(
            $actor,
            CustomerOrganisation::query()->findOrFail($arguments[1]),
            $arguments[2],
            $arguments[3] === '__NULL__' ? null : $arguments[3],
        ),
        'site-update' => app(UpdateSiteAction::class)->handle(
            $actor,
            CustomerOrganisation::query()->findOrFail($arguments[1]),
            Site::query()->findOrFail($arguments[2]),
            $arguments[3],
            $arguments[4] === '__NULL__' ? null : $arguments[4],
            (int) $arguments[5],
        ),
        'site-deactivate' => app(DeactivateSiteAction::class)->handle(
            $actor,
            CustomerOrganisation::query()->findOrFail($arguments[1]),
            Site::query()->findOrFail($arguments[2]),
            $arguments[3],
            (int) $arguments[4],
        ),
        default => throw new InvalidArgumentException("Unknown ADMIN-SITE02 operation: {$operation}"),
    };

    adminSite02MysqlResult(true, $operation);
} catch (Throwable $exception) {
    adminSite02MysqlResult(false, $operation, $exception);
}

function adminSite02MysqlResult(bool $ok, string $operation, ?Throwable $exception = null): never
{
    echo json_encode([
        'ok' => $ok,
        'operation' => $operation,
        'exception' => $exception === null ? null : $exception::class,
        'message' => $exception?->getMessage(),
    ], JSON_THROW_ON_ERROR);

    exit(0);
}
