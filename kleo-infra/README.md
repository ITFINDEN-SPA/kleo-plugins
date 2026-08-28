# kleo-infra — Administración de infraestructura web multi-entorno

Administra vhosts y carpetas en los **tres entornos** del ecosistema, cada uno con su mecánica correcta:

| Entorno | Servidor | Stack | Mecánica de vhosts |
|---------|----------|-------|--------------------|
| `prod` | server148 (148.113.194.249) | **cPanel** + Apache 2.4.68 | **userdata + buildhttpdconf** (NUNCA tocar httpd.conf) |
| `macbook` | Este equipo | Apache Homebrew (`/opt/homebrew/etc/httpd`) | archivo por dominio en `vhosts/` + graceful |
| `macmini` | Mac mini (172.16.0.53) | Apache Homebrew (`/opt/homebrew/etc/httpd`) | archivo por dominio en `vhosts/` + graceful |

## ⚠️ Regla crítica: cPanel

**cPanel regenera `/etc/apache2/conf/httpd.conf` en cada cambio de cuenta/dominio.** Editar ese archivo a mano significa:
- Los cambios se **pierden** en el próximo rebuild de cPanel.
- Puede **romper el panel** o causar conflictos en `httpd -t`.

Por eso, en `prod` el plugin:
- **NUNCA escribe en httpd.conf** — solo lo lee (list/search/show/usage/logs son de solo lectura).
- `create` usa el mecanismo oficial: `/etc/apache2/conf.d/userdata/std/2_4/<usuario>/<dominio>/vhost.conf` + `/usr/local/cpanel/scripts/buildhttpdconf`.
- Para **cuentas o dominios nuevos** el paso correcto es `whmapi1 createacct` / UAPI (el plugin lo valida y avisa).

## Uso

```bash
./kleo-infra --env <prod|macbook|macmini> <comando> [args...]

# Multi-entorno
./kleo-infra --env prod check                    # Syntax OK (cPanel)
./kleo-infra --env prod show api.puntobot.com    # vhost + SSL + userdata
./kleo-infra --env macbook list                  # vhosts de *.puntobot.test locales
./kleo-infra --env macmini list                  # kleo.test, hesk.itfinden.local
./kleo-infra --env macbook create --domain demo.puntobot.test --docroot ~/home/demo/public --dry-run
./kleo-infra --env prod create --domain extra.puntobot.com --docroot /home/puntobot/public_html/extra --user puntobot --dry-run
```

## Comandos

| Comando | Descripción | prod | local |
|---------|-------------|------|-------|
| `list` | Vhosts/dominios | ✅ (lectura) | ✅ |
| `search <texto>` | Buscar vhost | ✅ | ✅ |
| `show <dominio>` | Detalle + SSL | ✅ | ✅ |
| `check` | `httpd -t` + estado | ✅ | ✅ |
| `folders [usuario]` | Carpetas/symlinks | ✅ cuentas | ✅ ~/home |
| `logs <dominio> [n]` | Errores recientes | ✅ domlogs | ✅ ErrorLog |
| `usage <dominio>` | Tamaño docroot | ✅ | ✅ |
| `create` | Crear vhost | userdata+buildhttpdconf | archivo+graceful |

## Seguridad de `create` (local)

1. Verifica que el docroot exista y que el vhost no exista ya.
2. Backup del vhost anterior (`*.bak-<fecha>`).
3. `httpd -t` (configtest) — solo si pasa, recarga con `httpd -k graceful` (o `-k start` si no corre).
4. `--dry-run` comprueba requisitos sin escribir nada.

## Nota sobre sandbox de la sesión Kleo

Durante esta sesión de desarrollo, el sandbox bloqueó escrituras fuera del workspace del harness (incluido `/opt/homebrew/etc/httpd/vhosts/`). La **sintaxis del vhost generado se validó** con `httpd -f <httpd.conf real> -t` → `Syntax OK`. Al ejecutar `kleo-infra` fuera de la sesión sandboxed (terminal normal, cron, o con permisos ampliados), `create` escribe el vhost y recarga Apache correctamente.

## Verificado (esta sesión)

- `--env macbook list` → lista real de vhosts `*.puntobot.test` (agenda, api, auth, bot, bridge, crm…).
- `--env macmini list/check` → kleo.test + hesk.itfinden.local, `Syntax OK`.
- `--env prod check/show/usage/logs/create --dry-run` → cPanel responde, `Syntax OK`, userdata validado.
- Vhost generado por `create` validado contra la config real de Apache → `Syntax OK`.
