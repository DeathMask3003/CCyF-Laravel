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
    throw "No se encontró PHP de XAMPP: $php"
}
foreach ($command in @('git', 'composer')) {
    if (-not (Get-Command $command -ErrorAction SilentlyContinue)) {
        throw "No se encontró $command en PATH."
    }
}

$env:PATH = (Join-Path $XamppRoot 'php') + ';' + $env:PATH
if ($UseExistingCheckout) {
    if (-not (Test-Path -LiteralPath (Join-Path $project '.git'))) {
        throw "No se encontró un checkout Git en $project."
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
        throw 'El checkout tiene cambios locales. Revísalos antes de instalar.'
    }
} else {
    if (Test-Path -LiteralPath $project) {
        throw "La carpeta $project ya existe. Revísala antes de instalar para no sobrescribir datos."
    }
    & git clone --branch main --single-branch $Repository $project
    if ($LASTEXITCODE -ne 0) { throw 'Falló la descarga del repositorio.' }
}

Push-Location $project
try {
    if (Test-Path -LiteralPath '.env') {
        throw 'Ya existe .env. Este instalador es para la primera instalación y no lo reemplazará.'
    }
    Copy-Item -LiteralPath 'deploy\windows-server2022\env.production.example' -Destination '.env' -ErrorAction Stop
    & composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Falló la instalación de dependencias con Composer.' }

    & $php artisan key:generate --force --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo generar APP_KEY.' }

    & composer check-platform-reqs --no-dev
    if ($LASTEXITCODE -ne 0) { throw 'PHP de XAMPP no cumple los requisitos de Composer.' }
} finally {
    Pop-Location
}

Write-Host "Código instalado en $project"
Write-Host 'Completa .env con credenciales, URL y correo; copia los expedientes; verifica la base ccyflaravel antes de ejecutar migraciones.'
