$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Backend = Join-Path $Root "backend"

if (-not (Test-Path (Join-Path $Backend "artisan"))) {
    throw "Backend not found. Follow the Backend Setup steps in README.md first."
}

Push-Location $Backend
Write-Host "Starting SheZen Laravel API on http://0.0.0.0:8000" -ForegroundColor Cyan
Write-Host "Windows browser health check: http://127.0.0.1:8000/api/health"
Write-Host "Android emulator API base: http://10.0.2.2:8000/api"
php artisan serve --host=0.0.0.0 --port=8000
Pop-Location
