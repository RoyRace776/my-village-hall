param(
    [Parameter(Mandatory)][string]$Version,
    [Parameter(Mandatory)][string]$Path
)

$content = Get-Content -Path $Path -Raw

# Update plugin header  "* Version: x.y.z"
$content = $content -replace '(?m)^(\s*\*\s*Version:\s*).*$', "`${1}$Version"

# Update PHP constant  define( 'MYVH_VERSION', 'x.y.z' );
$content = $content -replace '(?m)^(define\(\s*''MYVH_VERSION''\s*,\s*'').*?(''[\s]*\)[\s]*;)', "`${1}$Version`${2}"

$utf8 = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($Path, $content, $utf8)

Write-Host "Version set to $Version in $Path"
