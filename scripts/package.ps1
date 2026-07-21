[CmdletBinding()]
param(
    [string]$ProjectRoot
)

$ErrorActionPreference = 'Stop'
$ScriptDirectory = Split-Path -Parent $MyInvocation.MyCommand.Path
if ([string]::IsNullOrWhiteSpace($ProjectRoot)) {
    $ProjectRoot = Split-Path -Parent $ScriptDirectory
}
Set-Location -LiteralPath $ProjectRoot

$cpanel = Join-Path $ProjectRoot 'deploy/cpanel'
$casaos = Join-Path $ProjectRoot 'deploy/casaos'

if (Test-Path -LiteralPath 'deploy') {
    Remove-Item -LiteralPath 'deploy' -Recurse -Force
}
New-Item -ItemType Directory -Force -Path $cpanel, "$casaos/scripts", "$casaos/bin" | Out-Null

Copy-Item -Path 'server/*' -Destination $cpanel -Recurse -Force
Copy-Item -Path 'server/.htaccess' -Destination "$cpanel/.htaccess" -Force
Copy-Item -Path 'server/server.env.example' -Destination "$cpanel/server.env.example" -Force

@'
# Despliegue cPanel

1. Sube todo el contenido de esta carpeta a `public_html` o a un subdirectorio.
2. Copia `server.env.example` como `.env` y completa las credenciales MySQL y `PCC_REGISTRATION_KEY`.
3. Crea la base de datos y el usuario desde cPanel.
4. Ejecuta una vez `database/install.php` y después protégelo o elimínalo.
5. Accede al panel en `/panel/`.

No subas `client/`, Docker, binarios ni archivos del repositorio raíz.
'@ | Set-Content -LiteralPath "$cpanel/README_DEPLOY.md" -Encoding utf8

@'
FROM alpine:3.19
RUN apk add --no-cache ca-certificates tzdata
WORKDIR /app
COPY bin/pcc-client /app/pcc-client
COPY config.yaml /app/config/config.yaml
RUN mkdir -p /app/logs
VOLUME ["/app/config", "/app/logs"]
ENTRYPOINT ["/app/pcc-client"]
'@ | Set-Content -LiteralPath "$casaos/Dockerfile" -Encoding utf8
if (-not (Test-Path -LiteralPath 'client/pcc-client' -PathType Leaf)) {
    Push-Location 'client'
    go build -ldflags='-s -w' -o pcc-client .
    Pop-Location
}
Copy-Item -Path 'client/config/config.yaml' -Destination "$casaos/config.yaml" -Force
Copy-Item -Path 'client/pcc-client' -Destination "$casaos/bin/pcc-client" -Force
if (Test-Path -LiteralPath 'client/pcc-client' -PathType Leaf) {
    Remove-Item -LiteralPath 'client/pcc-client' -Force
}

@'
services:
  client:
    image: ghcr.io/jcares/pcc-tunnel-client:latest
    restart: unless-stopped
    env_file:
      - .env
    volumes:
      - ./config.yaml:/app/config/config.yaml:ro
      - ./logs:/app/logs
    extra_hosts:
      - "host.docker.internal:host-gateway"
'@ | Set-Content -LiteralPath "$casaos/docker-compose.yml" -Encoding utf8

@'
services:
  client:
    build:
      context: .
      dockerfile: Dockerfile
    image: pcc-tunnel-client:local
'@ | Set-Content -LiteralPath "$casaos/docker-compose.override.yml" -Encoding utf8

@'
PCC_SERVER_URL=https://tudominio.example/api
PCC_AUTH_TOKEN=
PCC_REGISTRATION_KEY=
PCC_PROXY_LOCAL=http://host.docker.internal:80
'@ | Set-Content -LiteralPath "$casaos/.env.example" -Encoding utf8

@'
# Despliegue CasaOS

1. Copia esta carpeta completa a CasaOS.
2. Copia `.env.example` como `.env` y completa las variables.
3. Ajusta `config.yaml` si necesitas cambiar el cliente o el servicio local.
4. Ejecuta `docker compose up -d`.
5. Usa `docker compose logs -f client` para verificar la conexión.

Esta carpeta solo contiene el cliente Go y sus archivos Docker. No incluye PHP,
panel ni base de datos.
'@ | Set-Content -LiteralPath "$casaos/README_DEPLOY.md" -Encoding utf8

Write-Host "Paquetes creados en deploy/cpanel y deploy/casaos" -ForegroundColor Green
