param(
    [string] $ProjectRoot = 'C:\xampp\htdocs\ccyf-laravel',
    [string] $XamppRoot = 'C:\xampp'
)

$ErrorActionPreference = 'Stop'
$project = [System.IO.Path]::GetFullPath($ProjectRoot).TrimEnd('\')
$php = Join-Path $XamppRoot 'php\php.exe'
$backupScript = Join-Path $project 'deploy\windows-server2022\backup-production-database.ps1'
$migration = 'database/migrations/2026_10_02_000001_add_home_document_to_ccyf_branding.php'

foreach ($path in @($php, $backupScript, (Join-Path $project $migration))) {
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { throw "Falta el archivo: $path" }
}

Set-Location -LiteralPath $project
& $backupScript -ProjectRoot $project -XamppRoot $XamppRoot

function Invoke-Artisan {
    param([string[]] $Arguments)
    $ErrorActionPreference = 'Continue'
    & $php artisan @Arguments
    $code = $LASTEXITCODE
    $ErrorActionPreference = 'Stop'
    if ($code -ne 0) { throw "artisan $($Arguments[0]) fallo con codigo $code. No continúes con el siguiente paso." }
}

Invoke-Artisan -Arguments @('optimize:clear', '--no-ansi')
Invoke-Artisan -Arguments @('migrate', "--path=$migration", '--force', '--no-interaction', '--no-ansi')
Invoke-Artisan -Arguments @('optimize:clear', '--no-ansi')

$ErrorActionPreference = 'Continue'
$status = & $php artisan migrate:status --no-ansi 2>&1
$statusCode = $LASTEXITCODE
$ErrorActionPreference = 'Stop'
if ($statusCode -ne 0) { throw "No se pudo consultar el estado de la migracion (codigo $statusCode)." }
$line = @($status | Where-Object { $_ -match '2026_10_02_000001_add_home_document_to_ccyf_branding' })
if ($line.Count -ne 1 -or $line[0] -notmatch '\bRan\b') {
    throw 'La migracion del PDF destacado no aparece como aplicada. Revisa la salida anterior.'
}
Write-Host $line[0]
Write-Host 'Actualizacion del PDF destacado aplicada en la base de produccion.'
