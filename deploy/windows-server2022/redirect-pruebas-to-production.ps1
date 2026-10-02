param([string] $XamppRoot = 'C:\xampp')

$ErrorActionPreference = 'Stop'
$apache = Join-Path $XamppRoot 'apache\bin\httpd.exe'
$vhosts = Join-Path $XamppRoot 'apache\conf\extra\httpd-vhosts.conf'
$encoding = [System.Text.Encoding]::GetEncoding(28591)
$target = 'https://ccyf.cobaemex.edu.mx%{REQUEST_URI}'
$rule = "RewriteRule ^ $target [R=302,L,NE]"

foreach ($path in @($apache, $vhosts)) {
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { throw "Falta el archivo: $path" }
}

function Test-ApacheSyntax {
    $ErrorActionPreference = 'Continue'
    $output = & $apache -t 2>&1 | Out-String
    return @{ Code = $LASTEXITCODE; Output = $output.Trim() }
}

$before = Test-ApacheSyntax
if ($before.Code -ne 0) { throw "Apache ya tiene errores de configuracion: $($before.Output)" }

$content = [System.IO.File]::ReadAllText($vhosts, $encoding)
$blocks = [regex]::Matches($content, '(?is)<VirtualHost\s+\*:([0-9]+)\s*>.*?</VirtualHost>')
$production = @($blocks | Where-Object {
    $_.Groups[1].Value -eq '443' -and
    $_.Value -match '(?im)^[ \t]*ServerName[ \t]+ccyf\.cobaemex\.edu\.mx[ \t]*\r?$'
})
if ($production.Count -ne 1 -or
    $production[0].Value -notmatch '(?im)^[ \t]*DocumentRoot[ \t]+"C:/xampp/htdocs/ccyf-laravel/public"[ \t]*\r?$') {
    throw 'Produccion no apunta al checkout Laravel esperado. No se modifico Apache.'
}

$edits = @()
foreach ($port in @('80', '443')) {
    $matches = @($blocks | Where-Object {
        $_.Groups[1].Value -eq $port -and
        $_.Value -match '(?im)^[ \t]*ServerName[ \t]+pruebas\.cobaemex\.edu\.mx[ \t]*\r?$'
    })
    if ($matches.Count -ne 1) {
        throw "Se esperaba un solo VirtualHost de pruebas en el puerto $port. No se modifico Apache."
    }
    $block = $matches[0].Value
    if ($port -eq '443' -and
        ($block -notmatch '(?im)^[ \t]*SSLEngine[ \t]+on[ \t]*\r?$' -or
         $block -notmatch '(?im)^[ \t]*SSLCertificateFile[ \t]+' -or
         $block -notmatch '(?im)^[ \t]*SSLCertificateKeyFile[ \t]+')) {
        throw 'El VirtualHost HTTPS de pruebas no tiene los certificados esperados.'
    }
    if ($block.Contains($rule)) { continue }
    $serverName = [regex]::Matches($block, '(?m)^[ \t]*ServerName[ \t]+pruebas\.cobaemex\.edu\.mx[ \t]*(?:\r?\n|$)')
    if ($serverName.Count -ne 1) { throw "No se encontro el ServerName esperado en el puerto $port." }
    $newline = if ($block.Contains("`r`n")) { "`r`n" } else { "`n" }
    $redirect = "    RewriteEngine On${newline}    $rule${newline}"
    $updatedBlock = $block.Insert($serverName[0].Index + $serverName[0].Length, $redirect)
    $edits += [pscustomobject]@{ Index = $matches[0].Index; Length = $matches[0].Length; Text = $updatedBlock }
}

if ($edits.Count -eq 0) {
    Write-Host 'Ambos VirtualHost de pruebas ya tienen la redireccion temporal a produccion.'
    exit 0
}

$updated = $content
foreach ($edit in @($edits | Sort-Object Index -Descending)) {
    $updated = $updated.Substring(0, $edit.Index) + $edit.Text + $updated.Substring($edit.Index + $edit.Length)
}
$backup = "$vhosts.ccyf-pruebas-redirect-backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')-$(Get-Random)"
Copy-Item -LiteralPath $vhosts -Destination $backup -ErrorAction Stop
try {
    [System.IO.File]::WriteAllText($vhosts, $updated, $encoding)
    $after = Test-ApacheSyntax
    if ($after.Code -ne 0) { throw "Apache rechazo el cambio: $($after.Output)" }
} catch {
    Copy-Item -LiteralPath $backup -Destination $vhosts -Force
    throw
}

Write-Host 'Redireccion 302 preparada: pruebas.cobaemex.edu.mx -> ccyf.cobaemex.edu.mx'
Write-Host "Respaldo de Apache: $backup"
Write-Host 'Reinicia Apache y verifica los dominios. El script no reinicio el servicio.'
