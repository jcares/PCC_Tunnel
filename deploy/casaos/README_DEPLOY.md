# Despliegue CasaOS

1. Copia esta carpeta completa a CasaOS.
2. Copia `.env.example` como `.env` y completa las variables.
3. Ajusta `config.yaml` si necesitas cambiar el cliente o el servicio local.
4. Ejecuta `docker compose up -d`.
5. Usa `docker compose logs -f client` para verificar la conexiÃ³n.

Esta carpeta solo contiene el cliente Go y sus archivos Docker. No incluye PHP,
panel ni base de datos.
