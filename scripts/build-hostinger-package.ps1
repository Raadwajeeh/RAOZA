param(
    [string]$OutputDirectory = "artifacts"
)

$ErrorActionPreference = 'Stop'

function Invoke-ExternalCommand {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Command,

        [string[]]$ArgumentList = @(),

        [switch]$CaptureOutput
    )

    if ($CaptureOutput) {
        $commandOutput = & $Command @ArgumentList
        $exitCode = $LASTEXITCODE
        if ($exitCode -ne 0) {
            throw "External command failed with exit code ${exitCode}: $Command $($ArgumentList -join ' ')"
        }

        return ($commandOutput -join [Environment]::NewLine)
    }

    & $Command @ArgumentList
    $exitCode = $LASTEXITCODE
    if ($exitCode -ne 0) {
        throw "External command failed with exit code ${exitCode}: $Command $($ArgumentList -join ' ')"
    }
}

function Assert-WritableDirectory {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path
    )

    New-Item -ItemType Directory -Path $Path -Force | Out-Null
    $probe = Join-Path $Path ('.raoza-write-probe-' + [guid]::NewGuid().ToString('N'))
    try {
        [IO.File]::WriteAllText($probe, 'write-test', [Text.UTF8Encoding]::new($false))
    } finally {
        Remove-Item -LiteralPath $probe -Force -ErrorAction SilentlyContinue
    }
}

$repository = (Invoke-ExternalCommand -Command 'git' -ArgumentList @('rev-parse', '--show-toplevel') -CaptureOutput).Trim()
if (-not $repository) { throw 'Run this script inside the RAOZA Git repository.' }
if (Invoke-ExternalCommand -Command 'git' -ArgumentList @('-C', $repository, 'status', '--porcelain') -CaptureOutput) {
    throw 'The release worktree must be clean. Commit or stash reviewed changes first.'
}

$sha = (Invoke-ExternalCommand -Command 'git' -ArgumentList @('-C', $repository, 'rev-parse', 'HEAD') -CaptureOutput).Trim()
$shortSha = (Invoke-ExternalCommand -Command 'git' -ArgumentList @('-C', $repository, 'rev-parse', '--short=12', 'HEAD') -CaptureOutput).Trim()
$output = [IO.Path]::GetFullPath((Join-Path $repository $OutputDirectory))
$temporary = Join-Path ([IO.Path]::GetTempPath()) ("raoza-release-" + [guid]::NewGuid().ToString('N'))
$sourceZip = Join-Path $temporary 'source.zip'
$stage = Join-Path $temporary 'app'
$archiveName = "raoza-$shortSha.zip"
$archive = Join-Path $output $archiveName
$checksum = "$archive.sha256"
$candidateArchive = Join-Path $temporary $archiveName
$candidateChecksum = "$candidateArchive.sha256"
$published = $false

try {
    New-Item -ItemType Directory -Path $output -Force | Out-Null
    Remove-Item -LiteralPath $archive -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $checksum -Force -ErrorAction SilentlyContinue
    New-Item -ItemType Directory -Path $stage -Force | Out-Null
    Invoke-ExternalCommand -Command 'git' -ArgumentList @('-C', $repository, 'archive', '--format=zip', "--output=$sourceZip", 'HEAD')
    Expand-Archive -LiteralPath $sourceZip -DestinationPath $stage
    $writableDirectories = @(
        'bootstrap\cache',
        'storage\app\private',
        'storage\app\public',
        'storage\framework\cache\data',
        'storage\framework\sessions',
        'storage\framework\testing',
        'storage\framework\views',
        'storage\logs'
    )
    foreach ($directory in $writableDirectories) {
        Assert-WritableDirectory -Path (Join-Path $stage $directory)
    }

    Push-Location $stage
    try {
        # Install development tooling for verification first. The final install
        # below removes it before the release archive is created.
        Invoke-ExternalCommand -Command 'composer' -ArgumentList @('install', '--no-interaction', '--prefer-dist')
        Invoke-ExternalCommand -Command 'npm' -ArgumentList @('ci', '--no-audit', '--no-fund')
        Invoke-ExternalCommand -Command 'npm' -ArgumentList @('run', 'typecheck')
        Invoke-ExternalCommand -Command 'npm' -ArgumentList @('run', 'build')
        Invoke-ExternalCommand -Command 'php' -ArgumentList @('artisan', 'production:verify-build')
        $previousAppKey = [Environment]::GetEnvironmentVariable('APP_KEY', 'Process')
        try {
            # The archived source deliberately contains no .env. Use the same
            # non-secret deterministic test key as CI only for the test process.
            $env:APP_KEY = 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='
            Invoke-ExternalCommand -Command 'php' -ArgumentList @('artisan', 'test')
        } finally {
            if ($null -eq $previousAppKey) {
                Remove-Item Env:APP_KEY -ErrorAction SilentlyContinue
            } else {
                $env:APP_KEY = $previousAppKey
            }
        }
        Invoke-ExternalCommand -Command 'composer' -ArgumentList @('install', '--no-dev', '--no-interaction', '--prefer-dist', '--optimize-autoloader', '--classmap-authoritative')
        Invoke-ExternalCommand -Command 'composer' -ArgumentList @('check-platform-reqs', '--no-dev')
        if (Test-Path -LiteralPath (Join-Path $stage '.env')) {
            throw 'A release archive must not contain .env.'
        }
        foreach ($required in @(
            'vendor\autoload.php',
            'public\build\manifest.json',
            'deployment\hostinger\public_html\index.php',
            'deployment\hostinger\public_html\.htaccess'
        )) {
            if (-not (Test-Path -LiteralPath (Join-Path $stage $required))) {
                throw "Release input is missing: $required"
            }
        }
        foreach ($directory in $writableDirectories) {
            Assert-WritableDirectory -Path (Join-Path $stage $directory)
        }
        Remove-Item -LiteralPath (Join-Path $stage 'node_modules') -Recurse -Force
        Remove-Item -LiteralPath (Join-Path $stage 'public\hot') -Force -ErrorAction SilentlyContinue
        Remove-Item -LiteralPath (Join-Path $stage '.phpunit.result.cache') -Force -ErrorAction SilentlyContinue
        [IO.File]::WriteAllText((Join-Path $stage 'RELEASE'), "$sha`n", [Text.UTF8Encoding]::new($false))
    } finally {
        Pop-Location
    }

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [IO.Compression.ZipFile]::CreateFromDirectory($stage, $candidateArchive, [IO.Compression.CompressionLevel]::Optimal, $false)
    $hash = (Get-FileHash -Algorithm SHA256 -LiteralPath $candidateArchive).Hash.ToLowerInvariant()
    [IO.File]::WriteAllText($candidateChecksum, "$hash  $archiveName`n", [Text.UTF8Encoding]::new($false))
    Move-Item -LiteralPath $candidateArchive -Destination $archive
    Move-Item -LiteralPath $candidateChecksum -Destination $checksum
    $published = $true
    Write-Output "Release package: $archive"
    Write-Output "SHA-256: $hash"
} finally {
    if (-not $published) {
        Remove-Item -LiteralPath $archive -Force -ErrorAction SilentlyContinue
        Remove-Item -LiteralPath $checksum -Force -ErrorAction SilentlyContinue
    }
    if (Test-Path -LiteralPath $temporary) {
        Remove-Item -LiteralPath $temporary -Recurse -Force
    }
}
