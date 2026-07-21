param(
    [string]$GitHubUser = $(if ([string]::IsNullOrWhiteSpace($env:GHCR_USER)) { "jcares" } else { $env:GHCR_USER }),
    [string]$Token = $env:GHCR_TOKEN
)

$ErrorActionPreference = "Stop"
$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$gatewayImage = "ghcr.io/jcares/pcc-tunnel-gateway:latest"
$clientImage = "ghcr.io/jcares/pcc-tunnel-client:latest"

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker no está instalado o no está disponible en PATH."
}

if ([string]::IsNullOrWhiteSpace($Token)) {
    $secureToken = Read-Host "Token de GitHub con permiso write:packages" -AsSecureString
    $tokenPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureToken)
    try {
        $Token = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($tokenPointer)
    }
    finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($tokenPointer)
    }
}

if ([string]::IsNullOrWhiteSpace($Token)) {
    throw "Debes proporcionar un token de GitHub."
}

Push-Location $projectRoot
try {
    $Token | docker login ghcr.io --username $GitHubUser --password-stdin
    if ($LASTEXITCODE -ne 0) { throw "No se pudo iniciar sesión en GHCR." }

    docker build -t $gatewayImage ./gateway
    if ($LASTEXITCODE -ne 0) { throw "No se pudo construir la imagen del gateway." }

    docker build -t $clientImage ./client
    if ($LASTEXITCODE -ne 0) { throw "No se pudo construir la imagen del cliente." }

    docker push $gatewayImage
    if ($LASTEXITCODE -ne 0) { throw "No se pudo publicar la imagen del gateway." }

    docker push $clientImage
    if ($LASTEXITCODE -ne 0) { throw "No se pudo publicar la imagen del cliente." }
}
finally {
    if ($Token) { $Token = $null }
    Pop-Location
}

Write-Host "Imágenes publicadas correctamente:" -ForegroundColor Green
Write-Host "  $gatewayImage"
Write-Host "  $clientImage"
