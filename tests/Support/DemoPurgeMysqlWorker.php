<?php

use App\Actions\Administration\PurgeDemoCustomerOrSiteAction;
use App\Models\CustomerOrganisation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    app(PurgeDemoCustomerOrSiteAction::class)->site(
        User::query()->findOrFail((int) $argv[1]),
        CustomerOrganisation::query()->findOrFail((int) $argv[2]),
        Site::query()->findOrFail((int) $argv[3]),
        $argv[4],
    );
    echo json_encode(['outcome' => 'purged'], JSON_THROW_ON_ERROR);
} catch (ValidationException $exception) {
    echo json_encode(['outcome' => 'stale_or_blocked'], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode(['outcome' => 'error', 'type' => $exception::class], JSON_THROW_ON_ERROR);
}
