# kleo-prd-sync — Despliegue QA/Dev → Producción (server148)

Despliega el código de un subsistema PuntoBot desde este equipo hacia **producción** (server148 / vps148.itfinden.com) con backup automático y opción de migraciones.

> ⚠️ **MODIFICA PRODUCCIÓN** — usar con cuidado. Siempre probar antes con `--dry-run`.

## Uso

```bash
./kleo-prd-sync <subsistema> [--migrate] [--dry-run]
```

Ejemplos:
```bash
./kleo-prd-sync puntobotcrm --dry-run    # ver qué se desplegaría
./kleo-prd-sync puntobotcrm              # deploy sin migraciones
./kleo-prd-sync puntobotapi --migrate    # deploy + php artisan migrate --force
```

## Qué hace (en orden)

1. **Backup pre-deploy**: `tar czf /home/<user>/backups/deploy-<fecha>/app.tar.gz` con `app/`, `routes/`, `database/` del estado actual en producción.
2. **rsync** `~/home/<subsistema>/public_html/` → `server148:/home/<subsistema>/public_html/` con `--delete`.
   - Excluye: `vendor/`, `node_modules/`, `.env*`, `storage/framework`, `storage/logs`, `logs/`, `backups/`, `.git/`, `tmp/`, `mail/`
3. **(opcional `--migrate`)** `php artisan migrate --force` en producción.
4. **Limpieza de caché**: `config:clear`, `cache:clear`, `view:clear`.
5. **Verificación**: lista de controladores y respuesta HTTP del sitio.

## Seguridad

- Backup automático siempre (nunca se despliega sin respaldo previo).
- El usuario cPanel de destino es el nombre del subsistema (`puntobotapi`, `puntobotcrm`, …) — coincide con `/home/<subsistema>/public_html` confirmado en server148.
- El `.env` de producción NO se toca (excluido del rsync).

## Restauración de un deploy

```bash
ssh vps148.itfinden.com "tar xzf /home/<subsistema>/backups/deploy-<fecha>/app.tar.gz -C /home/<subsistema>/public_html"
```
