$ErrorActionPreference = 'Stop'

function Invoke-TestCommand {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Command,

        [Parameter(Mandatory = $true)]
        [string[]]$ArgumentList
    )

    & $Command @ArgumentList | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "Test setup command failed: $Command $($ArgumentList -join ' ')"
    }
}

$repository = Split-Path $PSScriptRoot -Parent
$temporary = Join-Path ([IO.Path]::GetTempPath()) ('raoza-build-failure-test-' + [guid]::NewGuid().ToString('N'))
$testRepository = Join-Path $temporary 'repository'
$fakeBin = Join-Path $temporary 'bin'
$previousPath = $env:PATH

try {
    New-Item -ItemType Directory -Path (Join-Path $testRepository 'scripts') -Force | Out-Null
    New-Item -ItemType Directory -Path (Join-Path $testRepository 'bootstrap\cache') -Force | Out-Null
    New-Item -ItemType Directory -Path $fakeBin -Force | Out-Null
    Copy-Item -LiteralPath (Join-Path $repository 'scripts\build-hostinger-package.ps1') -Destination (Join-Path $testRepository 'scripts\build-hostinger-package.ps1')
    [IO.File]::WriteAllText((Join-Path $testRepository '.gitignore'), "/artifacts/`n", [Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText((Join-Path $testRepository 'bootstrap\cache\.gitignore'), "*`n!.gitignore`n", [Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText((Join-Path $fakeBin 'composer.cmd'), "@exit /b 23`r`n", [Text.ASCIIEncoding]::new())

    Invoke-TestCommand -Command 'git' -ArgumentList @('-C', $testRepository, 'init', '--quiet')
    Invoke-TestCommand -Command 'git' -ArgumentList @('-C', $testRepository, 'config', 'user.name', 'RAOZA Build Test')
    Invoke-TestCommand -Command 'git' -ArgumentList @('-C', $testRepository, 'config', 'user.email', 'build-test@invalid.example')
    Invoke-TestCommand -Command 'git' -ArgumentList @('-C', $testRepository, 'add', '.')
    Invoke-TestCommand -Command 'git' -ArgumentList @('-C', $testRepository, 'commit', '--quiet', '-m', 'fixture')
    $shortSha = (& git -C $testRepository rev-parse --short=12 HEAD).Trim()
    if ($LASTEXITCODE -ne 0) {
        throw 'Could not resolve the fixture commit.'
    }
    $artifactDirectory = Join-Path $testRepository 'artifacts'
    New-Item -ItemType Directory -Path $artifactDirectory -Force | Out-Null
    [IO.File]::WriteAllText((Join-Path $artifactDirectory "raoza-$shortSha.zip"), 'stale', [Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText((Join-Path $artifactDirectory "raoza-$shortSha.zip.sha256"), 'stale', [Text.UTF8Encoding]::new($false))

    $env:PATH = "$fakeBin;$previousPath"
    $standardOutput = Join-Path $temporary 'build.stdout.log'
    $standardError = Join-Path $temporary 'build.stderr.log'
    $process = Start-Process -FilePath 'powershell.exe' `
        -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', 'scripts\build-hostinger-package.ps1') `
        -WorkingDirectory $testRepository `
        -RedirectStandardOutput $standardOutput `
        -RedirectStandardError $standardError `
        -Wait `
        -PassThru `
        -NoNewWindow
    $buildExitCode = $process.ExitCode
    $buildError = Get-Content -LiteralPath $standardError -Raw

    if ($buildExitCode -eq 0) {
        throw 'The build unexpectedly succeeded after Composer returned exit code 23.'
    }
    if ($buildError -notmatch 'exit code 23: composer install') {
        throw 'The build did not report the expected Composer failure.'
    }
    if (Get-ChildItem -LiteralPath (Join-Path $testRepository 'artifacts') -Filter '*.zip' -File -ErrorAction SilentlyContinue) {
        throw 'A ZIP was published after Composer failed.'
    }
    if (Get-ChildItem -LiteralPath (Join-Path $testRepository 'artifacts') -Filter '*.sha256' -File -ErrorAction SilentlyContinue) {
        throw 'A checksum was published after Composer failed.'
    }

    Write-Output 'Composer failure correctly stopped the build without publishing artifacts.'
} finally {
    $env:PATH = $previousPath
    if (Test-Path -LiteralPath $temporary) {
        Remove-Item -LiteralPath $temporary -Recurse -Force
    }
}
