# kleo-install — Central de instalación del ecosistema Kleo (GitHub)

**GitHub es la central de instalación**: cualquier Mac (MacBook Pro, Mac mini, MacBook Air o uno nuevo) instala/actualiza el ecosistema completo con un solo comando. No depende de que otra máquina esté encendida.

## Repositorios centrales

| Repo | Contenido | Visibilidad |
|------|-----------|-------------|
| `itfinden/KLEO-Hardness` | El harness Kleo (fork propio) — branch `fork/kleo-rebrand` | privado |
| `ITFINDEN-SPA/kleo-plugins` | Los 9 plugins: funlib, infra, qa-sync, prd-sync, codeaudit, team, collab, provision, install | público |
| `ITFINDEN-SPA/kleo-mcp-whmcs` | Conector MCP Kleo↔WHMCS (clientes, tickets, facturas, dominios) | público |

## Instalar/actualizar en cualquier Mac

### 🚀 ONBOARDING DE UNA MAC NUEVA (todo automático, un comando)

```bash
curl -fsSL https://raw.githubusercontent.com/ITFINDEN-SPA/kleo-plugins/main/kleo-install/bootstrap-mac.sh -o ~/bootstrap-mac.sh && bash ~/bootstrap-mac.sh
```

El bootstrap hace todo en secuencia:
1. **Xcode Command Line Tools** (git + compiladores — puede pedir pulsa INSTALAR en un diálogo)
2. **Homebrew** (si falta)
3. **gh** (GitHub CLI, si falta)
4. **Autenticación GitHub** (cuenta `itfinden` — diálogo interactivo)
5. **kleo-install** (harness + plugins + MCP-WHMCS)

> Para autenticación **sin diálogo** (SSH/cabina): `GH_TOKEN=gho_xxx bash ~/bootstrap-mac.sh`

### En una máquina ya preparada

```bash
# Opción A: desde el harness (cualquier máquina ya clonada)
plugins/kleo-install/kleo-install

# Opción B: desde cero, sin tener nada (bootstrap directo)
curl -fsSL https://raw.githubusercontent.com/ITFINDEN-SPA/kleo-plugins/main/kleo-install/kleo-install -o ~/kleo-install.sh
bash ~/kleo-install.sh

# Modos
kleo-install --check            # ver estado (sin cambiar nada)
kleo-install --install-deps     # además pnpm install
kleo-install --dir RUTA         # instalar en otra ruta
```

> ⚠️ El script anteriormente se descargaba a `/tmp`; en Macs nuevos `/tmp` puede no ser escribible (error `curl: (56) Failure writing output`). **Descarga siempre a `~/` (home).**

El script clona/actualiza: **harness + plugins + MCP-WHMCS**, verifica toolchain (node/php/git/gh) y reporta el resultado.

## Requisito: autenticación GitHub

El harness es un repo **privado**, así que cada máquina necesita credenciales de la cuenta `itfinden`:

```bash
# En la máquina nueva:
brew install gh                     # o: /opt/homebrew/bin/brew install gh
gh auth login                       # con el token/SSO de la cuenta itfinden
gh auth setup-git                   # configura git para usar HTTPS con el token
```

> Para bots/máquinas headless, `echo $GH_TOKEN | gh auth login --with-token` funciona igual.

## Verificado (agosto 2026)

- **Mac mini**: instalación desde cero vía `kleo-install` → harness (49 entradas) + 9 plugins + MCP-WHMCS (13 fuentes) en ~1 min. ✅
- `kleo-funlib stats` → 16 subsistemas; `search sentiment` funciona en el mini. ✅
- Tablero del equipo sincronizado (4 tareas). ✅
- El MacBook Pro ya tenía el harness con `origin = KLEO-Hardness`; `--check` lo detecta correctamente. ✅

## Flujo de actualización diario

1. **Cada máquina**: `kleo-install` (pull de harness + plugins + MCP).
2. **Al publicar cambios de plugins**: desde el MacBook Pro →
   `cd plugins && git add -A && git commit -m "..." && git push`.
3. **Los subsistemas puntobot** siguen su propio flujo (repos `ITFINDEN-SPA/*.puntobot.test` + `kleo-prd-sync` para producción).
4. **El tablero de tareas** se sincroniza con `kleo-collab push/pull` (no va por git).
