# Servidor PCC_Tunnel (PHP)

El servidor es una API REST escrita en PHP 8.x que corre en hosting compartido con cPanel. No requiere acceso root ni puertos TCP personalizados. Toda la comunicación es HTTPS.

## Estructura de archivos

```
api/
├── bootstrap.php       Conexión PDO, helpers, autoloader PSR-4
├── auth.php            Autenticación HMAC del cliente
├── register.php        Provisión de clientes
├── heartbeat.php       Actualiza estado del cliente
├── poll.php            Long polling: entrega solicitudes al cliente
├── request.php         Entrada pública del túnel (cola solicitudes)
├── response.php        Recibe respuesta del cliente
├── upload.php          Alias de request.php para subidas de archivos
├── download.php        Alias de response.php para descargas
├── stream.php          Alias de request.php para streaming
└── .htaccess           Fuerza HTTPS, bloquea archivos sensibles

Classes/
├── Auth/
│   └── ClientAuthenticator.php   Valida cabeceras + firma HMAC
├── Controllers/
│   └── ApiController.php         Cabeceras de control
├── Logs/
│   └── EventLogger.php           Registro de eventos
├── Models/
│   └── Client.php                CRUD de clientes
├── Tunnels/
│   └── RequestQueue.php          Cola transaccional de solicitudes
└── Users/
    └── User.php                  CRUD de usuarios

database/
├── install.php                   Ejecuta todas las migraciones
└── migrations/
    ├── 001_init.sql              Tablas base
    └── 002_control_plane.sql     Tokens y configuración

panel/
└── index.php                     Panel de administración web
```

## Autenticación

Cada solicitud del cliente debe incluir:

| Cabecera | Descripción |
|---|---|
| `X-PCC-Client-ID` | Identificador único del cliente |
| `X-PCC-Token` | Token en texto claro |
| `X-PCC-Timestamp` | Unix timestamp (±120 s de tolerancia) |
| `X-PCC-Signature` | `HMAC-SHA256(timestamp + "\n" + body, token)` |

## Protocolo de cola

1. Solicitud externa llega a `request.php` → se encola en MySQL con estado `pending`.
2. Cliente hace POST a `poll.php` → reclama la solicitud (estado `processing`, FOR UPDATE).
3. Cliente ejecuta la solicitud localmente y hace POST a `response.php`.
4. `request.php` detecta la respuesta y la devuelve al usuario externo.
5. Si no hay respuesta en 30 s, la solicitud expira con estado 504.

## Variables de entorno del servidor

| Variable | Descripción | Valor por defecto |
|---|---|---|
| `PCC_DB_HOST` | Host MySQL | `127.0.0.1` |
| `PCC_DB_NAME` | Base de datos | `pcc_tunnel` |
| `PCC_DB_USER` | Usuario MySQL | `root` |
| `PCC_DB_PASS` | Contraseña MySQL | `` |
| `PCC_REGISTRATION_KEY` | Clave para auto-registro de clientes | — |

Se definen en el archivo `.env` de cPanel o en el gestor de variables del hosting.
