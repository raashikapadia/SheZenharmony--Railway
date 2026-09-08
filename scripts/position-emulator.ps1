# Moves the running Android emulator window fully on-screen without resizing it.
#
# The Pixel_8 AVD is 1080x2400, which is taller than a 720p host display, so the
# emulator places its window partly above the top of the screen and the title bar
# becomes unreachable. This only changes the window position: resizing the window
# from outside the emulator desyncs its touch input mapping, so the size is left
# exactly as the emulator set it.

$ErrorActionPreference = "Stop"

Add-Type @"
using System;
using System.Runtime.InteropServices;
public class EmuWin {
    [DllImport("user32.dll")] public static extern bool MoveWindow(IntPtr h, int x, int y, int w, int t, bool repaint);
    [DllImport("user32.dll")] public static extern bool GetWindowRect(IntPtr h, out RECT r);
    [DllImport("user32.dll")] public static extern bool SetForegroundWindow(IntPtr h);
    public struct RECT { public int Left, Top, Right, Bottom; }
}
"@

$proc = Get-Process -Name qemu-system-x86_64 -ErrorAction SilentlyContinue |
    Where-Object { $_.MainWindowHandle -ne 0 } |
    Select-Object -First 1

if (-not $proc) {
    Write-Host "No running emulator window found. Start the emulator first." -ForegroundColor Yellow
    exit 1
}

$handle = $proc.MainWindowHandle
$rect = New-Object EmuWin+RECT
[void][EmuWin]::GetWindowRect($handle, [ref]$rect)

$width = $rect.Right - $rect.Left
$height = $rect.Bottom - $rect.Top

Write-Host ("Before: x={0} y={1} w={2} h={3}" -f $rect.Left, $rect.Top, $width, $height)

# Keep the original width/height so touch input stays correctly mapped.
[void][EmuWin]::MoveWindow($handle, 0, 0, $width, $height, $true)
[void][EmuWin]::SetForegroundWindow($handle)

Start-Sleep -Milliseconds 400
[void][EmuWin]::GetWindowRect($handle, [ref]$rect)

Write-Host ("After:  x={0} y={1} w={2} h={3}" -f $rect.Left, $rect.Top, ($rect.Right - $rect.Left), ($rect.Bottom - $rect.Top))
Write-Host "Emulator window moved on-screen (size unchanged)." -ForegroundColor Green
