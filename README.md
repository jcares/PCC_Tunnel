# PCC_Tunnel

Sistema de túnel inverso TCP para publicar servicios internos detrás de CGNAT. El Gateway recibe conexiones públicas y las transporta por una conexión persistente hacia el Cliente, que conecta con el servicio local.

## Estado del código

Implementado para desarrollo local:

- Handshake JSON `HELLO` / `SERVER_OK`.
- Autenticación opcional mediante `PCC_AUTH_TOKEN`.
- Heartbeat `PING` / `PONG` configurable.
- Reconexión automática con backoff progresivo.
- Registro concurrente de clientes activos.
- Multiplexación básica mediante `OPEN_STREAM`, `DATA` y `CLOSE_STREAM`.
- Forwarding TCP hacia `proxy.local`.
- Dockerfiles y Compose preparados, sin despliegue de producción.

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

Terminal del Gateway:

```powershell
$env:PCC_CONTROL_ADDR = ":8080"
$env:PCC_PUBLIC_ADDR = ":8081"
$env:PCC_AUTH_TOKEN = ""
cd gateway
.\pcc-gateway.exe
```

Terminal del Cliente:

```powershell
cd client
.\pcc-client.exe
```

El Gateway escucha el control en `:8080` y el tráfico público en `:8081`. El Cliente usa `client/config/config.yaml` para definir el Gateway, token, heartbeat, reconexión y servicio local.

## Pruebas PowerShell

```powershell
. .\test_handshake.ps1
. .\test_heartbeat.ps1
. .\test_sessions.ps1
```

La prueba del forwarding debe ejecutarse con un servicio TCP local activo en la dirección configurada y conectando un cliente TCP a `127.0.0.1:8081`.

## Docker

El Compose local crea Gateway y Cliente:

```powershell
docker compose up --build
```

Antes de usarlo en una red real, configurar `PCC_AUTH_TOKEN`, revisar puertos publicados y añadir TLS en el perímetro. CasaOS y dominio público no forman parte de esta etapa.

## Arquitectura

```text
Cliente externo -> Gateway :8081 -> canal persistente :8080 -> Cliente -> servicio local
```

El panel PHP en `panel/` es una base para usuarios, clientes, tokens, túneles, logs y estadísticas. Todavía no administra sesiones del Gateway.
