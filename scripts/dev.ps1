[CmdletBinding()]
param(
    [Parameter(Position = 0)]
    [ValidateSet('setup', 'config', 'start', 'stop', 'status', 'install', 'build', 'test', 'quality', 'migrate', 'db-check', 'test-db-check', 'test-reset', 'config-clear', 'logs', 'remove', 'storage-start', 'storage-check')]
    [string] $Action = 'status',
    [int] $AppPort = 0,
    [int] $DatabasePort = 0,
    [switch] $ConfirmTestReset,
    [switch] $ConfirmRemoveVolumes
)

$ErrorActionPreference = 'Stop'
$taskRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).ProviderPath
$taskWindows = [Environment]::OSVersion.Platform -eq [PlatformID]::Win32NT
$taskCanonical = $taskRoot.TrimEnd('\', '/')
if ($taskWindows) { $taskCanonical = $taskCanonical.ToLowerInvariant() }
$taskHash = [Security.Cryptography.SHA256]::Create()
$taskId = ([BitConverter]::ToString($taskHash.ComputeHash([Text.Encoding]::UTF8.GetBytes($taskCanonical)))).Replace('-', '').ToLowerInvariant().Substring(0, 12)
$taskProject = "cetakin-$taskId"
$taskEnvPath = Join-Path $taskRoot '.env.docker'
$taskComposePath = Join-Path $taskRoot 'compose.yaml'
Get-Command docker -ErrorAction Stop | Out-Null
& docker compose version | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Docker Compose v2 is required.' }
if ($env:DOCKER_HOST) { throw 'Unset DOCKER_HOST. These commands require the local Docker daemon, not a remote endpoint.' }
$taskDockerEndpoint = (& docker context inspect --format '{{.Endpoints.docker.Host}}' | Out-String).Trim()
$taskExpectedEndpoint = 'unix:///var/run/docker.sock'
if ($taskWindows) { $taskExpectedEndpoint = 'npipe:////./pipe/dockerDesktopLinuxEngine' }
if ($LASTEXITCODE -ne 0 -or $taskDockerEndpoint -ne $taskExpectedEndpoint) {
    throw 'Select local Docker Desktop Linux on Windows or the local Unix Docker socket on Linux. Remote endpoints are refused.'
}

function Invoke-TaskCompose {
    param([string[]] $Arguments)
    & docker compose --env-file $taskEnvPath --project-name $taskProject --file $taskComposePath @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Docker Compose action failed (exit $LASTEXITCODE)." }
}

if ($Action -eq 'setup') {
    if (Test-Path -LiteralPath $taskEnvPath) { throw '.env.docker already exists. Inspect it; setup never overwrites an existing environment.' }
    $taskOffset = [Convert]::ToInt32($taskId.Substring(0, 4), 16) % 10000
    if ($AppPort -eq 0) { $AppPort = 20000 + $taskOffset }
    if ($DatabasePort -eq 0) { $DatabasePort = 30000 + $taskOffset }
    if ($AppPort -eq $DatabasePort -or $AppPort -lt 1024 -or $AppPort -gt 65535 -or $DatabasePort -lt 1024 -or $DatabasePort -gt 65535) { throw 'Choose two distinct ports from 1024 through 65535.' }
    foreach ($taskPort in @($AppPort, $DatabasePort)) {
        $taskListener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, $taskPort)
        try { $taskListener.Start() } catch { throw "Port $taskPort is occupied. Choose explicit -AppPort and -DatabasePort values." } finally { $taskListener.Stop() }
    }
    $taskKeyBytes = New-Object byte[] 32
    $taskRandom = [Security.Cryptography.RandomNumberGenerator]::Create()
    $taskRandom.GetBytes($taskKeyBytes)
    $taskRandom.Dispose()
    $taskEnvironment = @(
        '# Generated local-only environment; never copy between worktrees.'
        "DEV_PROJECT=$taskProject"
        "DEV_WORKTREE_ID=$taskId"
        "DEV_APP_PORT=$AppPort"
        "DEV_DB_PORT=$DatabasePort"
        ('DEV_APP_KEY=base64:' + [Convert]::ToBase64String($taskKeyBytes))
        'DEV_ADMIN_PASSWORD=cetakin_local_admin_only'
        'DEV_DB_PASSWORD=cetakin_local_dev_only'
        'DEV_TEST_PASSWORD=cetakin_local_test_only'
        'DEV_DESIGN_OBJECT_DRIVER=local'
    )
    [IO.File]::WriteAllLines($taskEnvPath, $taskEnvironment, [Text.UTF8Encoding]::new($false))
    Write-Output "Created ignored .env.docker for $taskProject. Browser URL http://$taskProject.localhost`:$AppPort; PostgreSQL port $DatabasePort."
    exit 0
}

