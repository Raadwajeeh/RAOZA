[CmdletBinding()]
param(
    [ValidateRange(1, 65535)]
    [int] $BackendPort = 8080,

    [ValidateRange(1, 65535)]
    [int] $VitePort = 5175,

    [switch] $Queue
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $projectRoot

foreach ($command in @('php', 'npm', 'npx')) {
    if (-not (Get-Command $command -ErrorAction SilentlyContinue)) {
        throw "Required command '$command' was not found on PATH."
    }
}

foreach ($port in @($BackendPort, $VitePort)) {
    $listener = Get-NetTCPConnection -State Listen -LocalPort $port -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($listener) {
        $process = Get-Process -Id $listener.OwningProcess -ErrorAction SilentlyContinue
        $processName = if ($process) { $process.ProcessName } else { 'unknown process' }
        throw "Port $port is already in use by $processName (PID $($listener.OwningProcess)). Stop that process or choose another port."
    }
}

$environment = (& php artisan env --no-ansi 2>&1 | Out-String)
if ($LASTEXITCODE -ne 0 -or $environment -notmatch '\[local\]') {
    throw 'The Windows local launcher only runs when Laravel resolves APP_ENV=local.'
}

# Process-scoped safety overrides. These do not edit .env and are inherited only
# by the local Laravel/Vite/queue child processes started below.
$env:APP_URL = "http://127.0.0.1:$BackendPort"
$env:PAYMENT_PROVIDER = 'demo'
$env:RAOZA_DEMO_MODE = 'true'
$env:MAIL_MAILER = 'log'
$env:ANALYTICS_PROVIDER = 'none'
$env:SESSION_SECURE_COOKIE = 'false'

$names = @('laravel', 'vite')
$colors = @('blue', 'magenta')
$commands = @(
    "php artisan serve --host=127.0.0.1 --port=$BackendPort --no-reload",
    "npm run dev -- --host 127.0.0.1 --port $VitePort --strictPort"
)

if ($Queue) {
    $names += 'queue'
    $colors += 'yellow'
    $commands += 'php artisan queue:work --tries=1'
}

Write-Host "Starting RAOZA at http://127.0.0.1:$BackendPort"
Write-Host "Vite HMR will use http://127.0.0.1:$VitePort"
Write-Host 'Local safety: demo payments, log-only mail, and no analytics.'
if (-not $Queue) {
    Write-Host 'The queue worker is disabled. Add -Queue when a journey needs queued jobs.'
}

& npx concurrently --kill-others --names ($names -join ',') --prefix-colors ($colors -join ',') @commands
exit $LASTEXITCODE
