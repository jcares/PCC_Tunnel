# Instalación de PCC_Tunnel

## 1. Servidor — cPanel / Hosting compartido

### 1.1 Subir archivos

Sube las siguientes carpetas al `public_html` (o a un subdirectorio):

```
api/
Classes/
database/
panel/
server.env.example
```

Si instalas en un subdirectorio (por ejemplo `tunnel`), la URL base será `https://tudominio.example/tunnel/api`.

### 1.2 Configurar base de datos

1. Crea una base de datos MySQL desde cPanel → **MySQL Databases**.
2. Crea un usuario y asígnalo a la base de datos con todos los permisos.
3. Copia `server.env.example` a `.env` en la raíz del proyecto y rellena los valores:

```
PCC_DB_HOST=127.0.0.1
PCC_DB_NAME=pcc_tunnel_db
PCC_DB_USER=usuario_db
PCC_DB_PASS=contraseña_segura
PCC_REGISTRATION_KEY=clave-aleatoria-muy-larga
```

4. Carga `.env` desde tu `php.ini` o via `SetEnv` en `.htaccess`:

```apache
SetEnv PCC_DB_HOST 127.0.0.1
SetEnv PCC_DB_NAME pcc_tunnel_db
SetEnv PCC_DB_USER usuario_db
SetEnv PCC_DB_PASS contraseña_segura
SetEnv PCC_REGISTRATION_KEY clave-aleatoria-muy-larga
```

### 1.3 Ejecutar migraciones

Visita desde el navegador (una sola vez, después protege o elimina el archivo):

```
https://tudominio.example/database/install.php
```

Debe mostrar: `Database ready`

### 1.4 Crear usuario administrador del panel

Inserta el primer usuario directamente en la base de datos:

```sql
INSERT INTO users (email, password_hash)
VALUES ('admin@ejemplo.com', '$2y$12$...');
```

Para generar el hash:

```php
echo password_hash('tu-contraseña', PASSWORD_DEFAULT);
```

### 1.5 Verificar el panel

Accede a `https://tudominio.example/panel/` e inicia sesión.

---

## 2. Cliente — CasaOS

### 2.1 Usando Docker Compose (recomendado)

1. Copia `docker-compose.ghcr.yml` al servidor CasaOS.
2. Edita las variables de entorno:
   - `PCC_SERVER_URL=https://tudominio.example/api`
   - `PCC_AUTH_TOKEN=tu-token`
   - `PCC_REGISTRATION_KEY=tu-clave-registro`
   - `PCC_PROXY_LOCAL=http://host.docker.internal:PUERTO_LOCAL`
3. Importa desde **App Store → Custom Install → Import** o ejecuta:

```bash
docker compose -f docker-compose.ghcr.yml up -d
```

### 2.2 Compilando desde fuente

```bash
cd client
go mod tidy
go build -ldflags="-s -w" -o pcc-client .
./pcc-client
```

### 2.3 Servicio systemd (Linux / Raspberry Pi)

```ini
[Unit]
Description=PCC_Tunnel Client
After=network.target

[Service]
ExecStart=/usr/local/bin/pcc-client
WorkingDirectory=/opt/pcc-tunnel/client
Restart=always
RestartSec=10
Environment=PCC_SERVER_URL=https://tudominio.example/api
Environment=PCC_AUTH_TOKEN=tu-token
Environment=PCC_PROXY_LOCAL=http://127.0.0.1:80

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now pcc-client
```

---

## 3. Registro de un cliente

Si `PCC_REGISTRATION_KEY` está configurada, el cliente se auto-registra en el primer arranque.

También puedes registrar un cliente manualmente:

```bash
curl -X POST https://tudominio.example/api/register.php \
  -H 'Content-Type: application/json' \
  -H 'X-PCC-Registration-Key: tu-clave-registro' \
  -d '{"client_id":"cliente-01","name":"Mi CasaOS","token":"token-de-al-menos-16-chars"}'
```

---

## 4. Routing por dominio

Para que las solicitudes externas se enruten automáticamente a un cliente, inserta un dominio:

```sql
INSERT INTO domains (hostname, client_id, enabled)
VALUES ('servicio.tudominio.example', 'cliente-01', 1);
```

A partir de ese momento, cualquier solicitud con cabecera `Host: servicio.tudominio.example` se enruta al cliente `cliente-01`.

---

## 5. Verificación

1. Panel: `https://tudominio.example/panel/` → el cliente debe aparecer **online**.
2. Desde cualquier navegador: accede a `https://tudominio.example/api/request.php` con la cabecera `X-PCC-Client-ID: cliente-01`; el servidor debe responder con la respuesta de tu servicio local.