if (-not (Test-Path -LiteralPath $taskEnvPath)) { throw 'Run .\scripts\dev.ps1 setup first.' }
$taskSettings = @{}
foreach ($taskLine in [IO.File]::ReadAllLines($taskEnvPath)) {
    if ($taskLine.Trim() -eq '' -or $taskLine.StartsWith('#')) { continue }
    $taskPair = $taskLine.Split('=', 2)
    if ($taskPair.Length -ne 2 -or $taskSettings.ContainsKey($taskPair[0])) { throw 'Invalid or duplicate setting in .env.docker.' }
    $taskSettings[$taskPair[0]] = $taskPair[1]
}
$taskKeys = @('DEV_PROJECT', 'DEV_WORKTREE_ID', 'DEV_APP_PORT', 'DEV_DB_PORT', 'DEV_APP_KEY', 'DEV_ADMIN_PASSWORD', 'DEV_DB_PASSWORD', 'DEV_TEST_PASSWORD')
# Existing CET-002 environments retain the local adapter without regeneration.
if (-not $taskSettings.ContainsKey('DEV_DESIGN_OBJECT_DRIVER')) { $taskSettings.DEV_DESIGN_OBJECT_DRIVER = 'local' }
$taskKeys += 'DEV_DESIGN_OBJECT_DRIVER'
if ($taskSettings.Count -ne $taskKeys.Count) { throw 'Unexpected environment keys; use the local example.' }
foreach ($taskSettingKey in $taskKeys) { if (-not $taskSettings.ContainsKey($taskSettingKey) -or $taskSettings[$taskSettingKey] -eq '') { throw "Missing local setting $taskSettingKey." } }
if ($taskSettings.DEV_PROJECT -ne $taskProject -or $taskSettings.DEV_WORKTREE_ID -ne $taskId) { throw 'Environment belongs to a different worktree. Generate a new one in this checkout.' }
foreach ($taskPortKey in @('DEV_APP_PORT', 'DEV_DB_PORT')) {
    if ($taskSettings[$taskPortKey] -notmatch '^\d+$' -or [int]$taskSettings[$taskPortKey] -lt 1024 -or [int]$taskSettings[$taskPortKey] -gt 65535) { throw 'Invalid local port.' }
}
if ($taskSettings.DEV_APP_PORT -eq $taskSettings.DEV_DB_PORT) { throw 'Application and database ports must differ.' }
if ($taskSettings.DEV_DESIGN_OBJECT_DRIVER -notin @('local', 's3')) { throw 'Choose local or s3 for the synthetic design adapter.' }

