$ErrorActionPreference = "Stop"

$Root = Split-Path -Parent $PSScriptRoot
$EnvFile = Join-Path $Root "backend\.env"
$EnvExample = Join-Path $Root "backend\.env.example"

if (-not (Test-Path (Join-Path $Root "backend\artisan"))) {
    throw "Laravel backend does not exist yet. Run .\scripts\bootstrap.ps1 first."
}

if (-not (Test-Path $EnvFile)) {
    Copy-Item $EnvExample $EnvFile
}

function Read-WithDefault {
    param([string]$Prompt, [string]$Default)
    $value = Read-Host "$Prompt [$Default]"
    if ([string]::IsNullOrWhiteSpace($value)) { return $Default }
    return $value
}

$hostName = Read-WithDefault "DB host" "127.0.0.1"
$port = Read-WithDefault "DB port" "3306"
$database = Read-WithDefault "DB name" "shezen_harmony"
$username = Read-WithDefault "DB username" "root"
$secure = Read-Host "DB password" -AsSecureString
$bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
try {
    $password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
}

$content = Get-Content $EnvFile -Raw

function Set-EnvValue {
    param([string]$Text, [string]$Key, [string]$Value)

    # Quote values so spaces and special characters are handled by dotenv.
    $escaped = $Value.Replace('\', '\\').Replace('"', '\"')
    $replacement = "$Key=`"$escaped`""

    if ($Text -match "(?m)^$([regex]::Escape($Key))=.*$") {
        return [regex]::Replace(
            $Text,
            "(?m)^$([regex]::Escape($Key))=.*$",
            [System.Text.RegularExpressions.MatchEvaluator]{ param($m) $replacement }
        )
    }
    return $Text.TrimEnd() + "`r`n$replacement`r`n"
}

$content = Set-EnvValue $content "DB_CONNECTION" "mysql"
$content = Set-EnvValue $content "DB_HOST" $hostName
$content = Set-EnvValue $content "DB_PORT" $port
$content = Set-EnvValue $content "DB_DATABASE" $database
$content = Set-EnvValue $content "DB_USERNAME" $username
$content = Set-EnvValue $content "DB_PASSWORD" $password

Set-Content $EnvFile $content -Encoding UTF8

Push-Location (Join-Path $Root "backend")
php artisan config:clear
Pop-Location

Write-Host ""
Write-Host "Database settings saved to backend\.env (Git ignored)." -ForegroundColor Green
Write-Host "Now run:"
Write-Host "  cd backend"
Write-Host "  php artisan migrate"
Write-Host "  php artisan db:seed"
