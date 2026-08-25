# Kept as a compatibility entry point for developers using the old command.
& (Join-Path $PSScriptRoot "run-mobile.ps1") @args
exit $LASTEXITCODE
