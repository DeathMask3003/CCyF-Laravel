param(
    [string] $XamppRoot = 'C:\xampp',
    [string] $ProjectRoot = 'C:\xampp\htdocs\ccyf-laravel',
    [string] $LegacyFilesRoot = 'C:\xampp\ccyf-legacy-files'
)

$ErrorActionPreference = 'Stop'
$php = Join-Path $XamppRoot 'php\php.exe'
$apache = Join-Path $XamppRoot 'apache\bin\httpd.exe'
$required = @('pdo_mysql', 'mbstring', 'gd', 'zip', 'xml', 'fileinfo', 'openssl')
$failed = $false

foreach ($path in @($php, $apache, $ProjectRoot, (Join-Path $ProjectRoot 'public\index.php'), $LegacyFilesRoot)) {
    if (Test-Path -LiteralPath $path) {
        Write-Host "OK      $path"
    } else {
        Write-Warning "FALTA   $path"
        $failed = $true
    }
}

if (Test-Path -LiteralPath $php) {
    $version = (& $php -r 'echo PHP_VERSION;').Trim()
    Write-Host "PHP     $version"
    if ([version]$version -lt [version]'8.2.0') {
        Write-Warning 'Laravel 12 requiere PHP 8.2 o posterior.'
        $failed = $true
    }

    $extensions = @(& $php -m | ForEach-Object { $_.Trim().ToLowerInvariant() })
    foreach ($extension in $required) {
        if ($extensions -contains $extension) {
            Write-Host "OK      extension $extension"
        } else {
            Write-Warning "FALTA   extension $extension"
            $failed = $true
        }
    }
}

foreach ($command in @('git', 'composer')) {
    if (Get-Command $command -ErrorAction SilentlyContinue) {
        Write-Host "OK      comando $command"
    } else {
        Write-Warning "FALTA   comando $command en PATH"
        $failed = $true
    }
}

if (Test-Path -LiteralPath (Join-Path $ProjectRoot '.env')) {
    Write-Host 'OK      archivo .env del servidor'
} else {
    Write-Warning 'FALTA   archivo .env del servidor'
    $failed = $true
}

if ($failed) { exit 1 }
Write-Host 'Comprobaciones básicas completas. Verifica aparte Apache HTTPS, MySQL, correo y permisos de escritura.'
