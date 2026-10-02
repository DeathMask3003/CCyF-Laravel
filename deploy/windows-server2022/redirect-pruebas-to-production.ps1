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
    $hostBlocks = @($blocks | Where-Object {
        $_.Groups[1].Value -eq $port -and
        $_.Value -match '(?im)^[ \t]*ServerName[ \t]+pruebas\.cobaemex\.edu\.mx[ \t]*\r?$'
    })
    if ($hostBlocks.Count -ne 1) {
        throw "Se esperaba un solo VirtualHost de pruebas en el puerto $port. No se modifico Apache."
    }
    $block = $hostBlocks[0].Value
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
    $edits += [pscustomobject]@{ Index = $hostBlocks[0].Index; Length = $hostBlocks[0].Length; Original = $block; Text = $updatedBlock }
}

if ($edits.Count -eq 0) {
    Write-Host 'Ambos VirtualHost de pruebas ya tienen la redireccion temporal a produccion.'
    exit 0
}

$updated = $content
foreach ($edit in @($edits | Sort-Object Index -Descending)) {
    if ($updated.Substring($edit.Index, $edit.Length) -cne $edit.Original) {
        throw 'La posicion calculada no corresponde al VirtualHost de pruebas. No se modifico Apache.'
    }
    $updated = $updated.Substring(0, $edit.Index) + $edit.Text + $updated.Substring($edit.Index + $edit.Length)
}
$updatedBlocks = [regex]::Matches($updated, '(?is)<VirtualHost\s+\*:([0-9]+)\s*>.*?</VirtualHost>')
if ($updatedBlocks.Count -ne $blocks.Count) {
    throw 'Cambio inesperado en la cantidad de VirtualHost. No se modifico Apache.'
}
for ($index = 0; $index -lt $blocks.Count; $index++) {
    $isPruebas = $blocks[$index].Value -match '(?im)^[ \t]*ServerName[ \t]+pruebas\.cobaemex\.edu\.mx[ \t]*\r?$'
    if (-not $isPruebas -and $updatedBlocks[$index].Value -cne $blocks[$index].Value) {
        throw 'La simulacion alteraria otro VirtualHost. No se modifico Apache.'
    }
}
$updatedProduction = @([regex]::Matches($updated, '(?is)<VirtualHost\s+\*:443\s*>.*?</VirtualHost>') | Where-Object {
    $_.Value -match '(?im)^[ \t]*ServerName[ \t]+ccyf\.cobaemex\.edu\.mx[ \t]*\r?$'
})
if ($updatedProduction.Count -ne 1 -or $updatedProduction[0].Value -cne $production[0].Value) {
    throw 'La simulacion alteraria el VirtualHost de produccion. No se modifico Apache.'
}
$backup = "$vhosts.ccyf-pruebas-redirect-backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')-$(Get-Random)"
Copy-Item -LiteralPath $vhosts -Destination $backup -ErrorAction Stop
try {
    [System.IO.File]::WriteAllText($vhosts, $updated, $encoding)
    $after = Test-ApacheSyntax
    if ($after.Code -ne 0) { throw "Apache rechazo el cambio: $($after.Output)" }
} catch {
    Copy-Item -LiteralPath $backup -Destination $vhosts -Force
    $restored = Test-ApacheSyntax
    if ($restored.Code -ne 0) { throw "La restauracion del respaldo requiere revision: $($restored.Output)" }
    Write-Host 'Apache rechazo el cambio y se restauro el archivo anterior; sintaxis nuevamente valida.'
    throw
}

Write-Host 'Redireccion 302 preparada: pruebas.cobaemex.edu.mx -> ccyf.cobaemex.edu.mx'
Write-Host "Respaldo de Apache: $backup"
Write-Host 'Reinicia Apache y verifica los dominios. El script no reinicio el servicio.'
