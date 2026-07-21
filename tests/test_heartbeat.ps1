# ==========================================================
# PCC_Tunnel - Heartbeat and Reconnect Test
# Ejecutar desde la raiz de PCC_Tunnel
# ==========================================================

$ErrorActionPreference = "Stop"
$root = (Get-Location).Path
if ((Split-Path -Leaf $root) -ne "PCC_Tunnel") {
    Write-Host "ERROR: Ejecutar desde la raiz PCC_Tunnel"
    exit 1
}

$gatewayLog = Join-Path $root "gateway-heartbeat-test.log"
$gatewayLogRestart = Join-Path $root "gateway-heartbeat-restart.log"
$gatewayError = Join-Path $root "gateway-heartbeat-error.log"
$clientLog = Join-Path $root "client-heartbeat-test.log"
$clientError = Join-Path $root "client-heartbeat-error.log"
$gatewayProcess = $null
$clientProcess = $null

function Stop-TestProcess($process) {
    if ($null -ne $process -and -not $process.HasExited) {
        Stop-Process -Id $process.Id -Force
        $process.WaitForExit()
    }
}

try {
    Write-Host "========================================="
    Write-Host " PCC_Tunnel Heartbeat/Reconnect Test"
    Write-Host "========================================="

    Push-Location client
    go mod tidy
    if ($LASTEXITCODE -ne 0) { throw "client go mod tidy fallo" }
    go build -o pcc-client.exe .
    if ($LASTEXITCODE -ne 0) { throw "compilacion del cliente fallo" }
    Pop-Location

    Push-Location gateway
    go mod tidy
    if ($LASTEXITCODE -ne 0) { throw "gateway go mod tidy fallo" }
    go build -o pcc-gateway.exe .
    if ($LASTEXITCODE -ne 0) { throw "compilacion del gateway fallo" }
    Pop-Location

    Remove-Item $gatewayLog, $gatewayLogRestart, $gatewayError, $clientLog, $clientError -Force -ErrorAction SilentlyContinue

    $gatewayProcess = Start-Process `
        -FilePath (Join-Path $root "gateway\pcc-gateway.exe") `
        -WorkingDirectory (Join-Path $root "gateway") `
        -RedirectStandardOutput $gatewayLog `
        -RedirectStandardError $gatewayError `
        -PassThru

    Start-Sleep -Seconds 1

    $clientProcess = Start-Process `
        -FilePath (Join-Path $root "client\pcc-client.exe") `
        -WorkingDirectory (Join-Path $root "client") `
        -RedirectStandardOutput $clientLog `
        -RedirectStandardError $clientError `
        -PassThru

    Start-Sleep -Seconds 8
    $initialClientLog = Get-Content $clientLog -Raw
    $initialGatewayLog = Get-Content $gatewayLog -Raw
    if ($initialClientLog -notmatch "Connected Gateway") {
        throw "el cliente no completo la conexion inicial"
    }
    if ($initialGatewayLog -notmatch "Heartbeat: cliente-01") {
        throw "no se detecto el intercambio PING/PONG"
    }
    Write-Host "[OK] Heartbeat PING/PONG validado"

    Stop-TestProcess $gatewayProcess
    $gatewayProcess = $null
    Start-Sleep -Seconds 7

    $gatewayProcess = Start-Process `
        -FilePath (Join-Path $root "gateway\pcc-gateway.exe") `
        -WorkingDirectory (Join-Path $root "gateway") `
        -RedirectStandardOutput $gatewayLogRestart `
        -RedirectStandardError $gatewayError `
        -PassThru

    Start-Sleep -Seconds 8
    $finalClientLog = Get-Content $clientLog -Raw
    $finalGatewayLog = Get-Content $gatewayLogRestart -Raw
    $clientConnections = ([regex]::Matches($finalClientLog, "Connected Gateway")).Count
    $gatewayConnections = ([regex]::Matches($finalGatewayLog, "Client connected: cliente-01")).Count

    if ($clientConnections -lt 2 -or $gatewayConnections -lt 1) {
        throw "el cliente no se reconecto correctamente"
    }

    Write-Host "[OK] Reconexion automatica validada"
    Write-Host "Conexiones del cliente: $clientConnections"
    Write-Host "Registros del gateway: $gatewayConnections"
    Write-Host "VALIDACION COMPLETADA"
}
catch {
    Write-Host "[ERROR] $($_.Exception.Message)"
    exit 1
}
finally {
    Stop-TestProcess $clientProcess
    Stop-TestProcess $gatewayProcess
    Pop-Location -ErrorAction SilentlyContinue
}
