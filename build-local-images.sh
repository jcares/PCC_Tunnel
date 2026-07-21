#!/usr/bin/env bash
set -Eeuo pipefail

REPOSITORY="https://github.com/jcares/PCC_Tunnel.git"
PROJECT_DIR="${1:-/opt/PCC_Tunnel}"
GATEWAY_IMAGE="pcc-tunnel-gateway:local"
CLIENT_IMAGE="pcc-tunnel-client:local"

if ! command -v docker >/dev/null 2>&1; then
  echo "Error: Docker no está instalado o no está disponible en PATH." >&2
  exit 1
fi

if [ ! -d "$PROJECT_DIR/gateway" ] || [ ! -f "$PROJECT_DIR/gateway/Dockerfile" ]; then
  if [ -e "$PROJECT_DIR" ] && [ "$(ls -A "$PROJECT_DIR" 2>/dev/null)" ]; then
    echo "Error: $PROJECT_DIR existe, pero no contiene el proyecto PCC_Tunnel." >&2
    exit 1
  fi

  rm -rf "$PROJECT_DIR"
  git clone "$REPOSITORY" "$PROJECT_DIR"
fi

if [ ! -f "$PROJECT_DIR/gateway/Dockerfile" ] || [ ! -f "$PROJECT_DIR/client/Dockerfile" ]; then
  echo "Error: faltan gateway/Dockerfile o client/Dockerfile en $PROJECT_DIR." >&2
  exit 1
fi

echo "Construyendo $GATEWAY_IMAGE..."
docker build --tag "$GATEWAY_IMAGE" "$PROJECT_DIR/gateway"

echo "Construyendo $CLIENT_IMAGE..."
docker build --tag "$CLIENT_IMAGE" "$PROJECT_DIR/client"

echo
echo "Imágenes creadas correctamente:"
docker image ls --format 'table {{.Repository}}\t{{.Tag}}\t{{.ID}}\t{{.Size}}' \
  | awk 'NR == 1 || $1 == "pcc-tunnel-gateway" || $1 == "pcc-tunnel-client"'

echo
echo "Usa estas imágenes en Portainer:"
echo "  $GATEWAY_IMAGE"
echo "  $CLIENT_IMAGE"
