param(
    [string] $BaseUrl = '',
    [string] $PhpExe = 'D:\phpstudy_pro\Extensions\php\php8.0.2nts\php.exe',
    [int] $Port = 8123
)

$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Resolve-Path (Join-Path $scriptDir '..\..')
$runner = Join-Path $scriptDir 'smoke_http.php'

if (-not (Test-Path $PhpExe)) {
    throw "PHP executable not found: $PhpExe"
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
