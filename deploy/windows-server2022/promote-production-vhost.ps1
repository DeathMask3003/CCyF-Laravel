param(
    [string] $XamppRoot = 'C:\xampp',
    [string] $ProjectRoot = 'C:\xampp\htdocs\ccyf-laravel'
)

$ErrorActionPreference = 'Stop'
$productionUrl = 'https://ccyf.cobaemex.edu.mx'
$productionHost = 'ccyf.cobaemex.edu.mx'
$apache = Join-Path $XamppRoot 'apache\bin\httpd.exe'
$vhosts = Join-Path $XamppRoot 'apache\conf\extra\httpd-vhosts.conf'
$project = [System.IO.Path]::GetFullPath($ProjectRoot).TrimEnd('\')
$htdocs = [System.IO.Path]::GetFullPath((Join-Path $XamppRoot 'htdocs')).TrimEnd('\')
$public = (Join-Path $project 'public').Replace('\', '/')
$envFile = Join-Path $project '.env'
$encoding = [System.Text.Encoding]::GetEncoding(28591)

if (-not $project.StartsWith($htdocs + '\', [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "El proyecto debe estar dentro de $htdocs."
}
foreach ($path in @($apache, $vhosts, (Join-Path $project 'public\index.php'), $envFile)) {
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { throw "Falta el archivo: $path" }
}
if ($public.Contains('"')) { throw 'La ruta del proyecto contiene comillas no admitidas por Apache.' }

function Read-EnvironmentValues {
    param([string] $Path)

    $values = @{}
    foreach ($line in [System.IO.File]::ReadAllLines($Path)) {
        if ($line -match '^[ \t]*#' -or $line -notmatch '^[ \t]*([^#= \t]+)[ \t]*=(.*)$') { continue }
        $value = $Matches[2].Trim()
        if (($value.StartsWith('"') -and $value.EndsWith('"')) -or
            ($value.StartsWith("'") -and $value.EndsWith("'"))) {
            $value = $value.Substring(1, $value.Length - 2)
        }
        $values[$Matches[1]] = $value
    }

    return $values
}

function Require-EnvironmentValue {
    param(
        [hashtable] $Values,
        [string] $Name,
        [string] $Expected
    )

    if (-not $Values.ContainsKey($Name) -or $Values[$Name] -cne $Expected) {
        throw "Antes del corte, $Name debe ser exactamente $Expected en $envFile."
    }
}

function Require-EnvironmentNotEmpty {
    param(
        [hashtable] $Values,
        [string] $Name
    )

    if (-not $Values.ContainsKey($Name) -or [string]::IsNullOrWhiteSpace($Values[$Name])) {
        throw "Antes del corte, $Name debe tener un valor en $envFile."
    }
}

function Test-ApacheSyntax {
    $ErrorActionPreference = 'Continue'
    $output = & $apache -t 2>&1 | Out-String
    return @{ Code = $LASTEXITCODE; Output = $output.Trim() }
}

$environment = Read-EnvironmentValues -Path $envFile
Require-EnvironmentValue -Values $environment -Name 'APP_ENV' -Expected 'production'
Require-EnvironmentValue -Values $environment -Name 'APP_DEBUG' -Expected 'false'
Require-EnvironmentValue -Values $environment -Name 'APP_URL' -Expected $productionUrl
Require-EnvironmentValue -Values $environment -Name 'SESSION_SECURE_COOKIE' -Expected 'true'
Require-EnvironmentValue -Values $environment -Name 'SESSION_COOKIE' -Expected 'ccyf_laravel_session'
Require-EnvironmentValue -Values $environment -Name 'DB_CONNECTION' -Expected 'mysql'
Require-EnvironmentValue -Values $environment -Name 'DB_DATABASE' -Expected 'ccyflaravel_prod'
Require-EnvironmentValue -Values $environment -Name 'LEGACY_DB_DATABASE' -Expected 'ccyflaravel_prod'
Require-EnvironmentValue -Values $environment -Name 'MAIL_MAILER' -Expected 'smtp'
Require-EnvironmentValue -Values $environment -Name 'CCYF_TURNSTILE_ENABLED' -Expected 'true'
Require-EnvironmentValue -Values $environment -Name 'CCYF_GOOGLE_LOGIN_ENABLED' -Expected 'true'
Require-EnvironmentValue -Values $environment -Name 'GOOGLE_REDIRECT_URI' -Expected "$productionUrl/acceso/google/callback"
foreach ($name in @(
    'APP_KEY',
    'DB_USERNAME',
    'DB_PASSWORD',
    'LEGACY_DB_USERNAME',
    'LEGACY_DB_PASSWORD',
    'CCYF_LEGACY_PASSWORD_KEY',
    'MAIL_USERNAME',
    'MAIL_PASSWORD',
    'MAIL_FROM_ADDRESS',
    'TURNSTILE_SITE_KEY',
    'TURNSTILE_SECRET_KEY',
    'GOOGLE_CLIENT_ID',
    'GOOGLE_CLIENT_SECRET'
)) {
    Require-EnvironmentNotEmpty -Values $environment -Name $name
}

$before = Test-ApacheSyntax
if ($before.Code -ne 0) { throw "Apache ya tiene errores de configuracion: $($before.Output)" }

$content = [System.IO.File]::ReadAllText($vhosts, $encoding)
$blocks = [regex]::Matches($content, '(?is)<VirtualHost\s+\*:([0-9]+)\s*>.*?</VirtualHost>')
$hostPattern = [regex]::Escape($productionHost)
$http = @($blocks | Where-Object { $_.Groups[1].Value -eq '80' -and $_.Value -match "(?im)^[ \t]*ServerName[ \t]+$hostPattern[ \t]*\r?$" })
$https = @($blocks | Where-Object { $_.Groups[1].Value -eq '443' -and $_.Value -match "(?im)^[ \t]*ServerName[ \t]+$hostPattern[ \t]*\r?$" })
if ($http.Count -ne 1 -or $https.Count -ne 1) {
    throw "Se esperaba un VirtualHost :80 y uno :443 para $productionHost. No se modifico Apache."
}

$block = $https[0].Value
if ($block -notmatch '(?im)^[ \t]*SSLEngine[ \t]+on[ \t]*\r?$' -or
    $block -notmatch '(?im)^[ \t]*SSLCertificateFile[ \t]+' -or
    $block -notmatch '(?im)^[ \t]*SSLCertificateKeyFile[ \t]+') {
    throw 'El VirtualHost HTTPS no tiene los certificados esperados. No se modifico Apache.'
}

$documentPattern = '(?im)^([ \t]*)DocumentRoot[ \t]+(?:"[^"\r\n]+"|[^\r\n]+)[ \t]*\r?$'
$directories = [regex]::Matches($block, '(?is)<Directory[ \t]+[^>]+>.*?</Directory>')
if ([regex]::Matches($block, $documentPattern).Count -ne 1 -or $directories.Count -ne 1) {
    throw 'El VirtualHost de produccion tiene varias rutas o directorios. Revisalo antes de cambiarlo.'
}

$directory = $directories[0].Value
$openDirectory = '(?im)^([ \t]*)<Directory[ \t]+[^>]+>[ \t]*\r?$'
if ([regex]::Matches($directory, $openDirectory).Count -ne 1) {
    throw 'No se pudo identificar el directorio publico del VirtualHost.'
}
$directory = [regex]::Replace($directory, $openDirectory, '${1}<Directory "' + $public + '">')
$directory = [regex]::Replace($directory, '(?im)^([ \t]*)Options[ \t]+All[ \t]*\r?$', '${1}Options -Indexes +FollowSymLinks')
$directory = [regex]::Replace($directory, '(?im)^[ \t]*Auth(?:Type|Name|UserFile)[ \t]+[^\r\n]*\r?\n?', '')
$requirePattern = '(?im)^([ \t]*)Require[ \t]+[^\r\n]+\r?$'
if ([regex]::Matches($directory, $requirePattern).Count -ne 1) {
    throw 'El directorio de produccion tiene reglas de acceso no previstas. No se modifico Apache.'
}
$directory = [regex]::Replace($directory, $requirePattern, '${1}Require all granted')

$updatedBlock = $block.Replace($directories[0].Value, $directory)
$updatedBlock = [regex]::Replace($updatedBlock, $documentPattern, '${1}DocumentRoot "' + $public + '"')
if ($updatedBlock -match '(?im)^[ \t]*Auth(?:Type|Name|UserFile)[ \t]+' -or
    $updatedBlock -notmatch '(?im)^[ \t]*Require[ \t]+all[ \t]+granted[ \t]*\r?$') {
    throw 'El VirtualHost conserva otra proteccion de acceso. Revisalo antes de hacerlo publico.'
}

$alias = [regex]::Match($updatedBlock, '(?im)^[ \t]*ServerAlias[ \t]+www\.ccyf\.cobaemex\.edu\.mx[ \t]*\r?$')
$wwwRedirect = 'RewriteCond %{HTTP_HOST} ^www\.ccyf\.cobaemex\.edu\.mx$ [NC]'
if ($alias.Success -and -not $updatedBlock.Contains($wwwRedirect)) {
    $canonical = "`r`n    RewriteEngine On`r`n    $wwwRedirect`r`n    RewriteRule ^ $productionUrl%{REQUEST_URI} [R=301,L,NE]"
    $updatedBlock = $updatedBlock.Substring(0, $alias.Index + $alias.Length) + $canonical +
        $updatedBlock.Substring($alias.Index + $alias.Length)
}

$updatedHttp = $http[0].Value.Replace('https://%{HTTP_HOST}%{REQUEST_URI}', "$productionUrl%{REQUEST_URI}")
$updated = $content
$edits = @(
    [pscustomobject]@{ Index = $http[0].Index; Length = $http[0].Length; Text = $updatedHttp },
    [pscustomobject]@{ Index = $https[0].Index; Length = $https[0].Length; Text = $updatedBlock }
) | Sort-Object Index -Descending
foreach ($edit in $edits) {
    $updated = $updated.Substring(0, $edit.Index) + $edit.Text + $updated.Substring($edit.Index + $edit.Length)
}
if ($updated -eq $content) {
    Write-Host 'El VirtualHost de produccion ya apunta a Laravel y permite acceso publico.'
    exit 0
}

$backup = "$vhosts.ccyf-production-backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')-$(Get-Random)"
Copy-Item -LiteralPath $vhosts -Destination $backup -ErrorAction Stop
try {
    [System.IO.File]::WriteAllText($vhosts, $updated, $encoding)
    $after = Test-ApacheSyntax
    if ($after.Code -ne 0) { throw "Apache rechazo el cambio: $($after.Output)" }
} catch {
    Copy-Item -LiteralPath $backup -Destination $vhosts -Force
    throw
}

Write-Host "VirtualHost preparado y validado: $productionUrl/"
Write-Host "Respaldo del archivo anterior: $backup"
Write-Host 'Apache no se reinicio; el cambio entrara en vigor al reiniciarlo.'
