# ==========================================================
# PCC_Tunnel - Active Sessions Test
# Ejecutar desde la raiz de PCC_Tunnel
# ==========================================================

$ErrorActionPreference = "Stop"
$root = (Get-Location).Path
if ((Split-Path -Leaf $root) -ne "PCC_Tunnel") {
    Write-Host "ERROR: Ejecutar desde la raiz PCC_Tunnel"
    exit 1
}

$gatewayLog = Join-Path $root "gateway-sessions-test.log"
$gatewayError = Join-Path $root "gateway-sessions-error.log"
$clientLog = Join-Path $root "client-sessions-test.log"
$clientError = Join-Path $root "client-sessions-error.log"
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
    Write-Host " PCC_Tunnel Active Sessions Test"
    Write-Host "========================================="

    Push-Location client
    go build -o pcc-client.exe .
    if ($LASTEXITCODE -ne 0) { throw "compilacion del cliente fallo" }
    Pop-Location

    Push-Location gateway
    go build -o pcc-gateway.exe .
    if ($LASTEXITCODE -ne 0) { throw "compilacion del gateway fallo" }
    Pop-Location

    Remove-Item $gatewayLog, $gatewayError, $clientLog, $clientError -Force -ErrorAction SilentlyContinue

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

    Start-Sleep -Seconds 7
    $connectedLog = Get-Content $gatewayLog -Raw
    if ($connectedLog -notmatch "Client connected: cliente-01") {
        throw "no se registro el cliente"
    }
    if ($connectedLog -notmatch "Active clients: 1") {
        throw "el registro no contiene un cliente activo"
    }
    Write-Host "[OK] Cliente registrado en sesiones activas"

    Stop-TestProcess $clientProcess
    $clientProcess = $null
    Start-Sleep -Seconds 2

    $disconnectedLog = Get-Content $gatewayLog -Raw
    if ($disconnectedLog -notmatch "Client disconnected: cliente-01") {
        throw "no se detecto la desconexion del cliente"
    }
    if ($disconnectedLog -notmatch "Active clients: 0") {
        throw "el cliente no fue retirado del registro"
    }
    Write-Host "[OK] Cliente retirado al desconectarse"
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
