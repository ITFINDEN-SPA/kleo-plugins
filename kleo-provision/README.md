# kleo-provision — Aprovisionamiento 360 del ecosistema Kleo

Replica el harness Kleo **completo** (código fuente + plugins + configs) desde el MacBook Pro (fuente de verdad) hacia cualquier otra máquina: **Mac mini hoy, MacBook Air mañana**.

## Qué copia

| Contenido | Detalle |
|-----------|---------|
| Harness completo | `packages/`, `apps/`, `vendor/`, `docs/`, `scripts/`, `kleo-mcp-whmcs/`, etc. (~55.442 archivos, ~210MB) |
| **Plugins** | los 9: `kleo-funlib`, `kleo-infra`, `kleo-qa-sync`, `kleo-prd-sync`, `kleo-codeaudit`, `kleo-team`, `kleo-collab`, `kleo-provision` |
| `collab/` | estado compartido del equipo (tablero de tareas) |
| **Excluye** | `node_modules/`, `.git/`, `dist/`, `lib/`, `.DS_Store` (se regeneran o son artefactos del sistema) |

## Uso

```bash
# Primera vez (desde el MacBook Pro):
./kleo-provision mac_mini                # copiar todo (~1 min)
./kleo-provision mac_mini --install      # además pnpm install en destino

# Mantenimiento:
./kleo-provision mac_mini --verify-only  # comprobar que destino == origen
./kleo-provision mac_mini --dry-run      # ver qué se copiaría
```

## Cómo funciona (técnica)

- **Transporte**: `tar czf - | ssh "tar xzf -"` — compatible con openrsync de macOS y con rutas que contienen espacios (`KLEO HARNESS`). El rsync remoto falla en esas rutas ("server receiver mode requires two argument").
- **Idempotente**: el mismo comando se puede repetir; tar sobreescribe y el destino queda igual.
- **Verificación**: conteo de archivos origen vs destino (excluyendo `.DS_Store`). `✅ conteos idénticos` = 0 diferencias.
- **`.DS_Store`**: se limpian automáticamente en el destino antes de sincronizar (macOS los crea al navegar).

## Verificado (esta sesión)

- Mac mini: harness vacío → **aprovissionado en 56s**, 9 plugins desplegados.
- Post-sync: `origen: 55442 | destino: 55442 → ✅ conteos idénticos`.
- Función de búsqueda `kleo-funlib` operativa en destino (índices incluidos).

## Onboarding de una máquina nueva (p. ej. MacBook Air)

1. **Requisitos previos** en el Air:
   - macOS con **Node.js 22** (`nvm install 22` o Homebrew `node@22`).
   - Clave SSH del Air añadida a `~/.ssh/authorized_keys` del MacBook Pro (para `pull`), y la clave del MacBook Pro en el Air (para `push`).
   - Entrada en `~/.ssh/config` del MacBook Pro: `Host macbook-air` → `HostName <IP>`, `User <usuario>`.

2. **Aprovisionar desde el MacBook Pro**:
   ```bash
   plugins/kleo-provision/kleo-provision macbook-air            # ~1 min
   plugins/kleo-provision/kleo-provision macbook-air --install  # dependencias
   ```

3. **En el Air (primer arranque)**:
   ```bash
   cd ~/home/AI-STORE/KSH/KLEO_HARNESS
   pnpm install        # si no se usó --install
   pnpm run build      # compilar el harness
   # lanzar la GUI/web del harness según el flujo habitual de dsh web
   ```

4. **Verificar el ecosistema**:
   ```bash
   plugins/kleo-funlib/kleo-funlib stats                 # biblioteca de funciones
   plugins/kleo-infra/kleo-infra --env macbook check     # infra local
   plugins/kleo-collab/kleo-collab status                # tablero compartido
   ```

5. **Ciclo de trabajo diario** (los 3 agentes):
   - Cada máquina desarrolla con su harness local.
   - `kleo-team` + `kleo-collab push/pull` sincronizan tareas.
   - El código viaja por **Git/GitHub** (fuente de verdad).
   - `kleo-prd-sync` despliega a producción solo desde una máquina autorizada (recomendado: MacBook Pro).

## Rol de las máquinas (ecosistema 360)

| Máquina | Rol |
|---------|-----|
| **MacBook Pro** | Hub principal · fuente de verdad · despliegues a producción |
| **Mac mini** | QA físico · segundo harness · colaboración |
| **MacBook Air** | Tercer desarrollador (nuevo) |
| **server148** | Producción (cPanel) · solo lectura para plugins |
