$ErrorActionPreference = 'Stop'
$secretRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).ProviderPath
$secretPreviousLocation = Get-Location
try {
    Set-Location -LiteralPath $secretRoot
    $secretFiles = @(& git -c "safe.directory=$secretRoot" -c core.quotepath=false ls-files --cached --others --exclude-standard)
    if ($LASTEXITCODE -ne 0 -or $secretFiles.Count -eq 0) { throw 'Cannot inspect the Git-visible source tree.' }
    $secretPatterns = @{
        'private key' = '-----BEGIN (?:RSA |EC |DSA |OPENSSH |PGP )?PRIVATE KEY(?: BLOCK)?-----'
        'GitHub token' = '(?:gh[pousr]_[A-Za-z0-9]{36,}|github_pat_[A-Za-z0-9_]{50,})'
        'AWS access key' = '(?:AKIA|ASIA)[A-Z0-9]{16}'
        'literal Laravel key' = 'APP_KEY\s*=\s*["'']?base64:[A-Za-z0-9+/]{43}='
    }
    $secretFailures = @()
    foreach ($secretFile in $secretFiles | Sort-Object -Unique) {
        if ($secretFile -match '(^|/)\.env(?:\..+)?$' -and $secretFile -notin @('.env.example', '.env.docker.example')) {
            $secretFailures += "${secretFile}: forbidden environment file"
        }
        if ($secretFile -match '(^|/)(vendor|node_modules|\.phpunit\.cache)(/|$)|^public/(build|hot)(/|$)|^storage/app/|\.(dump|backup|sqlite3?|sql(\.gz)?)$') {
            $secretFailures += "${secretFile}: forbidden generated/private data"
        }
        $secretPath = Join-Path $secretRoot $secretFile
        if (-not (Test-Path -LiteralPath $secretPath -PathType Leaf)) { continue }
        $secretText = [IO.File]::ReadAllText($secretPath)
        foreach ($secretPattern in $secretPatterns.GetEnumerator()) {
            if ($secretText -cmatch $secretPattern.Value) { $secretFailures += "${secretFile}: $($secretPattern.Key) detected" }
        }
    }
    if ($secretFailures.Count -gt 0) { throw ($secretFailures -join [Environment]::NewLine) }
    Write-Output "Secret/generated-file checks passed for $($secretFiles.Count) Git-visible files."
} finally {
    Set-Location -LiteralPath $secretPreviousLocation.Path
}
