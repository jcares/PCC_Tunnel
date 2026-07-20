# Protocolo PCC_Tunnel

El control y el transporte usan mensajes JSON delimitados por salto de línea (`\n`) sobre TCP.

## Tipos de mensaje

| Tipo | Dirección | Descripción |
|---|---|---|
| `HELLO` | Cliente → Gateway | Presentación del cliente |
| `SERVER_OK` | Gateway → Cliente | Confirmación de registro |
| `PING` | Cliente → Gateway | Heartbeat |
| `PONG` | Gateway → Cliente | Respuesta al heartbeat |
| `OPEN_STREAM` | Gateway → Cliente | Solicitud de conexión al servicio local |
| `DATA` | Bidireccional | Payload del stream en curso |
| `CLOSE_STREAM` | Bidireccional | Cierre de un stream |
| `CLOSE` | Bidireccional | Cierre de sesión |

## Handshake (Fase 1)

El cliente inicia la conexión TCP y envía:

```json
{"type":"HELLO","id":"cliente-01","name":"cliente-01","token":"secreto"}
```

El Gateway valida el token y responde:

```json
{"type":"SERVER_OK"}
```

Si el token es inválido o el nombre está vacío, el Gateway cierra la conexión sin respuesta.

## Heartbeat (Fase 2)

El cliente envía periódicamente:

```json
{"type":"PING"}
```

El Gateway responde:

```json
{"type":"PONG"}
```

Si el Gateway no recibe un `PING` dentro del timeout configurado (`heartbeat_timeout`), cierra la sesión y marca el cliente como desconectado.

## Streams (Fase 6 y 7)

Cuando llega una conexión TCP al puerto público, el Gateway asigna un ID único y notifica al cliente:

```json
{"type":"OPEN_STREAM","stream_id":"1"}
```

Los bytes se transportan en `payload`; JSON serializa `[]byte` como Base64:

```json
{"type":"DATA","stream_id":"1","payload":"UENDX0ZPUldBUkRJTkdfVEVTVA=="}
```

Cualquiera de los extremos puede cerrar el stream:

```json
{"type":"CLOSE_STREAM","stream_id":"1"}
```

## Autenticación (Fase 4)

Si `PCC_AUTH_TOKEN` (o `auth_token` en YAML) no está vacío, el Gateway rechaza cualquier `HELLO` cuyo token no coincida. El token debe transportarse como secreto de entorno y nunca registrarse en logs.

## Selección de cliente

Cuando llega una conexión pública, el Gateway selecciona el cliente online con nombre lexicográficamente menor. En una futura versión se añadirá enrutamiento por nombre de dominio o política de carga.
