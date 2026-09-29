param(
    [Parameter(Mandatory = $true)] [string] $SourceRoot,
    [Parameter(Mandatory = $true)] [string] $DestinationRoot,
    [string] $WebRoot = 'C:\xampp\htdocs'
)

$ErrorActionPreference = 'Stop'
$source = (Resolve-Path -LiteralPath $SourceRoot).Path.TrimEnd('\')
$destination = [System.IO.Path]::GetFullPath($DestinationRoot).TrimEnd('\')
$web = [System.IO.Path]::GetFullPath($WebRoot).TrimEnd('\')

if ($destination.Equals($source, [System.StringComparison]::OrdinalIgnoreCase) -or
    $destination.StartsWith($source + '\', [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'El destino no puede estar dentro del origen.'
}
if ($destination.Equals($web, [System.StringComparison]::OrdinalIgnoreCase) -or
    $destination.StartsWith($web + '\', [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'El destino de los expedientes debe quedar fuera de htdocs.'
}

$folders = @(
    'assets\documents',
    'reportes',
    'ccyf\e-signs',
    'assets\images\users',
    'assets\quejas'
)

foreach ($folder in $folders) {
    $from = Join-Path $source $folder
    $to = Join-Path $destination $folder
    if (-not (Test-Path -LiteralPath $from -PathType Container)) {
        throw "Falta el directorio de origen: $from"
    }
    New-Item -ItemType Directory -Path $to -Force | Out-Null
    & robocopy $from $to /E /COPY:DAT /DCOPY:DAT /R:2 /W:2 /NP
    if ($LASTEXITCODE -ge 8) {
        throw "Error de copia ($LASTEXITCODE): $folder"
    }
    $sourceCount = @(Get-ChildItem -LiteralPath $from -File -Recurse).Count
    $targetCount = @(Get-ChildItem -LiteralPath $to -File -Recurse).Count
    if ($targetCount -lt $sourceCount) {
        throw "Faltan archivos tras la copia: $folder ($sourceCount origen, $targetCount destino)"
    }
    Write-Host "OK ${folder}: $sourceCount archivos de origen, $targetCount en destino"
}

Write-Host 'Copia terminada. Conserva el origen y verifica la apertura de expedientes desde Laravel.'
