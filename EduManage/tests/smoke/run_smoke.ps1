<#
Standard usage:
1. powershell -ExecutionPolicy Bypass -File tests\smoke\run_smoke.ps1
2. powershell -ExecutionPolicy Bypass -File tests\smoke\run_smoke.ps1 -BaseUrl http://localhost
#>

param(
    [string] $BaseUrl = '',
    [string] $PhpExe = '',
    [string] $DbPass = '',
    [int] $Port = 8123
)

$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Resolve-Path (Join-Path $scriptDir '..\..')
$runner = Join-Path $scriptDir 'smoke_http.php'

function Resolve-PhpExePath {
    param(
        [string] $RequestedPhpExe
    )

    $candidates = New-Object System.Collections.Generic.List[string]

    if ($RequestedPhpExe -ne '') {
        $candidates.Add($RequestedPhpExe)
    }

    foreach ($envName in @('PHP_EXE', 'PHP_PATH')) {
        $envValue = [Environment]::GetEnvironmentVariable($envName)
        if (-not [string]::IsNullOrWhiteSpace($envValue)) {
            $candidates.Add($envValue)
        }
    }

    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    if ($phpCommand -and $phpCommand.Source) {
        $candidates.Add($phpCommand.Source)
    }

    $phpStudyRoots = @(
        'D:\phpstudy_pro\Extensions\php',
        'C:\phpstudy_pro\Extensions\php'
    )

    foreach ($root in $phpStudyRoots) {
        if (Test-Path $root) {
            Get-ChildItem -Path $root -Directory -ErrorAction SilentlyContinue |
                Sort-Object Name -Descending |
                ForEach-Object {
                    $candidate = Join-Path $_.FullName 'php.exe'
                    if (Test-Path $candidate) {
                        $candidates.Add($candidate)
                    }
                }
        }
    }

    foreach ($candidate in $candidates) {
        if ([string]::IsNullOrWhiteSpace($candidate)) {
            continue
        }

        if (Test-Path $candidate) {
            return (Resolve-Path $candidate).Path
        }

        $resolvedCommand = Get-Command $candidate -ErrorAction SilentlyContinue
        if ($resolvedCommand -and $resolvedCommand.Source -and (Test-Path $resolvedCommand.Source)) {
            return (Resolve-Path $resolvedCommand.Source).Path
        }
    }

    $message = @(
        'PHP executable not found.'
        'Provide it with one of the following:'
        '1. -PhpExe D:\path\to\php.exe'
        '2. -PhpExe php'
        '3. $env:PHP_EXE = ''D:\path\to\php.exe'' or $env:PHP_PATH = ''D:\path\to\php.exe'''
        '4. Add php.exe to PATH'
    ) -join [Environment]::NewLine

    throw $message
}

$PhpExe = Resolve-PhpExePath -RequestedPhpExe $PhpExe

if ($DbPass -ne '') {
    $env:DB_PASS = $DbPass
}

function Invoke-SmokeRunner {
    param(
        [string] $ResolvedBaseUrl
    )

    & $PhpExe $runner $ResolvedBaseUrl | Out-Host
    return $LASTEXITCODE
}

if ($BaseUrl -ne '') {
    exit (Invoke-SmokeRunner -ResolvedBaseUrl $BaseUrl)
}

$hostName = '127.0.0.1'
$resolvedBaseUrl = "http://${hostName}:$Port"
$stdoutLog = Join-Path $scriptDir 'php-server.stdout.log'
$stderrLog = Join-Path $scriptDir 'php-server.stderr.log'

if (Test-Path $stdoutLog) { Remove-Item $stdoutLog -Force }
if (Test-Path $stderrLog) { Remove-Item $stderrLog -Force }

$server = Start-Process `
    -FilePath $PhpExe `
    -ArgumentList @('-S', "${hostName}:$Port", '-t', $projectRoot) `
    -WorkingDirectory $projectRoot `
    -RedirectStandardOutput $stdoutLog `
    -RedirectStandardError $stderrLog `
    -PassThru

$exitCode = 1

try {
    $ready = $false
    for ($i = 0; $i -lt 30; $i++) {
        Start-Sleep -Milliseconds 500
        try {
            $response = Invoke-WebRequest -Uri ($resolvedBaseUrl + '/login/login.php') -UseBasicParsing -TimeoutSec 3
            if ($response.StatusCode -eq 200) {
                $ready = $true
                break
            }
        } catch {
        }
    }

    if (-not $ready) {
        throw "Built-in PHP server did not become ready at $resolvedBaseUrl"
    }

    $exitCode = Invoke-SmokeRunner -ResolvedBaseUrl $resolvedBaseUrl
} finally {
    if ($server -and -not $server.HasExited) {
        Stop-Process -Id $server.Id -Force
    }
}

exit $exitCode
