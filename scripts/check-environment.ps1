$ErrorActionPreference = "Continue"

Write-Host ""
Write-Host "=== SheZen Environment Check ===" -ForegroundColor Cyan
Write-Host ""

function Test-Command {
    param(
        [string]$Name,
        [string[]]$VersionArgs
    )

    $cmd = Get-Command $Name -ErrorAction SilentlyContinue
    if (-not $cmd) {
        Write-Host "[MISSING] $Name" -ForegroundColor Red
        return $false
    }

    Write-Host "[FOUND]   $Name -> $($cmd.Source)" -ForegroundColor Green
    try {
        & $Name @VersionArgs
    } catch {
        Write-Host "          Could not read version: $($_.Exception.Message)" -ForegroundColor Yellow
    }
    Write-Host ""
    return $true
}

$php = Test-Command "php" @("-v")
$composer = Test-Command "composer" @("-V")
$flutter = Test-Command "flutter" @("--version")
$git = Test-Command "git" @("--version")
$mysql = Test-Command "mysql" @("--version")
$adb = Test-Command "adb" @("--version")

Write-Host "=== Summary ===" -ForegroundColor Cyan
if ($php -and $composer -and $flutter -and $git) {
    Write-Host "Core development commands are available." -ForegroundColor Green
} else {
    Write-Host "One or more required development commands are missing." -ForegroundColor Yellow
    Write-Host "Read README.md before running bootstrap.ps1."
}

if (-not $mysql) {
    Write-Host "MySQL CLI was not found. You may still use MySQL Workbench, but MySQL Server must be installed." -ForegroundColor Yellow
}
if (-not $adb) {
    Write-Host "ADB was not found in PATH. Flutter may still find Android SDK through its own configuration." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Also run: flutter doctor" -ForegroundColor Cyan
