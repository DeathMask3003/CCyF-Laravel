param(
    [string] $XamppRoot = 'C:\xampp',
    [string] $ProjectRoot = 'C:\xampp\htdocs\ccyf-laravel'
)

$ErrorActionPreference = 'Stop'
$apache = Join-Path $XamppRoot 'apache\bin\httpd.exe'
$vhosts = Join-Path $XamppRoot 'apache\conf\extra\httpd-vhosts.conf'
$project = [System.IO.Path]::GetFullPath($ProjectRoot).TrimEnd('\')
$htdocs = [System.IO.Path]::GetFullPath((Join-Path $XamppRoot 'htdocs')).TrimEnd('\')
$public = (Join-Path $project 'public').Replace('\', '/')
$encoding = [System.Text.Encoding]::GetEncoding(28591)

if (-not $project.StartsWith($htdocs + '\', [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "El proyecto debe estar dentro de $htdocs."
}
foreach ($path in @($apache, $vhosts, (Join-Path $project 'public\index.php'), (Join-Path $project '.env'))) {
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { throw "Falta el archivo: $path" }
}
if ($public.Contains('"')) { throw 'La ruta del proyecto contiene comillas no admitidas por Apache.' }

function Test-ApacheSyntax {
    $ErrorActionPreference = 'Continue'
    $output = & $apache -t 2>&1 | Out-String
    return @{ Code = $LASTEXITCODE; Output = $output.Trim() }
}

$before = Test-ApacheSyntax
if ($before.Code -ne 0) { throw "Apache ya tiene errores de configuracion: $($before.Output)" }

$content = [System.IO.File]::ReadAllText($vhosts, $encoding)
$blocks = [regex]::Matches($content, '(?is)<VirtualHost\s+\*:([0-9]+)\s*>.*?</VirtualHost>')
$http = @($blocks | Where-Object { $_.Groups[1].Value -eq '80' -and $_.Value -match '(?im)^[ \t]*ServerName[ \t]+pruebas\.cobaemex\.edu\.mx[ \t]*\r?$' })
$https = @($blocks | Where-Object { $_.Groups[1].Value -eq '443' -and $_.Value -match '(?im)^[ \t]*ServerName[ \t]+pruebas\.cobaemex\.edu\.mx[ \t]*\r?$' })
if ($http.Count -ne 1 -or $https.Count -ne 1) {
    throw 'Se esperaba un VirtualHost :80 y uno :443 para pruebas.cobaemex.edu.mx. No se modifico Apache.'
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
    throw 'El VirtualHost de pruebas tiene varias rutas o directorios. Revisalo antes de cambiarlo.'
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
    throw 'El directorio de pruebas tiene reglas de acceso no previstas. No se modifico Apache.'
}
$directory = [regex]::Replace($directory, $requirePattern, '${1}Require all granted')

$updatedBlock = $block.Replace($directories[0].Value, $directory)
$updatedBlock = [regex]::Replace($updatedBlock, $documentPattern, '${1}DocumentRoot "' + $public + '"')
if ($updatedBlock -match '(?im)^[ \t]*Auth(?:Type|Name|UserFile)[ \t]+' -or
    $updatedBlock -notmatch '(?im)^[ \t]*Require[ \t]+all[ \t]+granted[ \t]*\r?$') {
    throw 'El VirtualHost conserva otra proteccion de acceso. Revisalo antes de hacerlo publico.'
}

$alias = [regex]::Match($updatedBlock, '(?im)^[ \t]*ServerAlias[ \t]+www\.pruebas\.cobaemex\.edu\.mx[ \t]*\r?$')
$wwwRedirect = 'RewriteCond %{HTTP_HOST} ^www\.pruebas\.cobaemex\.edu\.mx$ [NC]'
if ($alias.Success -and -not $updatedBlock.Contains($wwwRedirect)) {
    $canonical = "`r`n    RewriteEngine On`r`n    $wwwRedirect`r`n    RewriteRule ^ https://pruebas.cobaemex.edu.mx%{REQUEST_URI} [R=301,L,NE]"
    $updatedBlock = $updatedBlock.Substring(0, $alias.Index + $alias.Length) + $canonical +
        $updatedBlock.Substring($alias.Index + $alias.Length)
}

$updatedHttp = $http[0].Value.Replace('https://%{HTTP_HOST}%{REQUEST_URI}', 'https://pruebas.cobaemex.edu.mx%{REQUEST_URI}')
$updated = $content
$edits = @(
    [pscustomobject]@{ Index = $http[0].Index; Length = $http[0].Length; Text = $updatedHttp },
    [pscustomobject]@{ Index = $https[0].Index; Length = $https[0].Length; Text = $updatedBlock }
) | Sort-Object Index -Descending
foreach ($edit in $edits) {
    $updated = $updated.Substring(0, $edit.Index) + $edit.Text + $updated.Substring($edit.Index + $edit.Length)
}
if ($updated -eq $content) {
    Write-Host 'El VirtualHost de pruebas ya apunta a Laravel y permite acceso publico.'
    exit 0
}

$backup = "$vhosts.ccyf-pruebas-backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')-$(Get-Random)"
Copy-Item -LiteralPath $vhosts -Destination $backup -ErrorAction Stop
try {
    [System.IO.File]::WriteAllText($vhosts, $updated, $encoding)
    $after = Test-ApacheSyntax
    if ($after.Code -ne 0) { throw "Apache rechazo el cambio: $($after.Output)" }
} catch {
    Copy-Item -LiteralPath $backup -Destination $vhosts -Force
    throw
}

Write-Host "VirtualHost preparado y validado: https://pruebas.cobaemex.edu.mx/"
Write-Host "Respaldo del archivo anterior: $backup"
Write-Host 'Apache no se reinicio; el cambio entrara en vigor al reiniciarlo.'
