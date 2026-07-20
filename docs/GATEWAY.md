# PCC_Tunnel — Gateway

El Gateway es el servidor central. Recibe conexiones de Clientes (canal de control) y conexiones de usuarios externos (canal público), y las une mediante el sistema de streams.

## Responsabilidades

- Escuchar conexiones de control de Clientes (por defecto `:8080`).
- Escuchar conexiones públicas de usuarios externos (por defecto `:8081`).
- Validar el token de autenticación en el handshake.
- Registrar clientes con ID, nombre, IP remota, estado y último heartbeat.
- Responder a heartbeats y desconectar clientes inactivos.
- Crear streams para cada conexión pública y reenviarlos al cliente adecuado.
- Escribir logs en consola y en archivo persistente.

## Configuración: `gateway/config/config.yaml`

| Campo | Descripción | Defecto |
|---|---|---|
| `server.control_address` | Dirección de escucha del canal de control | `:8080` |
| `server.public_address` | Dirección de escucha pública | `:8081` |
| `server.auth_token` | Token de autenticación; vacío = sin autenticación | `""` |
| `server.heartbeat_timeout` | Segundos sin PING antes de desconectar el cliente | `15` |
| `log.file` | Ruta del archivo de log | `logs/gateway.log` |

## Variables de entorno (Docker)

Las variables sobreescriben los valores del YAML:

| Variable | Campo equivalente |
|---|---|
| `PCC_CONTROL_ADDR` | `server.control_address` |
| `PCC_PUBLIC_ADDR` | `server.public_address` |
| `PCC_AUTH_TOKEN` | `server.auth_token` |
| `PCC_LOG_FILE` | `log.file` |

## Compilación

```powershell
cd gateway
go mod tidy
go build -o pcc-gateway.exe .
```

## Ejecución local

```powershell
$env:PCC_AUTH_TOKEN = "mi-secreto"
cd gateway
.\pcc-gateway.exe
```

Salida esperada:
```
[INFO] PCC_Tunnel Gateway
[INFO] Control: :8080 | Publico: :8081
[INFO] Listening control :8080
[INFO] Listening public :8081
```

## Logs

Los logs se escriben en consola y en el archivo `log.file`. Niveles: `[INFO]`, `[WARN]`, `[DEBUG]`.

Ejemplo de sesión completa:
```
[INFO] Client connected: cliente-01 (id=cliente-01 ip=192.168.1.10) | Active clients: 1
[DEBUG] Heartbeat: cliente-01
[DEBUG] Stream 1 abierto hacia cliente cliente-01
[DEBUG] Stream 1 cerrado
[INFO] Client disconnected: cliente-01 | Active clients: 0
```

## Limitaciones actuales

- Un cliente por nombre; dos clientes con el mismo nombre se solapan.
- Sin TLS entre Gateway y Cliente (pendiente para producción).
- Panel administrativo no conectado a la API del Gateway.
