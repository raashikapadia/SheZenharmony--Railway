[CmdletBinding()]
param(
    [string]$AvdName = "Pixel_8",
    [ValidateRange(30, 900)]
    [int]$BootTimeoutSeconds = 180,
    # Boot (or reuse) the emulator and exit without running Flutter. Used as the
    # VS Code preLaunchTask so debugging always targets the emulator.
    [switch]$BootOnly
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Frontend = Join-Path $Root "frontend"
$ApiBaseUrl = "http://10.0.2.2:8000/api"

function Resolve-AndroidTools {
    $sdkCandidates = [System.Collections.Generic.List[string]]::new()

    foreach ($sdkPath in @($env:ANDROID_SDK_ROOT, $env:ANDROID_HOME)) {
        if (-not [string]::IsNullOrWhiteSpace($sdkPath)) {
            $sdkCandidates.Add($sdkPath)
        }
    }

    try {
        $flutterConfig = & flutter config --list 2>$null
        foreach ($line in $flutterConfig) {
            if ($line -match '^\s*android-sdk:\s*(.+?)\s*$') {
                $sdkCandidates.Add($Matches[1])
            }
        }
    } catch {
        # Other SDK discovery methods below may still succeed.
    }

    if (-not [string]::IsNullOrWhiteSpace($env:LOCALAPPDATA)) {
        $sdkCandidates.Add((Join-Path $env:LOCALAPPDATA "Android\Sdk"))
    }

    $adbCommand = Get-Command adb -ErrorAction SilentlyContinue
    $emulatorCommand = Get-Command emulator -ErrorAction SilentlyContinue
    $adbPath = if ($adbCommand) { $adbCommand.Source } else { $null }
    $emulatorPath = if ($emulatorCommand) { $emulatorCommand.Source } else { $null }

    foreach ($sdkPath in ($sdkCandidates | Select-Object -Unique)) {
        if ([string]::IsNullOrWhiteSpace($adbPath)) {
            $candidate = Join-Path $sdkPath "platform-tools\adb.exe"
            if (Test-Path -LiteralPath $candidate) { $adbPath = $candidate }
        }
        if ([string]::IsNullOrWhiteSpace($emulatorPath)) {
            $candidate = Join-Path $sdkPath "emulator\emulator.exe"
            if (Test-Path -LiteralPath $candidate) { $emulatorPath = $candidate }
        }
    }

    if ([string]::IsNullOrWhiteSpace($adbPath) -or [string]::IsNullOrWhiteSpace($emulatorPath)) {
        throw "Android SDK tools were not found. Set ANDROID_SDK_ROOT (or ANDROID_HOME), or configure the Android SDK in Flutter/Android Studio."
    }

    return @{ Adb = $adbPath; Emulator = $emulatorPath }
}

function Get-RunningEmulatorIds {
    param([string]$AdbPath)

    # adb writes transient stderr noise during boot; under ErrorActionPreference
    # "Stop" Windows PowerShell would turn that into a terminating error.
    $ErrorActionPreference = "Continue"

    $ids = @()
    $deviceLines = & $AdbPath devices 2>$null
    foreach ($line in $deviceLines) {
        if ($line -match '^(emulator-\d+)\s+device$') {
            $ids += $Matches[1]
        }
    }
    return $ids
}

function Test-AndroidBooted {
    param([string]$AdbPath, [string]$DeviceId)

    # adb prints "error: closed" until the device finishes booting. That is an
    # expected polling state, not a script failure.
    $ErrorActionPreference = "Continue"

    $bootCompleted = (& $AdbPath -s $DeviceId shell getprop sys.boot_completed 2>$null | Out-String).Trim()
    # Images built with debug.sf.nobootanimation=1 never start the bootanim
    # service, so init.svc.bootanim stays empty instead of reaching "stopped".
    # Only a still-"running" animation means Android has not finished booting.
    $bootAnimation = (& $AdbPath -s $DeviceId shell getprop init.svc.bootanim 2>$null | Out-String).Trim()
    return ($bootCompleted -eq "1" -and $bootAnimation -ne "running")
}

function Test-FlutterDetectsAndroid {
    param([string]$DeviceId)

    $ErrorActionPreference = "Continue"

    try {
        $json = (& flutter devices --machine 2>$null | Out-String)
        if ([string]::IsNullOrWhiteSpace($json)) { return $false }
        $devices = $json | ConvertFrom-Json
        return [bool]($devices | Where-Object {
            $_.id -eq $DeviceId -and $_.targetPlatform -like 'android-*'
        })
    } catch {
        return $false
    }
}

function Wait-ForReadyEmulator {
    param([string]$AdbPath, [int]$TimeoutSeconds, [System.Diagnostics.Process]$LaunchProcess)

    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)
    while ((Get-Date) -lt $deadline) {
        if ($LaunchProcess -and $LaunchProcess.HasExited) {
            return @{ Status = "Exited"; ExitCode = $LaunchProcess.ExitCode; DeviceId = $null }
        }

        foreach ($deviceId in @(Get-RunningEmulatorIds -AdbPath $AdbPath)) {
            if ((Test-AndroidBooted -AdbPath $AdbPath -DeviceId $deviceId) -and
                (Test-FlutterDetectsAndroid -DeviceId $deviceId)) {
                return @{ Status = "Ready"; ExitCode = $null; DeviceId = $deviceId }
            }
        }
        Start-Sleep -Seconds 3
    }
    return @{ Status = "TimedOut"; ExitCode = $null; DeviceId = $null }
}

