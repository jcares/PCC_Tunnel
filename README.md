# PCC_Tunnel

Sistema de túnel inverso autohospedado sobre HTTPS. Permite publicar servicios locales detrás de CGNAT usando únicamente un hosting compartido con cPanel (PHP + MySQL) y un cliente Go en la red privada. No requiere VPS, Docker en el servidor, ni herramientas de terceros (Cloudflare Tunnel, ngrok, Tailscale o FRP).

## Arquitectura

```
Usuario externo
     │
     ▼
Dominio (HTTPS)
     │
     ▼
Hosting compartido — PHP 8.x / Apache / LiteSpeed
     │
     ▼
API REST MySQL  ──── Long Polling ────► Cliente Go
                                              │
                                              ▼
                                       Servicio local
                                     (CasaOS / Docker)
```

No existe ningún gateway TCP. El servidor es PHP puro. El cliente Go hace polling HTTPS y ejecuta las solicitudes contra el servicio local.

## Componentes

| Componente | Ubicación | Descripción |
|---|---|---|
| Servidor PHP | `api/` | Control plane: colas, autenticación, heartbeat |
| Clases PHP | `Classes/` | Modelos, autenticación, controladores |
| Base de datos | `database/` | Migraciones y script de instalación |
| Panel Web | `panel/` | Administración: clientes, logs, dominios, usuarios |
| Cliente Go | `client/` | Agente local: polling, proxy HTTP, reconexión |

## Instalación rápida

Ver [`INSTALL.md`](INSTALL.md) para instrucciones completas de cPanel, CasaOS y compilación.

## Documentación

| Documento | Descripción |
|---|---|
| [`CLIENT.md`](CLIENT.md) | Configuración y uso del cliente Go |
| [`SERVER.md`](SERVER.md) | Descripción del servidor PHP |
| [`API.md`](API.md) | Protocolo de comunicación |
| [`INSTALL.md`](INSTALL.md) | Instalación paso a paso |
| [`ROADMAP.md`](ROADMAP.md) | Funcionalidades planificadas |

## Requisitos

### Servidor (hosting compartido)
- PHP 8.1+ con extensiones `pdo_mysql`, `openssl`, `hash`, `json`
- MySQL 5.7+ / MariaDB 10.5+
- Apache con `mod_rewrite` o LiteSpeed

### Cliente (red privada)
- Go 1.22+ para compilar
- Docker opcional (CasaOS / Raspberry Pi)
- Acceso HTTPS saliente al dominio del servidor
