$ErrorActionPreference = 'Stop'
# Same fail-closed aggregate on Windows and GitHub's Linux PowerShell runner.
& (Join-Path $PSScriptRoot 'dev.ps1') quality
if ($LASTEXITCODE -ne 0) { throw "Quality gate failed (exit $LASTEXITCODE)." }
