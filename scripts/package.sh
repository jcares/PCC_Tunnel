#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CPANEL="$PROJECT_ROOT/deploy/cpanel"
CASAOS="$PROJECT_ROOT/deploy/casaos"

rm -rf "$PROJECT_ROOT/deploy"
mkdir -p "$CPANEL" "$CASAOS/scripts" "$CASAOS/bin"

cp -R "$PROJECT_ROOT/server/." "$CPANEL/"
cp "$PROJECT_ROOT/client/Dockerfile" "$CASAOS/Dockerfile"
cp "$PROJECT_ROOT/client/config/config.yaml" "$CASAOS/config.yaml"
if [[ -f "$PROJECT_ROOT/client/pcc-client" ]]; then
  cp "$PROJECT_ROOT/client/pcc-client" "$CASAOS/bin/pcc-client"
fi

cat > "$CASAOS/docker-compose.yml" <<'YAML'
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
YAML

cat > "$CASAOS/docker-compose.override.yml" <<'YAML'
services:
  client:
    build:
      context: .
      dockerfile: Dockerfile
    image: pcc-tunnel-client:local
YAML

cat > "$CASAOS/.env.example" <<'ENV'
PCC_SERVER_URL=https://tudominio.example/api
PCC_AUTH_TOKEN=
PCC_REGISTRATION_KEY=
PCC_PROXY_LOCAL=http://host.docker.internal:80
ENV

cat > "$CPANEL/README_DEPLOY.md" <<'MARKDOWN'
# Despliegue cPanel

1. Sube todo el contenido de esta carpeta a `public_html` o a un subdirectorio.
2. Copia `server.env.example` como `.env` y completa las credenciales MySQL.
3. Ejecuta una vez `database/install.php` y después protégelo o elimínalo.
4. Accede al panel en `/panel/`.

No subas `client/`, Docker, binarios ni archivos del repositorio raíz.
MARKDOWN

cat > "$CASAOS/README_DEPLOY.md" <<'MARKDOWN'
# Despliegue CasaOS

1. Copia esta carpeta completa a CasaOS.
2. Copia `.env.example` como `.env` y completa las variables.
3. Ejecuta `docker compose up -d`.
4. Verifica la conexión con `docker compose logs -f client`.

Esta carpeta solo contiene el cliente Go y sus archivos Docker.
MARKDOWN

echo "Paquetes creados en deploy/cpanel y deploy/casaos"
