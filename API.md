# Protocolo de comunicación PCC_Tunnel

Todo el protocolo funciona sobre HTTPS. No existe conexión TCP persistente. El diseño permite migrar a WebSocket sin cambiar la semántica de mensajes.

## Endpoints del servidor PHP

| Método | Endpoint | Actor | Descripción |
|---|---|---|---|
| POST | `/api/register.php` | Cliente | Provisión del cliente |
| POST | `/api/heartbeat.php` | Cliente | Mantiene el cliente online |
| POST | `/api/poll.php` | Cliente | Long polling: solicita trabajo |
| POST | `/api/response.php` | Cliente | Entrega la respuesta al servidor |
| `*` | `/api/request.php` | Externo | Entrada pública del túnel |
| `*` | `/api/upload.php` | Externo | Alias para subidas de archivos |
| `*` | `/api/stream.php` | Externo | Alias para streaming |
| POST | `/api/download.php` | Cliente | Alias para respuestas de descarga |

## Autenticación del cliente

Todas las llamadas del cliente deben incluir estas cabeceras:

```
X-PCC-Client-ID: <client_id>
X-PCC-Token: <token_en_claro>
X-PCC-Timestamp: <unix_timestamp>
X-PCC-Signature: <hmac_sha256>
```

**Firma:**
```
HMAC-SHA256( timestamp + "\n" + cuerpo_raw, token )
```

La firma es hexadecimal en minúsculas. El timestamp debe estar dentro de ±120 segundos del reloj del servidor.

## POST /api/register.php

**Cabecera adicional:** `X-PCC-Registration-Key: <clave>`

**Cuerpo:**
```json
{
  "client_id": "cliente-01",
  "name": "Mi servidor",
  "token": "token-de-al-menos-16-chars"
}
```

**Respuesta 200:**
```json
{ "ok": true, "client_id": "cliente-01" }
```

## POST /api/heartbeat.php

**Cuerpo:** `{}`

**Respuesta 200:**
```json
{ "ok": true, "server_time": 1700000000 }
```

## POST /api/poll.php (Long Polling)

El servidor espera hasta ~12 s antes de responder vacío.

**Cuerpo:** `{}`

**Respuesta 200 (con trabajo):**
```json
{
  "request_id": "uuid",
  "method": "GET",
  "path": "/ruta?query=param",
  "headers": { "Accept": "text/html" },
  "body": "<base64>"
}
```

**Respuesta 204:** sin trabajo pendiente.

## POST /api/response.php

**Cuerpo:**
```json
{
  "request_id": "uuid",
  "status_code": 200,
  "headers": { "Content-Type": "text/html; charset=utf-8" },
  "body": "<base64>"
}
```

**Respuesta 200:**
```json
{ "ok": true }
```

## Flujo completo

```
Usuario externo                Servidor PHP              Cliente Go
     │                              │                        │
     │── GET /api/request.php ─────►│                        │
     │                              │─ INSERT requests ─────►│
     │                              │                        │
     │                              │◄── POST poll.php ──────│
     │                              │─ claim + UPDATE ──────►│
     │                              │                        │── GET servicio local
     │                              │                        │◄─ respuesta local
     │                              │◄── POST response.php ──│
     │                              │─ INSERT responses      │
     │◄── 200 OK ──────────────────│                        │
```

## Códigos de error comunes

| Código | Descripción |
|---|---|
| 401 | No autenticado o firma inválida |
| 403 | Clave de registro incorrecta |
| 404 | Cliente o ruta no encontrados |
| 504 | El cliente no respondió en tiempo |
