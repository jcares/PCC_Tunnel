# ==========================================================
# PCC_Tunnel - Local Handshake Test
# Validacion FASE 1
#
# Ejecutar desde:
# D:\desarrollos\PCC_Tunnel
# ==========================================================

$ErrorActionPreference = "Stop"


Write-Host ""
Write-Host "========================================="
Write-Host " PCC_Tunnel Handshake Test"
Write-Host "========================================="
Write-Host ""


# ----------------------------------------------------------
# Validar ubicacion
# ----------------------------------------------------------

$current = Split-Path -Leaf (Get-Location)

if ($current -ne "PCC_Tunnel") {

    Write-Host "ERROR: Ejecutar desde la raiz PCC_Tunnel"
    exit 1

}


Write-Host "[OK] Ubicacion correcta"


# ----------------------------------------------------------
# Validar Go
# ----------------------------------------------------------

Write-Host ""
Write-Host "Verificando Go..."


go version


if ($LASTEXITCODE -ne 0) {

    Write-Host "ERROR: Go no disponible"
    exit 1

}


Write-Host "[OK] Go disponible"



# ----------------------------------------------------------
# Validar estructura
# ----------------------------------------------------------

Write-Host ""
Write-Host "Validando estructura..."


$paths = @(

    "client/main.go",
    "client/go.mod",
    "client/config/config.yaml",
    "client/internal/tunnel/client.go",
    "client/internal/tunnel/protocol.go",

    "gateway/main.go",
    "gateway/go.mod",
    "gateway/tunnel/server.go",
    "gateway/tunnel/protocol.go"

)



foreach ($p in $paths) {

    if (Test-Path $p) {

        Write-Host "[OK] $p"

    }
    else {

        Write-Host "[FALTA] $p"
        exit 1

    }

}



# ----------------------------------------------------------
# Cliente
# ----------------------------------------------------------

Write-Host ""
Write-Host "========================================="
Write-Host " Preparando CLIENT"
Write-Host "========================================="


Push-Location client


Write-Host "go mod tidy"

go mod tidy


if ($LASTEXITCODE -ne 0) {

    Write-Host "ERROR CLIENT tidy"
    Pop-Location
    exit 1

}



Write-Host "Compilando cliente..."

go build -o pcc-client.exe .


if ($LASTEXITCODE -ne 0) {

    Write-Host "ERROR compilando cliente"
    Pop-Location
    exit 1

}


Write-Host "[OK] Cliente compilado"


Pop-Location



# ----------------------------------------------------------
# Gateway
# ----------------------------------------------------------

Write-Host ""
Write-Host "========================================="
Write-Host " Preparando GATEWAY"
Write-Host "========================================="


Push-Location gateway


Write-Host "go mod tidy"

go mod tidy


if ($LASTEXITCODE -ne 0) {

    Write-Host "ERROR Gateway tidy"
    Pop-Location
    exit 1

}



Write-Host "Compilando Gateway..."

go build -o pcc-gateway.exe .


if ($LASTEXITCODE -ne 0) {

    Write-Host "ERROR compilando Gateway"
    Pop-Location
    exit 1

}


Write-Host "[OK] Gateway compilado"


Pop-Location



# ----------------------------------------------------------
# Resultado
# ----------------------------------------------------------

Write-Host ""

Write-Host "========================================="
Write-Host " VALIDACION COMPLETADA"
Write-Host "========================================="

Write-Host ""

Write-Host "Archivos generados:"

Write-Host ""
Write-Host "client/pcc-client.exe"
Write-Host "gateway/pcc-gateway.exe"


Write-Host ""

Write-Host "Ahora ejecutar manualmente:"


Write-Host ""

Write-Host "Terminal 1:"
Write-Host "cd gateway"
Write-Host ".\pcc-gateway.exe"


Write-Host ""

Write-Host "Terminal 2:"
Write-Host "cd client"
Write-Host ".\pcc-client.exe"


Write-Host ""

Write-Host "Resultado esperado:"


Write-Host ""

Write-Host "Gateway:"
Write-Host "PCC_Tunnel Gateway"
Write-Host "Listening :8080"
Write-Host "Client connected: cliente-01"


Write-Host ""

Write-Host "Cliente:"
Write-Host "PCC_Tunnel Client"
Write-Host "Connected Gateway"
Write-Host "Client registered"


Write-Host ""

Write-Host "========================================="
Write-Host " Fin prueba FASE 1"
Write-Host "========================================="
