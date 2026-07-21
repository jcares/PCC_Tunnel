# ==========================================================
# PCC_Tunnel - TCP Forwarding Test
# Ejecutar desde la raiz de PCC_Tunnel
# ==========================================================

$ErrorActionPreference = "Stop"
$root = (Get-Location).Path
if ((Split-Path -Leaf $root) -ne "PCC_Tunnel") {
    Write-Host "ERROR: Ejecutar desde la raiz PCC_Tunnel"
    exit 1
}

$gatewayLog = Join-Path $root "gateway-forwarding-test.log"
$gatewayError = Join-Path $root "gateway-forwarding-error.log"
$clientLog = Join-Path $root "client-forwarding-test.log"
$clientError = Join-Path $root "client-forwarding-error.log"
$gatewayProcess = $null
$clientProcess = $null
$echoJob = $null

function Stop-TestProcess($process) {
    if ($null -ne $process -and -not $process.HasExited) {
        Stop-Process -Id $process.Id -Force
        $process.WaitForExit()
    }
}

try {
    Write-Host "========================================="
    Write-Host " PCC_Tunnel TCP Forwarding Test"
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

    $echoJob = Start-Job -ScriptBlock {
        $listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, 80)
        $listener.Start()
        try {
            $tcp = $listener.AcceptTcpClient()
            $stream = $tcp.GetStream()
            $buffer = New-Object byte[] 4096
            $count = $stream.Read($buffer, 0, $buffer.Length)
            $stream.Write($buffer, 0, $count)
            $stream.Flush()
            $tcp.Close()
        }
        finally {
            $listener.Stop()
        }
    }

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
    Start-Sleep -Seconds 2

    $tcp = [System.Net.Sockets.TcpClient]::new("127.0.0.1", 8081)
    $stream = $tcp.GetStream()
    $payload = [System.Text.Encoding]::UTF8.GetBytes("PCC_FORWARDING_TEST")
    $stream.Write($payload, 0, $payload.Length)
    $stream.Flush()
    $buffer = New-Object byte[] 4096
    $count = $stream.Read($buffer, 0, $buffer.Length)
    $response = [System.Text.Encoding]::UTF8.GetString($buffer, 0, $count)
    $tcp.Close()

    if ($response -ne "PCC_FORWARDING_TEST") {
        throw "respuesta inesperada del servicio local: $response"
    }
    Write-Host "[OK] Datos reenviados correctamente"
    Write-Host "VALIDACION COMPLETADA"
}
catch {
    Write-Host "[ERROR] $($_.Exception.Message)"
    exit 1
}
finally {
    if ($null -ne $stream) { $stream.Dispose() }
    Stop-TestProcess $clientProcess
    Stop-TestProcess $gatewayProcess
    if ($null -ne $echoJob) {
        Stop-Job $echoJob -ErrorAction SilentlyContinue
        Remove-Job $echoJob -Force -ErrorAction SilentlyContinue
    }
    Pop-Location -ErrorAction SilentlyContinue
}
