# Despliegue cPanel

1. Sube todo el contenido de esta carpeta a `public_html` o a un subdirectorio.
2. Copia `server.env.example` como `.env` y completa las credenciales MySQL y `PCC_REGISTRATION_KEY`.
3. Crea la base de datos y el usuario desde cPanel.
4. Ejecuta una vez `database/install.php` y despuÃ©s protÃ©gelo o elimÃ­nalo.
5. Accede al panel en `/panel/`.

No subas `client/`, Docker, binarios ni archivos del repositorio raÃ­z.
