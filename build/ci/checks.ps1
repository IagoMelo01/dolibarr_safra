param(
    [string]$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
)

$ErrorActionPreference = 'Stop'
Set-Location $ProjectRoot

$php = (Get-Command php -ErrorAction Stop).Source
Write-Host "Using PHP: $php"

$lintFailures = New-Object System.Collections.Generic.List[string]
$phpFiles = Get-ChildItem -Recurse -File -Include *.php | Where-Object {
    $_.FullName -notmatch '\\\.git\\'
}

foreach ($file in $phpFiles) {
    & $php -l $file.FullName | Out-Null
    if ($LASTEXITCODE -ne 0) {
        $lintFailures.Add($file.FullName)
    }
}

if ($lintFailures.Count -gt 0) {
    Write-Host "PHP lint failures:"
    $lintFailures | ForEach-Object { Write-Host " - $_" }
    throw "Lint stage failed."
}

Write-Host 'Lint stage passed.'

& $php tests/run.php
if ($LASTEXITCODE -ne 0) {
    throw "Test stage failed."
}

Write-Host 'Test stage passed.'

$requiredPaths = @(
    'upgrade.php',
    'class/api_sfactivities.class.php',
    'sql/migrations/20260409_migrate_aplicacao_to_activity.sql',
    'sql/mysql/activity.sql'
)

foreach ($relativePath in $requiredPaths) {
    if (-not (Test-Path $relativePath)) {
        throw "Required file missing: $relativePath"
    }
}

$packConfig = 'build/makepack-safra.conf'
if (Test-Path $packConfig) {
    Write-Host "Packaging config found: $packConfig"
} else {
    throw "Packaging config missing: $packConfig"
}

Write-Host 'All local checks passed.'
