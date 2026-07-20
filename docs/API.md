# API del protocolo

El control y el transporte usan JSON delimitado por salto de línea sobre TCP.

## Handshake

Cliente:

```json
{"type":"HELLO","name":"cliente-01","token":"secreto"}
```

Gateway:

```json
{"type":"SERVER_OK"}
```

## Heartbeat

```json
{"type":"PING"}
{"type":"PONG"}
```

## Streams

El Gateway abre un stream para cada conexión recibida en el puerto público:

```json
{"type":"OPEN_STREAM","stream_id":"1"}
```

Los bytes se transportan en `payload`; JSON serializa `[]byte` como Base64:

```json
{"type":"DATA","stream_id":"1","payload":"..."}
```

El cierre se indica con:

```json
{"type":"CLOSE_STREAM","stream_id":"1"}
```

## Seguridad

Si `PCC_AUTH_TOKEN` no está vacío, el Gateway rechaza cualquier `HELLO` cuyo token no coincida. El token debe transportarse mediante un secreto de entorno y no debe registrarse en logs.
