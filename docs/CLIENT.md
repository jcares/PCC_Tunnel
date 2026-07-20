# PCC_Tunnel — Cliente

El Cliente es un agente que se ejecuta dentro de la red privada y mantiene una conexión saliente persistente con el Gateway.

## Responsabilidades

- Conectar al Gateway y completar el handshake de registro.
- Enviar heartbeat periódico para mantener la sesión viva.
- Reconectarse automáticamente con backoff progresivo si la conexión se pierde.
- Abrir conexiones TCP al servicio local para cada `OPEN_STREAM` recibido.
- Reenviar datos en ambas direcciones entre el Gateway y el servicio local.

## Configuración: `client/config/config.yaml`

| Campo | Descripción | Defecto |
|---|---|---|
| `server.url` | Dirección TCP del control del Gateway | — (requerido) |
| `client.id` | Identificador único del cliente | igual que `name` |
| `client.name` | Nombre del agente | — (requerido) |
| `client.token` | Token que debe coincidir con `PCC_AUTH_TOKEN` | `""` (sin auth) |
| `client.reconnect` | Segundos iniciales entre reintentos | `5` |
| `client.heartbeat` | Intervalo de PING/PONG en segundos | `5` |
| `proxy.local` | Servicio TCP local a exponer | `http://127.0.0.1:80` |
| `log.file` | Ruta del archivo de log | `logs/client.log` |

`PCC_GATEWAY_ADDR` sobreescribe `server.url` (útil en Docker Compose).

## Compilación

```powershell
cd client
go mod tidy
go build -o pcc-client.exe .
```

## Ejecución

```powershell
cd client
.\pcc-client.exe
```

## Reconexión automática

El cliente espera `reconnect` segundos entre reintentos y duplica el delay cada vez hasta un máximo de 60 segundos. Al reconectarse exitosamente, el delay se resetea al valor inicial.

## Logs

Los logs se escriben en consola y en el archivo `log.file`. Niveles usados: `[INFO]`, `[WARN]`, `[DEBUG]`, `[ERROR]`.