# Explicitly set interpolation values: inherited shell variables must not redirect targets.
$taskPreviousEnvironment = @{}
foreach ($taskSettingKey in $taskKeys) {
    $taskPreviousEnvironment[$taskSettingKey] = [Environment]::GetEnvironmentVariable($taskSettingKey, 'Process')
    [Environment]::SetEnvironmentVariable($taskSettingKey, $taskSettings[$taskSettingKey], 'Process')
}
$taskPreviousLocation = Get-Location
try {
    Set-Location -LiteralPath $taskRoot
    Invoke-TaskCompose -Arguments @('config', '--quiet')
    $taskTestEnvironment = @('-e', 'APP_ENV=testing', '-e', 'DESIGN_OBJECT_DRIVER=local', '-e', 'DB_DATABASE=cetakin_test', '-e', 'DB_USERNAME=cetakin_test', '-e', "DB_PASSWORD=$($taskSettings.DEV_TEST_PASSWORD)")
    $taskTestEnvironment += @(
        '-e', 'CETAKIN_OBJECT_TEST_ENDPOINT=http://objectstore:8333',
        '-e', "CETAKIN_OBJECT_TEST_BUCKET=cetakin-test-$taskId",
        '-e', "CETAKIN_OBJECT_TEST_KEY=$taskProject-test",
        '-e', "CETAKIN_OBJECT_TEST_SECRET=$taskProject-test-synthetic-only",
        '-e', 'CETAKIN_OBJECT_TEST_REGION=us-east-1',
        '-e', "CETAKIN_OBJECT_TEST_DENIED_KEY=$taskProject-readonly",
        '-e', "CETAKIN_OBJECT_TEST_DENIED_SECRET=$taskProject-readonly-synthetic-only",
        '-e', 'CETAKIN_OBJECT_TEST_DISPOSABLE=1'
    )
    function Start-TaskObjects {
        Invoke-TaskCompose -Arguments @('--profile', 'objects', 'up', '--detach', '--wait', 'objectstore')
        Invoke-TaskCompose -Arguments @('exec', '-T', 'app', 'php', 'scripts/dev/object-store.php', 'init')
    }
    switch ($Action) {
        'config' { Write-Output "Compose configuration is valid. Browser URL http://$taskProject.localhost`:$($taskSettings.DEV_APP_PORT)." }
        'start' {
            Invoke-TaskCompose -Arguments @('up', '--detach', '--build', '--wait', 'app', 'postgres')
            if ($taskSettings.DEV_DESIGN_OBJECT_DRIVER -eq 's3') { Start-TaskObjects }
        }
        'stop' { Invoke-TaskCompose -Arguments @('--profile', 'objects', 'down') }
        'status' { Invoke-TaskCompose -Arguments @('ps', '--all') }
        'install' {
            Invoke-TaskCompose -Arguments @('build', 'app')
            Invoke-TaskCompose -Arguments @('run', '--rm', '--no-deps', 'app', 'composer', 'install', '--no-interaction')
            Invoke-TaskCompose -Arguments @('run', '--rm', '--no-deps', 'node', 'npm', 'ci', '--ignore-scripts')
        }
        'build' {
            Invoke-TaskCompose -Arguments @('run', '--rm', '--no-deps', 'node', 'npm', 'run', 'typecheck')
            Invoke-TaskCompose -Arguments @('run', '--rm', '--no-deps', 'node', 'npm', 'run', 'build')
        }
        'test' {
            Start-TaskObjects
            Invoke-TaskCompose -Arguments (@('exec', '-T') + $taskTestEnvironment + @('app', 'php', 'scripts/dev/database.php', 'test-check'))
            Invoke-TaskCompose -Arguments (@('exec', '-T') + $taskTestEnvironment + @('app', 'composer', 'test:bootstrap'))
            Invoke-TaskCompose -Arguments (@('exec', '-T') + $taskTestEnvironment + @('app', 'composer', 'test:postgres'))
        }
        'quality' {
            # Inventory runs on the host: Windows worktree .git pointers cannot be used inside Linux containers.
            & (Join-Path $PSScriptRoot 'check-secrets.ps1')
            Start-TaskObjects
            Invoke-TaskCompose -Arguments @('exec', '-T', 'app', 'composer', 'validate', '--no-check-publish')
            Invoke-TaskCompose -Arguments @('exec', '-T', 'app', 'composer', 'check-platform-reqs')
            Invoke-TaskCompose -Arguments @('exec', '-T', 'app', 'composer', 'lint:php')
            Invoke-TaskCompose -Arguments @('exec', '-T', 'app', 'composer', 'format:check')
            Invoke-TaskCompose -Arguments @('exec', '-T', 'app', 'composer', 'analyse')
            Invoke-TaskCompose -Arguments @('run', '--rm', '--no-deps', 'node', 'npm', 'run', 'format:check')
            Invoke-TaskCompose -Arguments @('run', '--rm', '--no-deps', 'node', 'npm', 'run', 'typecheck')
            Invoke-TaskCompose -Arguments @('run', '--rm', '--no-deps', 'node', 'npm', 'run', 'test:components')
            # A fresh checkout needs compiled assets before the existing HTTP/bootstrap smoke.
            Invoke-TaskCompose -Arguments @('run', '--rm', '--no-deps', 'node', 'npm', 'run', 'build')
            Invoke-TaskCompose -Arguments (@('exec', '-T') + $taskTestEnvironment + @('app', 'php', 'scripts/dev/database.php', 'test-check'))
            Invoke-TaskCompose -Arguments (@('exec', '-T') + $taskTestEnvironment + @('app', 'composer', 'test:bootstrap'))
            Invoke-TaskCompose -Arguments (@('exec', '-T') + $taskTestEnvironment + @('app', 'composer', 'test:postgres'))
            Invoke-TaskCompose -Arguments @('exec', '-T', 'app', 'composer', 'audit', '--locked')
            Invoke-TaskCompose -Arguments @('run', '--rm', '--no-deps', 'node', 'npm', 'audit', '--audit-level=high')
            Write-Output 'All application quality groups passed.'
        }
        'migrate' { Invoke-TaskCompose -Arguments @('exec', '-T', 'app', 'php', 'scripts/dev/database.php', 'migrate-dev') }
        'db-check' { Invoke-TaskCompose -Arguments @('exec', '-T', 'app', 'php', 'scripts/dev/database.php', 'dev-check') }
        'test-db-check' { Invoke-TaskCompose -Arguments (@('exec', '-T') + $taskTestEnvironment + @('app', 'php', 'scripts/dev/database.php', 'test-check')) }
        'test-reset' {
            if (-not $ConfirmTestReset) { throw 'Reset requires -ConfirmTestReset and targets only this worktree test database.' }
            Invoke-TaskCompose -Arguments (@('exec', '-T') + $taskTestEnvironment + @('-e', 'CETAKIN_TEST_RESET=confirmed', 'app', 'php', 'scripts/dev/database.php', 'reset-test'))
        }
        'config-clear' { Invoke-TaskCompose -Arguments @('exec', '-T', 'app', 'php', 'artisan', 'config:clear') }
        'storage-start' { Start-TaskObjects }
        'storage-check' { Invoke-TaskCompose -Arguments @('exec', '-T', 'app', 'php', 'scripts/dev/object-store.php', 'check') }
        'logs' { Invoke-TaskCompose -Arguments @('--profile', 'objects', 'logs', '--tail', '100', 'app', 'postgres', 'objectstore') }
        'remove' {
            if (-not $ConfirmRemoveVolumes) { throw 'Removing all local volumes requires -ConfirmRemoveVolumes. This deletes this worktree databases, dependencies and private storage.' }
            Invoke-TaskCompose -Arguments @('--profile', 'tools', '--profile', 'objects', 'down', '--volumes', '--remove-orphans')
        }
    }
} finally {
    Set-Location -LiteralPath $taskPreviousLocation.Path
    foreach ($taskSettingKey in $taskKeys) { [Environment]::SetEnvironmentVariable($taskSettingKey, $taskPreviousEnvironment[$taskSettingKey], 'Process') }
}
