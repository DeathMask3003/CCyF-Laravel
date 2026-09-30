param(
    [string] $XamppRoot = 'C:\xampp',
    [string] $ProjectRoot = 'C:\xampp\htdocs\ccyf-laravel'
)

$ErrorActionPreference = 'Stop'
$apache = Join-Path $XamppRoot 'apache\bin\httpd.exe'
$htpasswd = Join-Path $XamppRoot 'apache\bin\htpasswd.exe'
$vhosts = Join-Path $XamppRoot 'apache\conf\extra\httpd-vhosts.conf'
$authDir = Join-Path $XamppRoot 'ccyf-staging-auth'
$authFile = Join-Path $authDir 'users.htpasswd'
$authFileApache = $authFile.Replace('\', '/')
$project = [System.IO.Path]::GetFullPath($ProjectRoot).TrimEnd('\')
$htdocs = [System.IO.Path]::GetFullPath((Join-Path $XamppRoot 'htdocs')).TrimEnd('\')
$public = (Join-Path $project 'public').Replace('\', '/')
$oldRoot = 'C:/xampp/htdocs/pruebasccyf'
$encoding = [System.Text.Encoding]::GetEncoding(28591)

if (-not $project.StartsWith($htdocs + '\', [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "El proyecto debe estar dentro de $htdocs."
}
foreach ($path in @($apache, $htpasswd, $vhosts, (Join-Path $project 'public\index.php'), (Join-Path $project '.env'))) {
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
$http = @($blocks | Where-Object { $_.Groups[1].Value -eq '80' -and $_.Value -match '(?im)^\s*ServerName\s+pruebas\.cobaemex\.edu\.mx\s*$' })
$https = @($blocks | Where-Object { $_.Groups[1].Value -eq '443' -and $_.Value -match '(?im)^\s*ServerName\s+pruebas\.cobaemex\.edu\.mx\s*$' })
if ($http.Count -ne 1 -or $https.Count -ne 1) {
    throw 'Se esperaba un VirtualHost :80 y uno :443 para pruebas.cobaemex.edu.mx. No se modifico Apache.'
}
if ($https[0].Value -notmatch '(?im)^\s*SSLEngine\s+on\s*$' -or
    $https[0].Value -notmatch '(?im)^\s*SSLCertificateFile\s+' -or
    $https[0].Value -notmatch '(?im)^\s*SSLCertificateKeyFile\s+') {
    throw 'El VirtualHost HTTPS no tiene los certificados esperados. No se modifico Apache.'
}

$block = $https[0].Value
$escapedOldRoot = [regex]::Escape($oldRoot)
$escapedPublic = [regex]::Escape($public)
$usesOldRoot = $block -match "(?im)^\s*DocumentRoot\s+`"$escapedOldRoot`"\s*$" -and
    $block -match "(?im)^\s*<Directory\s+`"$escapedOldRoot`">\s*$"
$usesLaravel = $block -match "(?im)^\s*DocumentRoot\s+`"$escapedPublic`"\s*$" -and
    $block -match "(?im)^\s*<Directory\s+`"$escapedPublic`">\s*$"
if (-not $usesOldRoot -and -not $usesLaravel) {
    throw 'El VirtualHost de pruebas difiere del proporcionado. Revisalo antes de cambiarlo.'
}
if ($usesOldRoot -and
    ([regex]::Matches($block, $escapedOldRoot, [System.Text.RegularExpressions.RegexOptions]::IgnoreCase).Count -ne 2 -or
    [regex]::Matches($block, '(?im)^[ \t]*Options[ \t]+All[ \t]*$').Count -ne 1)) {
    throw 'La estructura del VirtualHost no coincide con la esperada. No se modifico Apache.'
}

if (-not (Test-Path -LiteralPath $authFile -PathType Leaf)) {
    New-Item -ItemType Directory -Path $authDir -Force | Out-Null
    $secret = Read-Host 'Contrasena adicional para el sitio de pruebas (minimo 12 caracteres)' -AsSecureString
    if ($secret.Length -lt 12) { throw 'La contrasena adicional debe tener al menos 12 caracteres.' }
    $pointer = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
    try {
        $plain = [System.Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
        $ErrorActionPreference = 'Continue'
        $result = $plain | & $htpasswd -i -B -c $authFile 'ccyf-pruebas' 2>&1 | Out-String
        $code = $LASTEXITCODE
        $ErrorActionPreference = 'Stop'
        if ($code -ne 0) { throw "No se pudo crear el acceso adicional: $result" }
    } finally {
        $plain = $null
        $secret.Dispose()
        [System.Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
        $ErrorActionPreference = 'Stop'
    }
    Write-Host 'Acceso adicional creado para el usuario ccyf-pruebas.'
}
if ([System.IO.File]::ReadAllText($authFile) -notmatch '(?m)^ccyf-pruebas:') {
    throw "El archivo de acceso no contiene al usuario ccyf-pruebas: $authFile"
}

$updatedHttp = $http[0].Value
$oldRedirect = 'https://%{HTTP_HOST}%{REQUEST_URI}'
$canonicalRedirect = 'https://pruebas.cobaemex.edu.mx%{REQUEST_URI}'
if ($updatedHttp.Contains($oldRedirect)) {
    $updatedHttp = $updatedHttp.Replace($oldRedirect, $canonicalRedirect)
} elseif (-not $updatedHttp.Contains($canonicalRedirect)) {
    throw 'El redireccionamiento HTTP de pruebas difiere del esperado.'
}

$updatedBlock = $block
if ($usesOldRoot) {
    $updatedBlock = [regex]::Replace($updatedBlock, $escapedOldRoot, $public, [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
    $updatedBlock = [regex]::Replace($updatedBlock, '(?im)^([ \t]*)Options[ \t]+All[ \t]*$', '${1}Options -Indexes +FollowSymLinks')
}
$requireAll = '(?im)^([ \t]*)Require[ \t]+all[ \t]+granted[ \t]*$'
if ([regex]::Matches($updatedBlock, $requireAll).Count -eq 1) {
    $authLines = '${1}AuthType Basic' + "`r`n" +
        '${1}AuthName "CCyF pruebas"' + "`r`n" +
        '${1}AuthUserFile "' + $authFileApache + '"' + "`r`n" +
        '${1}Require valid-user'
    $updatedBlock = [regex]::Replace($updatedBlock, $requireAll, $authLines)
} elseif ($updatedBlock -notmatch '(?im)^\s*AuthType\s+Basic\s*$' -or
    $updatedBlock -notmatch "(?im)^\s*AuthUserFile\s+`"$([regex]::Escape($authFileApache))`"\s*$" -or
    $updatedBlock -notmatch '(?im)^\s*Require\s+valid-user\s*$') {
    throw 'El control de acceso del VirtualHost es distinto del esperado.'
}
if ($updatedBlock -match $requireAll) { throw 'El VirtualHost aun permite acceso sin contrasena.' }

$wwwRedirect = 'RewriteCond %{HTTP_HOST} ^www\.pruebas\.cobaemex\.edu\.mx$ [NC]'
if (-not $updatedBlock.Contains($wwwRedirect)) {
    $alias = [regex]::Match($updatedBlock, '(?im)^[ \t]*ServerAlias[ \t]+www\.pruebas\.cobaemex\.edu\.mx[ \t]*$')
    if (-not $alias.Success) { throw 'Falta el alias www del VirtualHost HTTPS.' }
    $canonical = "`r`n    RewriteEngine On`r`n    $wwwRedirect`r`n    RewriteRule ^ https://pruebas.cobaemex.edu.mx%{REQUEST_URI} [R=301,L,NE]"
    $updatedBlock = $updatedBlock.Substring(0, $alias.Index + $alias.Length) + $canonical +
        $updatedBlock.Substring($alias.Index + $alias.Length)
}

$updated = $content
$edits = @(
    [pscustomobject]@{ Index = $http[0].Index; Length = $http[0].Length; Text = $updatedHttp },
    [pscustomobject]@{ Index = $https[0].Index; Length = $https[0].Length; Text = $updatedBlock }
) | Sort-Object Index -Descending
foreach ($edit in $edits) {
    $updated = $updated.Substring(0, $edit.Index) + $edit.Text + $updated.Substring($edit.Index + $edit.Length)
}
if ($updated -eq $content) {
    Write-Host 'El VirtualHost de pruebas ya apunta a Laravel y exige la contrasena adicional.'
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
