# Cliente PCC_Tunnel

El cliente Go es el agente que corre en la red privada (CasaOS, Raspberry Pi, etc.) y conecta el servicio local con el servidor PHP en el hosting compartido usando únicamente HTTPS.

## Configuración

`client/config/config.yaml`:

```yaml
server:
  url: "https://tudominio.example/api"

client:
  id: "cliente-01"
  name: "Mi servidor CasaOS"
  token: "token-secreto-de-al-menos-16-caracteres"
  registration_key: ""      # solo necesario si el servidor requiere auto-registro
  reconnect: 5              # segundos iniciales entre reintentos
  heartbeat: 5              # intervalo entre heartbeats

proxy:
  local: "http://127.0.0.1:80"

ssl:
  verify: true              # false solo para certificados autofirmados en pruebas

log:
  level: "info"
  file: "logs/client.log"
```

## Variables de entorno

Las variables de entorno sobreescriben los valores del YAML:

| Variable | Descripción |
|---|---|
| `PCC_SERVER_URL` | URL base de la API PHP |
| `PCC_AUTH_TOKEN` | Token del cliente |
| `PCC_REGISTRATION_KEY` | Clave para auto-registro |
| `PCC_PROXY_LOCAL` | URL del servicio local |

## Compilación

```bash
cd client
go mod tidy
go build -ldflags="-s -w" -o pcc-client .
```

**Windows:**
```powershell
cd client
go mod tidy
go build -ldflags="-s -w" -o pcc-client.exe .
```

**Cross-compilación Linux en Windows:**
```powershell
$env:GOOS = "linux"; $env:GOARCH = "amd64"
go build -ldflags="-s -w" -o pcc-client-linux .
```

## Docker

```bash
cd client
docker build -t pcc-client .
docker run -d --restart unless-stopped \
  -e PCC_SERVER_URL=https://tudominio.example/api \
  -e PCC_AUTH_TOKEN=tu-token \
  -e PCC_PROXY_LOCAL=http://host.docker.internal:80 \
  --add-host host.docker.internal:host-gateway \
  pcc-client
```

## CasaOS

Importa `docker-compose.ghcr.yml` desde **App Store → Custom Install → Import** después de editar los valores de entorno.

## Comportamiento

1. Al iniciar, envía heartbeat para verificar conectividad.
2. Entra en un bucle de polling HTTPS: solicita trabajo al servidor.
3. Si hay una solicitud pendiente, la ejecuta contra el servicio local y devuelve la respuesta al servidor.
4. Mantiene heartbeat periódico para marcar el cliente como online.
5. Si la conexión falla, reintenta con backoff exponencial (hasta 60 s).

## Compatibilidad

- Linux (amd64, arm64, armv7)
- Windows (amd64)
- macOS (amd64, arm64)
- Docker
- CasaOS
- Raspberry Pi (arm64 / armv7)
