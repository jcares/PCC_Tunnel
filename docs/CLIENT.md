# PCC_Tunnel Client

El Cliente se ejecuta dentro de la red privada y mantiene una conexión saliente persistente con el Gateway.

## Configuración

Archivo: `client/config/config.yaml`

- `server.url`: dirección TCP del control del Gateway.
- `client.name`: nombre del agente.
- `client.token`: token que debe coincidir con `PCC_AUTH_TOKEN`.
- `client.reconnect`: segundos iniciales entre reintentos.
- `client.heartbeat`: intervalo de `PING/PONG`.
- `proxy.local`: servicio TCP local que recibirá cada stream.

`PCC_GATEWAY_ADDR` puede sobrescribir `server.url`, especialmente dentro de Compose.

## Ejecución

```powershell
cd client
go run .
```

El Cliente conecta, registra su identidad, procesa `OPEN_STREAM`, conecta al servicio local y reenvía datos en ambas direcciones.
