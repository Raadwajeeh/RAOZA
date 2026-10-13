param(
    [string]$OutputDirectory = "artifacts"
)

$ErrorActionPreference = 'Stop'
$repository = (git rev-parse --show-toplevel).Trim()
if (-not $repository) { throw 'Run this script inside the RAOZA Git repository.' }
if (git status --porcelain) { throw 'The release worktree must be clean. Commit or stash reviewed changes first.' }

$sha = (git rev-parse HEAD).Trim()
$shortSha = (git rev-parse --short=12 HEAD).Trim()
$output = [IO.Path]::GetFullPath((Join-Path $repository $OutputDirectory))
$temporary = Join-Path ([IO.Path]::GetTempPath()) ("raoza-release-" + [guid]::NewGuid().ToString('N'))
$sourceZip = Join-Path $temporary 'source.zip'
$stage = Join-Path $temporary 'app'

try {
    New-Item -ItemType Directory -Path $stage -Force | Out-Null
    git -C $repository archive --format=zip --output=$sourceZip HEAD
    Expand-Archive -LiteralPath $sourceZip -DestinationPath $stage

    Push-Location $stage
    try {
        # Install development tooling for verification first. The final install
        # below removes it before the release archive is created.
        composer install --no-interaction --prefer-dist
        npm ci --no-audit --no-fund
        npm run typecheck
        npm run build
        php artisan production:verify-build
        php artisan test
        composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --classmap-authoritative
        composer check-platform-reqs --no-dev
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
        Remove-Item -LiteralPath (Join-Path $stage 'node_modules') -Recurse -Force
        Remove-Item -LiteralPath (Join-Path $stage 'public\hot') -Force -ErrorAction SilentlyContinue
        Remove-Item -LiteralPath (Join-Path $stage '.phpunit.result.cache') -Force -ErrorAction SilentlyContinue
        [IO.File]::WriteAllText((Join-Path $stage 'RELEASE'), "$sha`n", [Text.UTF8Encoding]::new($false))
    } finally {
        Pop-Location
    }

    New-Item -ItemType Directory -Path $output -Force | Out-Null
    $archive = Join-Path $output "raoza-$shortSha.zip"
    if (Test-Path -LiteralPath $archive) { Remove-Item -LiteralPath $archive -Force }
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [IO.Compression.ZipFile]::CreateFromDirectory($stage, $archive, [IO.Compression.CompressionLevel]::Optimal, $false)
    $hash = (Get-FileHash -Algorithm SHA256 -LiteralPath $archive).Hash.ToLowerInvariant()
    [IO.File]::WriteAllText("$archive.sha256", "$hash  $([IO.Path]::GetFileName($archive))`n", [Text.UTF8Encoding]::new($false))
    Write-Output "Release package: $archive"
    Write-Output "SHA-256: $hash"
} finally {
    if (Test-Path -LiteralPath $temporary) {
        Remove-Item -LiteralPath $temporary -Recurse -Force
    }
}
