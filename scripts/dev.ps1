[CmdletBinding()]
param(
    [Parameter(Position = 0)]
    [ValidateSet('setup', 'config', 'start', 'stop', 'status', 'install', 'build', 'test', 'migrate', 'db-check', 'test-db-check', 'test-reset', 'config-clear', 'logs', 'remove')]
    [string] $Action = 'status',
    [int] $AppPort = 0,
    [int] $DatabasePort = 0,
    [switch] $ConfirmTestReset,
    [switch] $ConfirmRemoveVolumes
)

$ErrorActionPreference = 'Stop'
$taskRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).ProviderPath
$taskCanonical = $taskRoot.TrimEnd('\', '/').ToLowerInvariant()
$taskHash = [Security.Cryptography.SHA256]::Create()
$taskId = ([BitConverter]::ToString($taskHash.ComputeHash([Text.Encoding]::UTF8.GetBytes($taskCanonical)))).Replace('-', '').ToLowerInvariant().Substring(0, 12)
$taskProject = "cetakin-$taskId"
$taskEnvPath = Join-Path $taskRoot '.env.docker'
$taskComposePath = Join-Path $taskRoot 'compose.yaml'
Get-Command docker -ErrorAction Stop | Out-Null
& docker compose version | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Docker Compose v2 is required.' }
if ($env:DOCKER_HOST) { throw 'Unset DOCKER_HOST. These commands require local Docker Desktop, not a remote daemon.' }
$taskDockerEndpoint = (& docker context inspect --format '{{.Endpoints.docker.Host}}' | Out-String).Trim()
if ($LASTEXITCODE -ne 0 -or $taskDockerEndpoint -ne 'npipe:////./pipe/dockerDesktopLinuxEngine') {
    throw 'Select the local Docker Desktop Linux context (docker context use desktop-linux). Remote/test-production endpoints are refused.'
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
    )
    [IO.File]::WriteAllLines($taskEnvPath, $taskEnvironment, [Text.UTF8Encoding]::new($false))
    Write-Output "Created ignored .env.docker for $taskProject. App port $AppPort; PostgreSQL port $DatabasePort."
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
if ($taskSettings.Count -ne $taskKeys.Count) { throw 'Unexpected environment keys; use the local example.' }
foreach ($taskSettingKey in $taskKeys) { if (-not $taskSettings.ContainsKey($taskSettingKey) -or $taskSettings[$taskSettingKey] -eq '') { throw "Missing local setting $taskSettingKey." } }
if ($taskSettings.DEV_PROJECT -ne $taskProject -or $taskSettings.DEV_WORKTREE_ID -ne $taskId) { throw 'Environment belongs to a different worktree. Generate a new one in this checkout.' }
foreach ($taskPortKey in @('DEV_APP_PORT', 'DEV_DB_PORT')) {
    if ($taskSettings[$taskPortKey] -notmatch '^\d+$' -or [int]$taskSettings[$taskPortKey] -lt 1024 -or [int]$taskSettings[$taskPortKey] -gt 65535) { throw 'Invalid local port.' }
}
if ($taskSettings.DEV_APP_PORT -eq $taskSettings.DEV_DB_PORT) { throw 'Application and database ports must differ.' }

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
    $taskTestEnvironment = @('-e', 'APP_ENV=testing', '-e', 'DB_DATABASE=cetakin_test', '-e', 'DB_USERNAME=cetakin_test', '-e', "DB_PASSWORD=$($taskSettings.DEV_TEST_PASSWORD)")
    switch ($Action) {
        'config' { Write-Output 'Compose configuration is valid.' }
        'start' { Invoke-TaskCompose -Arguments @('up', '--detach', '--build', '--wait', 'app', 'postgres') }
        'stop' { Invoke-TaskCompose -Arguments @('down') }
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
            Invoke-TaskCompose -Arguments (@('exec', '-T') + $taskTestEnvironment + @('app', 'composer', 'test:bootstrap'))
        }
        'migrate' { Invoke-TaskCompose -Arguments @('exec', '-T', 'app', 'php', 'scripts/dev/database.php', 'migrate-dev') }
        'db-check' { Invoke-TaskCompose -Arguments @('exec', '-T', 'app', 'php', 'scripts/dev/database.php', 'dev-check') }
        'test-db-check' { Invoke-TaskCompose -Arguments (@('exec', '-T') + $taskTestEnvironment + @('app', 'php', 'scripts/dev/database.php', 'test-check')) }
        'test-reset' {
            if (-not $ConfirmTestReset) { throw 'Reset requires -ConfirmTestReset and targets only this worktree test database.' }
            Invoke-TaskCompose -Arguments (@('exec', '-T') + $taskTestEnvironment + @('-e', 'CETAKIN_TEST_RESET=confirmed', 'app', 'php', 'scripts/dev/database.php', 'reset-test'))
        }
        'config-clear' { Invoke-TaskCompose -Arguments @('exec', '-T', 'app', 'php', 'artisan', 'config:clear') }
        'logs' { Invoke-TaskCompose -Arguments @('logs', '--tail', '100', 'app', 'postgres') }
        'remove' {
            if (-not $ConfirmRemoveVolumes) { throw 'Removing all local volumes requires -ConfirmRemoveVolumes. This deletes this worktree databases, dependencies and private storage.' }
            Invoke-TaskCompose -Arguments @('--profile', 'tools', 'down', '--volumes', '--remove-orphans')
        }
    }
} finally {
    Set-Location -LiteralPath $taskPreviousLocation.Path
    foreach ($taskSettingKey in $taskKeys) { [Environment]::SetEnvironmentVariable($taskSettingKey, $taskPreviousEnvironment[$taskSettingKey], 'Process') }
}
