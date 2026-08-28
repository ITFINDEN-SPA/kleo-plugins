# kleo-collab — Desarrollo colaborativo entre harness (Mac mini ↔ MacBook Pro)

Sincroniza el **estado de trabajo del equipo** entre las dos máquinas para que ambos agentes Kleo avancen en paralelo sin pisarse.

## Modelo

```
MacBook Pro (Hub)          Mac mini (Worker)
  kleo-team (tablero)  ←→   kleo-team (tablero)
  plugins/kleo-*            plugins/kleo-*
  Git/GitHub (código)  ←→   (replica)
```

- **El código** se versiona en Git/GitHub (fuente de verdad).
- **El estado del trabajo** (tablero de tareas, notas, decisión) se sincroniza con `kleo-collab`.

## Uso

```bash
./kleo-collab init      # crear dir compartido en el Mac mini
./kleo-collab push      # Hub → Mini (tablero + ESTADO.md)
./kleo-collab pull      # Mini → Hub (aplicar cambios del mini al tablero)
./kleo-collab status    # comparar estado entre máquinas
```

## Detalles técnicos

- Transporte: `tar` + `ssh` (compatible con openrsync de ambos macOS, a diferencia de rsync entre versiones).
- Rutas: hub = `plugins/collab/`, mini = `/Users/friedrich/home/kleo-collab` (sin espacios en la ruta del mini).
- `pull` aplica automáticamente el tablero recibido al `kleo-team` local.

## Flujo de trabajo recomendado

1. Cada agente usa `kleo-team` en su máquina (tareas locales).
2. Terminada una tarea → `kleo-collab push` (el otro agente ve el avance).
3. Antes de empezar → `kleo-collab pull` (traer tareas nuevas del otro).
4. El código siempre viaja por Git/GitHub; el collab solo sincroniza el estado.

## Verificado

- `push` y `pull` probados en ambas direcciones con el tablero real (tarea #9 creada en el Mac mini → aplicada en el MacBook Pro).
- `status` compara hashes MD5 de los tableros y confirma sincronización.
