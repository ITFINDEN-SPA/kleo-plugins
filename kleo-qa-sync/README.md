# kleo-qa-sync — Sincronización Dev (MacBook Pro) → QA (Mac mini)

Sincroniza el código de un subsistema PuntoBot desde este equipo (fuente Dev) hacia el **Mac mini** (QA) vía rsync sobre SSH.

## Uso

```bash
./kleo-qa-sync <subsistema> [--dry-run]
```

Ejemplos:
```bash
./kleo-qa-sync puntobotcrm --dry-run   # ver qué se copiaría
./kleo-qa-sync puntobotcrm             # sincronizar de verdad
./kleo-qa-sync puntobotapi
```

## Qué sincroniza

- Origen: `~/home/<subsistema>/public_html` (MacBook Pro — Dev)
- Destino: `mac_mini:~/home/<subsistema>/public_html` (Mac mini — QA)
- **Excluye**: `vendor/`, `node_modules/`, `.env*`, `storage/`, `logs/`, `backups/`, `.git/`, `tmp/`, `mail/` (el entorno de cada lado mantiene sus propios artefactos y secretos)
- Usa `--delete` (el destino queda idéntico al origen en lo sincronizable)

## Verificación

Tras el rsync calcula un hash MD5 de los primeros 50 archivos PHP (sin vendor) en origen y destino; reporta `✅ Sincronizado correctamente` o `⚠️ Diferencias`.

## Nota de arquitectura

El **QA real del ecosistema es este MacBook Pro** (16 subsistemas con Git). El Mac mini es un QA secundario/físico — hasta ahora solo contiene `puntobotcrm`. `kleo-qa-sync` sirve para replicar cualquier subsistema a esa máquina cuando se requiera probar en hardware/entorno separado.
