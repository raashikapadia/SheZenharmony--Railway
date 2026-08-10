$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Frontend = Join-Path $Root "frontend"

if (-not (Test-Path (Join-Path $Frontend "pubspec.yaml"))) {
    throw "Frontend not found. Run .\scripts\bootstrap.ps1 first."
}

Push-Location $Frontend
flutter devices
Write-Host ""
Write-Host "Starting Flutter using the Android-emulator API address..." -ForegroundColor Cyan
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
Pop-Location
