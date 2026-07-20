# PCC_Tunnel Gateway

El Gateway mantiene el canal de control y acepta conexiones públicas para reenviarlas a clientes online.

## Variables

- `PCC_CONTROL_ADDR`: listener JSON de clientes; valor local `:8080`.
- `PCC_PUBLIC_ADDR`: listener TCP público; valor local `:8081`.
- `PCC_AUTH_TOKEN`: token opcional para validar `HELLO`.

## Ejecución

```powershell
$env:PCC_AUTH_TOKEN = "secreto-local"
cd gateway
go run .
```

Salida esperada:

```text
PCC_Tunnel Gateway
Listening control :8080
Listening public :8081
```

El Gateway mantiene clientes activos en memoria, responde heartbeat, crea streams por conexión pública y elimina streams cuando cualquiera de los extremos se desconecta.

## Limitaciones actuales

- La selección de cliente usa el primer cliente online.
- El estado no se persiste en una base de datos.
- TLS público y panel administrativo todavía deben agregarse antes de producción.
