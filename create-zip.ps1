param(
    [Parameter(Mandatory)][string]$SourceDir,
    [Parameter(Mandatory)][string]$DestinationZip
)

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

if (Test-Path $DestinationZip) { Remove-Item $DestinationZip -Force }

$sourceDir = (Resolve-Path $SourceDir).Path.TrimEnd('\')
$parentDir = Split-Path $sourceDir -Parent

$stream  = [System.IO.File]::Open($DestinationZip, [System.IO.FileMode]::Create)
$archive = New-Object System.IO.Compression.ZipArchive($stream, [System.IO.Compression.ZipArchiveMode]::Create)

Get-ChildItem -Path $sourceDir -Recurse -Force | ForEach-Object {
    $fullPath    = $_.FullName
    # Build entry name with forward slashes, relative to the parent of the source folder
    $entryName   = ($fullPath.Substring($parentDir.Length + 1)) -replace '\\', '/'

    if ($_.PSIsContainer) {
        # Directory entry must end with /
        $entry = $archive.CreateEntry("$entryName/")
    } else {
        $entry        = $archive.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)
        $entryStream  = $entry.Open()
        $fileStream   = [System.IO.File]::OpenRead($fullPath)
        $fileStream.CopyTo($entryStream)
        $fileStream.Dispose()
        $entryStream.Dispose()
    }
}

$archive.Dispose()
$stream.Dispose()

Write-Host "Created $DestinationZip with forward-slash entry paths"
