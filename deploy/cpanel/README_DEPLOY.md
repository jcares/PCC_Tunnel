# Despliegue cPanel

1. Sube todo el contenido de esta carpeta a `public_html` o a un subdirectorio.
2. Abre el dominio en el navegador y completa el wizard de instalación con los datos de MySQL y del primer administrador.
3. El wizard ejecutará las migraciones y generará la configuración. Después, protege o elimina `setup.php`.
4. Accede al panel en `/panel/`.
5. Conserva `server.env.example` solo como referencia para las variables de entorno opcionales.

No subas `client/`, Docker, binarios ni archivos del repositorio raÃ­z.
