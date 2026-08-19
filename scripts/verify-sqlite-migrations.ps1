$ErrorActionPreference = 'Stop'

$verificationDatabase = Join-Path $env:TEMP 'customerapp-migration-verification.sqlite'

if (Test-Path -LiteralPath $verificationDatabase) {
    Remove-Item -LiteralPath $verificationDatabase -Force
}

New-Item -ItemType File -Path $verificationDatabase -Force | Out-Null

try {
    $env:DB_CONNECTION = 'sqlite'
    $env:DB_DATABASE = $verificationDatabase

    php artisan migrate --force
    php artisan migrate:reset --force
    php artisan migrate --force
} finally {
    Remove-Item -LiteralPath $verificationDatabase -Force -ErrorAction SilentlyContinue
}
