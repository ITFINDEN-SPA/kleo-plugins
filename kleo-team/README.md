# kleo-team — Gestión de equipos de trabajo

Tablero de tareas simple y compartido para coordinar el desarrollo del ecosistema PuntoBot entre agentes Kleo y desarrolladores.

## Uso

```bash
./kleo-team init                          # crear tablero
./kleo-team add --title "X" [--assign NOMBRE] [--prio alta|media|baja] [--scope ruta]
./kleo-team list [--status E] [--assign N]
./kleo-team start <id> [NOMBRE]           # marcar en progreso (y asignar)
./kleo-team done <id>                     # completar
./kleo-team todo                          # próxima tarea pendiente recomendada
./kleo-team status                        # resumen del tablero
./kleo-team report                        # resumen por persona y estado
```

## Ejemplo

```bash
./kleo-team add --title "Inicializar git en 10 subsistemas" --assign kleo-macbook --prio alta
./kleo-team start 1 kleo-macbook
./kleo-team done 1
./kleo-team report
```

## Integración con kleo-collab

El tablero vive en `plugins/kleo-team/board/tasks.tsv` y se sincroniza entre máquinas con:

```bash
./kleo-collab push   # MacBook Pro → Mac mini
./kleo-collab pull   # Mac mini → MacBook Pro
```

Así, una tarea completada por el agente del Mac mini aparece en el tablero del MacBook Pro y viceversa.

## Estado actual

Ver `./kleo-team status` para el resumen en vivo. Tareas iniciales registradas durante el arranque de la suite: inicializar Git en subsistemas sin repo, corregir APP_DEBUG en QA, conectar n8n con la API.
