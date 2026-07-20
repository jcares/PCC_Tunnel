# PCC_Tunnel

Sistema de túnel inverso TCP para publicar servicios internos detrás de CGNAT. Similar a Cloudflare Tunnel / ngrok, permite que un cliente en una red privada exponga servicios locales a través de un Gateway público.

## Arquitectura

```
Usuario externo
      |
Gateway :8081 (público)
      |
Canal persistente :8080 (control)
      |
Cliente (red privada)
      |
Servicio local (127.0.0.1:80 u otro)
```

## Componentes

| Componente | Ubicación | Rol |
|---|---|---|
| Gateway | `gateway/` | Servidor central; acepta conexiones públicas y clientes |
| Cliente | `client/` | Agente en red privada; mantiene sesión persistente con Gateway |
| Panel | `panel/` | Panel administrativo PHP (base para futuras etapas) |

## Funcionalidades implementadas

- Handshake JSON `HELLO` / `SERVER_OK` con ID, nombre y token.
- Autenticación mediante token configurable (`PCC_AUTH_TOKEN`).
- Heartbeat `PING` / `PONG` con timeout configurable.
- Reconexión automática con backoff progresivo (hasta 60 s).
- Registro de clientes con ID, nombre, IP, estado y último heartbeat.
- Multiplexación de streams (`OPEN_STREAM`, `DATA`, `CLOSE_STREAM`).
- Forwarding TCP completo hacia servicio local configurable.
- Logs persistentes en `logs/` con niveles info / warn / debug / error.
- Dockerfiles multi-stage y Compose con volúmenes y healthcheck.

## Compilación local

```powershell
cd gateway
go mod tidy
go build -o pcc-gateway.exe .

cd ..\client
go mod tidy
go build -o pcc-client.exe .
```

## Ejecución local

**Terminal 1 — Gateway:**

```powershell
$env:PCC_AUTH_TOKEN = ""
cd gateway
.\pcc-gateway.exe
```

Salida esperada:
```
[INFO] PCC_Tunnel Gateway
[INFO] Listening control :8080
[INFO] Listening public :8081
```

**Terminal 2 — Cliente:**

```powershell
cd client
.\pcc-client.exe
```

Salida esperada:
```
[INFO] PCC_Tunnel Client
[INFO] Connected Gateway
[INFO] Client registered
```

## Configuración

### Gateway: `gateway/config/config.yaml`

```yaml
server:
  control_address: ":8080"
  public_address: ":8081"
  auth_token: ""
  heartbeat_timeout: 15    # segundos máximos sin PING antes de desconectar

log:
  level: "info"
  file: "logs/gateway.log"
```

Variables de entorno (Docker) sobreescriben el YAML:

| Variable | Descripción |
|---|---|
| `PCC_CONTROL_ADDR` | Dirección de escucha del control |
| `PCC_PUBLIC_ADDR` | Dirección de escucha pública |
| `PCC_AUTH_TOKEN` | Token de autenticación |
| `PCC_LOG_FILE` | Ruta del archivo de log |

### Cliente: `client/config/config.yaml`

```yaml
server:
  url: "127.0.0.1:8080"

client:
  id: "cliente-01"
  name: "cliente-01"
  token: ""
  reconnect: 5      # segundos iniciales entre reintentos
  heartbeat: 5      # intervalo de PING/PONG

proxy:
  local: "http://127.0.0.1:80"

log:
  level: "info"
  file: "logs/client.log"
```

Variable de entorno que sobreescribe `server.url`:

```
PCC_GATEWAY_ADDR=gateway:8080
```

## Pruebas PowerShell

Ejecutar desde la raíz del proyecto:

```powershell
. .\test_handshake.ps1
. .\test_heartbeat.ps1
. .\test_sessions.ps1
. .\test_forwarding.ps1
```

`test_forwarding.ps1` incluye un echo server interno; no requiere un servicio externo activo.

## Docker

```powershell
docker compose up --build
```

El Compose levanta Gateway y Cliente con reinicio automático, volúmenes de logs y healthcheck en el control del Gateway.

**Antes de usar en producción:**
- Definir `PCC_AUTH_TOKEN` con un valor secreto seguro.
- Colocar un proxy inverso (nginx / Caddy) con TLS delante del puerto público.
- Revisar puertos expuestos según el firewall del servidor.

## Publicar imágenes en GitHub Container Registry

El workflow [`docker-publish.yml`](.github/workflows/docker-publish.yml) construye y publica automáticamente las imágenes en GHCR cuando se hace push a `master` o a un tag `v*.*.*`.

Las imágenes quedan disponibles como:

```text
ghcr.io/jcares/pcc-tunnel-gateway:latest
ghcr.io/jcares/pcc-tunnel-client:latest
```

GitHub debe tener habilitado **Settings → Actions → General → Workflow permissions → Read and write permissions**. El primer uso puede requerir cambiar la visibilidad de los paquetes desde la sección **Packages** del perfil de GitHub.

## CasaOS

Para usar el Compose localmente en CasaOS:

1. Copiar el proyecto al servidor CasaOS.
2. Abrir una terminal en el servidor y situarse en la carpeta del proyecto.
3. Definir un token seguro y levantar los servicios:

```bash
export PCC_AUTH_TOKEN='cambia-este-token'
docker compose up --build -d
```

Para usar las imágenes publicadas desde CasaOS, sustituir `build:` en `docker-compose.yml` por estas líneas:

```yaml
gateway:
  image: ghcr.io/jcares/pcc-tunnel-gateway:latest

client:
  image: ghcr.io/jcares/pcc-tunnel-client:latest
```

Después se puede importar ese Compose desde **App Store → Custom Install → Import**. No se deben rellenar **Imagen Docker** y **Tag** con valores inventados en el instalador de contenedor individual.

Definir redirección de puertos en el router si se accede desde Internet.

## Protocolo

Ver [`docs/API.md`](docs/API.md) para la especificación completa de mensajes.

## Estado del proyecto

Código fuente completo. Próximos pasos:
- TLS entre Gateway y Cliente.
- Panel administrativo conectado a la API del Gateway.
- Métricas de tráfico por cliente.
- Múltiples túneles por cliente.
