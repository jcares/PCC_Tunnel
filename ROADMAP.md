# Roadmap PCC_Tunnel

## v1.0 — Implementado

- [x] Servidor PHP sobre HTTPS
- [x] API REST: register, heartbeat, poll, request, response
- [x] Autenticación HMAC-SHA256 con timestamp
- [x] Cola transaccional MySQL (FOR UPDATE)
- [x] Long polling en poll.php
- [x] Cliente Go con proxy HTTP local
- [x] Reconexión automática con backoff exponencial
- [x] Heartbeat periódico
- [x] Routing por dominio (tabla domains)
- [x] Panel web de administración
- [x] Migraciones versionadas
- [x] Docker y CasaOS
- [x] Logs de eventos en base de datos

## v1.1 — Próximas mejoras

- [ ] Compresión gzip en payloads grandes
- [ ] Soporte de WebSocket si el hosting lo habilita
- [ ] Tokens de acceso múltiples por cliente (tabla tokens)
- [ ] API REST del panel para integración externa
- [ ] Estadísticas de tráfico por cliente y por dominio
- [ ] Retención y limpieza automática de logs (cron)
- [ ] Ruta de health check pública `/api/health.php`

## v1.2 — Futuro

- [ ] Múltiples destinos locales por cliente
- [ ] Autenticación básica en el proxy local
- [ ] Cifrado adicional de los payloads en la cola MySQL
- [ ] CLI de administración en Go
- [ ] Empaquetado de instalador único para cPanel