Write-Host "Checking Flutter..." -ForegroundColor Cyan
$flutterCommand = Get-Command flutter -ErrorAction SilentlyContinue
if (-not $flutterCommand) {
    throw "Flutter was not found in PATH. Install Flutter and confirm that 'flutter --version' works."
}

if (-not (Test-Path -LiteralPath (Join-Path $Frontend "pubspec.yaml"))) {
    throw "Flutter project not found at '$Frontend'."
}

Write-Host "Checking Android emulator..." -ForegroundColor Cyan
$androidTools = Resolve-AndroidTools
& $androidTools.Adb start-server | Out-Null
$deviceId = @(Get-RunningEmulatorIds -AdbPath $androidTools.Adb) | Select-Object -First 1

if ($deviceId) {
    Write-Host "Waiting for Android to boot..." -ForegroundColor Cyan
    $ready = Wait-ForReadyEmulator -AdbPath $androidTools.Adb -TimeoutSeconds $BootTimeoutSeconds -LaunchProcess $null
} else {
    $availableAvds = @(& $androidTools.Emulator -list-avds 2>$null | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
    if ($AvdName -notin $availableAvds) {
        Write-Host ""
        Write-Host "$AvdName Android emulator was not found." -ForegroundColor Red
        Write-Host ""
        Write-Host "Available emulators:"
        if ($availableAvds.Count -eq 0) { Write-Host "(none)" } else { $availableAvds | ForEach-Object { Write-Host $_ } }
        Write-Host ""
        throw "Create/import a $AvdName emulator in Android Studio Device Manager and try again."
    }

    Write-Host "Starting $AvdName..." -ForegroundColor Cyan
    $emulatorProcess = Start-Process -FilePath $androidTools.Emulator -ArgumentList @("-avd", $AvdName) -PassThru
    Write-Host "Waiting for Android to boot..." -ForegroundColor Cyan
    $ready = Wait-ForReadyEmulator -AdbPath $androidTools.Adb -TimeoutSeconds $BootTimeoutSeconds -LaunchProcess $emulatorProcess

    if ($ready.Status -eq "Exited") {
        Write-Warning "Normal emulator startup exited with code $($ready.ExitCode). Retrying once with a cold boot (saved data will not be wiped)."
        $emulatorProcess = Start-Process -FilePath $androidTools.Emulator -ArgumentList @("-avd", $AvdName, "-no-snapshot-load") -PassThru
        Write-Host "Waiting for Android to boot after cold-boot retry..." -ForegroundColor Cyan
        $ready = Wait-ForReadyEmulator -AdbPath $androidTools.Adb -TimeoutSeconds $BootTimeoutSeconds -LaunchProcess $emulatorProcess
    }
}

if ($ready.Status -ne "Ready") {
    if ($ready.Status -eq "Exited") {
        throw "The Android emulator exited with code $($ready.ExitCode), including the safe cold-boot retry. Open Android Studio Device Manager and test the AVD, then run 'flutter doctor -v' for SDK diagnostics. No emulator data was wiped."
    }
    throw "Android did not finish booting and appear in Flutter within $BootTimeoutSeconds seconds. Check the emulator window, Android Studio Device Manager, and 'flutter doctor -v', then try again."
}

$deviceId = $ready.DeviceId
Write-Host "Android emulator ready: $deviceId" -ForegroundColor Green

if ($BootOnly) {
    exit 0
}

Write-Host "Make sure Laravel is running in another terminal:" -ForegroundColor Yellow
Write-Host "  cd backend"
Write-Host "  php artisan serve"
Write-Host "Starting SheZen Harmony..." -ForegroundColor Cyan

Push-Location $Frontend
try {
    & flutter run -d $deviceId "--dart-define=API_BASE_URL=$ApiBaseUrl"
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
} finally {
    Pop-Location
}
