# kleo-codeaudit — Auditoría de seguridad y optimización

Audita el código de los subsistemas PuntoBot (local, en este equipo) buscando riesgos de seguridad y problemas de mantenimiento. **No toca producción** — solo lee el código local.

## Uso

```bash
./kleo-codeaudit                    # audita los 16 subsistemas (resumen)
./kleo-codeaudit puntobotcrm        # auditoría detallada de uno
./kleo-codeaudit puntobotapi --full # + duplicados y archivos grandes
```

## Revisiones

| # | Revisión | Detección |
|---|----------|-----------|
| 1 | Secretos hardcodeados | `sk_live`, `sk_test`, AWS keys, `ghp_`, `api_key=`/`secret=` largos en `app/`, `config/`, `routes/` |
| 2 | `.env` y permisos | Permisos != 600/640 y `APP_DEBUG=true` (peligroso en producción) |
| 3 | Funciones peligrosas | `eval(`, `shell_exec(`, `passthru(`, `system(` en `app/` |
| 4 | `unserialize` | Riesgo de RCE si la entrada no se valida |
| 5 | Estado git | Archivos sin commit y `.env` trackeado en git |
| 6 | `--full` | Archivos duplicados (md5) y archivos PHP > 500 KB |

## Hallazgos del último escaneo (QA local, MacBook Pro)

- **Todos los `.env` locales** con `APP_DEBUG=true` y permisos `644` — **aceptable en QA**, pero nunca desplegar esos `.env` a producción (el rsync de `kleo-prd-sync` los excluye; producción usa `APP_DEBUG` no definido/false y `.env` propios).
- 10 subsistemas **sin repositorio git** — candidatos a inicializar (ver plan kleo-collab/qa-sync).

## Salida de ejemplo

```
── puntobotcrm ──
  .env: permisos 644 ⚠️ (recomendado 600/640)
  🔴 APP_DEBUG=true en .env (producción debería ser false)
  git: 1 archivo(s) sin commit
```
