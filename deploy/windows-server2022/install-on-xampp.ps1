param(
    [string] $XamppRoot = 'C:\xampp',
    [string] $ProjectRoot = 'C:\xampp\htdocs\ccyf-laravel',
    [string] $Repository = 'https://github.com/DeathMask3003/CCyF-Laravel.git',
    [switch] $UseExistingCheckout
)

$ErrorActionPreference = 'Stop'
$php = Join-Path $XamppRoot 'php\php.exe'
$htdocs = [System.IO.Path]::GetFullPath((Join-Path $XamppRoot 'htdocs')).TrimEnd('\')
$project = [System.IO.Path]::GetFullPath($ProjectRoot).TrimEnd('\')

if (-not $project.StartsWith($htdocs + '\', [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "El proyecto debe instalarse dentro de $htdocs."
}
if (-not (Test-Path -LiteralPath $php)) {
    throw "No se encontro PHP de XAMPP: $php"
}
foreach ($command in @('git', 'composer')) {
    if (-not (Get-Command $command -ErrorAction SilentlyContinue)) {
        throw "No se encontro $command en PATH."
    }
}

$env:PATH = (Join-Path $XamppRoot 'php') + ';' + $env:PATH
if ($UseExistingCheckout) {
    if (-not (Test-Path -LiteralPath (Join-Path $project '.git'))) {
        throw "No se encontro un checkout Git en $project."
    }
    $actualRepository = (& git -C $project remote get-url origin).Trim()
    if ($LASTEXITCODE -ne 0 -or -not $actualRepository.Equals($Repository, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "El checkout no apunta al repositorio esperado: $Repository"
    }
    $branch = (& git -C $project branch --show-current).Trim()
    if ($LASTEXITCODE -ne 0 -or $branch -ne 'main') {
        throw 'El checkout debe estar en la rama main.'
    }
    $changes = @(& git -C $project status --porcelain)
    if ($LASTEXITCODE -ne 0 -or $changes.Count -gt 0) {
        throw 'El checkout tiene cambios locales. Revisalos antes de instalar.'
    }
} else {
    if (Test-Path -LiteralPath $project) {
        throw "La carpeta $project ya existe. Revisala antes de instalar para no sobrescribir datos."
    }
    & git clone --branch main --single-branch $Repository $project
    if ($LASTEXITCODE -ne 0) { throw 'Fallo la descarga del repositorio.' }
}

Push-Location $project
try {
    if (Test-Path -LiteralPath '.env') {
        Write-Host 'Se conserva el archivo .env existente.'
    } else {
        Copy-Item -LiteralPath 'deploy\windows-server2022\env.production.example' -Destination '.env' -ErrorAction Stop
    }
    & composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Fallo la instalacion de dependencias con Composer.' }

    $keyLine = Get-Content -LiteralPath '.env' | Where-Object { $_ -match '^APP_KEY=' } | Select-Object -First 1
    if (-not $keyLine -or $keyLine -match '^APP_KEY=\s*$') {
        & $php artisan key:generate --force --no-interaction
        if ($LASTEXITCODE -ne 0) { throw 'No se pudo generar APP_KEY.' }
    } else {
        Write-Host 'Se conserva APP_KEY existente.'
    }

    & composer check-platform-reqs --no-dev
    if ($LASTEXITCODE -ne 0) { throw 'PHP de XAMPP no cumple los requisitos de Composer.' }
} finally {
    Pop-Location
}

Write-Host "Codigo instalado en $project"
Write-Host 'Completa .env con credenciales, URL y correo; copia los expedientes; verifica la base ccyflaravel antes de ejecutar migraciones.'
