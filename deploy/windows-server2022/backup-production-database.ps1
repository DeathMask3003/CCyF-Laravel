param(
    [string] $ProjectRoot = 'C:\xampp\htdocs\ccyf-laravel',
    [string] $XamppRoot = 'C:\xampp',
    [string] $BackupRoot = 'C:\xampp\ccyf-backups'
)

$ErrorActionPreference = 'Stop'
$project = [System.IO.Path]::GetFullPath($ProjectRoot).TrimEnd('\')
$htdocs = [System.IO.Path]::GetFullPath((Join-Path $XamppRoot 'htdocs')).TrimEnd('\')
$destination = [System.IO.Path]::GetFullPath($BackupRoot).TrimEnd('\')
$envFile = Join-Path $project '.env'
$dump = Join-Path $XamppRoot 'mysql\bin\mysqldump.exe'

if (-not $project.Equals((Join-Path $htdocs 'ccyf-laravel'), [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'Este respaldo solo admite el checkout de produccion C:\xampp\htdocs\ccyf-laravel.'
}
if ($destination.StartsWith($htdocs + '\', [System.StringComparison]::OrdinalIgnoreCase) -or
    $destination.Equals($htdocs, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'La carpeta de respaldo debe estar fuera de htdocs.'
}
foreach ($path in @($envFile, $dump)) {
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { throw "Falta el archivo: $path" }
}

$values = @{}
foreach ($line in [System.IO.File]::ReadAllLines($envFile)) {
    if ($line -match '^[ \t]*#' -or $line -notmatch '^[ \t]*([^#= \t]+)[ \t]*=(.*)$') { continue }
    $name = $Matches[1]
    $value = $Matches[2].Trim()
    if ($value.Length -ge 2 -and
        (($value.StartsWith('"') -and $value.EndsWith('"')) -or
         ($value.StartsWith("'") -and $value.EndsWith("'")))) {
        $value = $value.Substring(1, $value.Length - 2)
    }
    $values[$name] = $value
}

foreach ($item in @(
    @{ Name = 'APP_ENV'; Expected = 'production' },
    @{ Name = 'APP_URL'; Expected = 'https://ccyf.cobaemex.edu.mx' },
    @{ Name = 'DB_CONNECTION'; Expected = 'mysql' },
    @{ Name = 'DB_DATABASE'; Expected = 'ccyflaravel_prod' },
    @{ Name = 'LEGACY_DB_DATABASE'; Expected = 'ccyflaravel_prod' }
)) {
    if (-not $values.ContainsKey($item.Name) -or $values[$item.Name] -cne $item.Expected) {
        throw "El .env no corresponde a produccion: $($item.Name). No se creo respaldo."
    }
}
foreach ($name in @('DB_HOST', 'DB_PORT', 'DB_USERNAME')) {
    if (-not $values.ContainsKey($name) -or [string]::IsNullOrWhiteSpace($values[$name])) {
        throw "Falta $name en .env. No se creo respaldo."
    }
}
if ($values['DB_HOST'] -notmatch '^[A-Za-z0-9.:-]+$' -or
    $values['DB_PORT'] -notmatch '^[0-9]{1,5}$' -or
    $values['DB_USERNAME'] -notmatch '^[A-Za-z0-9_.-]+$') {
    throw 'Los parametros de conexion no tienen el formato esperado.'
}

New-Item -ItemType Directory -Path $destination -Force | Out-Null
$backup = Join-Path $destination ("ccyflaravel_prod-$(Get-Date -Format 'yyyyMMdd-HHmmss')-$(Get-Random).sql")
$hadPassword = Test-Path Env:MYSQL_PWD
$previousPassword = if ($hadPassword) { $env:MYSQL_PWD } else { $null }

try {
    if ($values.ContainsKey('DB_PASSWORD') -and $values['DB_PASSWORD'].Length -gt 0) {
        $env:MYSQL_PWD = $values['DB_PASSWORD']
    } else {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    }

    $arguments = @(
        '--no-defaults', '--protocol=tcp',
        "--host=$($values['DB_HOST'])", "--port=$($values['DB_PORT'])", "--user=$($values['DB_USERNAME'])",
        '--single-transaction', '--quick', '--routines', '--triggers', '--events',
        '--no-tablespaces', '--default-character-set=utf8mb4', "--result-file=$backup", 'ccyflaravel_prod'
    )
    $ErrorActionPreference = 'Continue'
    & $dump @arguments
    $dumpExitCode = $LASTEXITCODE
    $ErrorActionPreference = 'Stop'
    if ($dumpExitCode -ne 0) { throw "mysqldump fallo con codigo $dumpExitCode. Revisa el mensaje anterior; no uses el archivo incompleto." }
} finally {
    if ($hadPassword) { $env:MYSQL_PWD = $previousPassword }
    else { Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue }
}

$file = Get-Item -LiteralPath $backup
if ($file.Length -lt 1024) { throw "El respaldo parece incompleto: $backup" }
Write-Host "Respaldo creado fuera de htdocs: $backup"
Write-Host "Tamano: $([math]::Round($file.Length / 1MB, 2)) MB"
Write-Host 'La base no se modifico. Conserva este archivo antes de ejecutar la migracion.'
