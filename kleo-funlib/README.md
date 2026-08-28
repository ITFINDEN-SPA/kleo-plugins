# kleo-funlib — Biblioteca de funciones PuntoBot

Plugin de Kleo que indexa **todas las funciones, métodos y rutas** del ecosistema PuntoBot para reutilizar código ya escrito **sin gastar tokens** volviéndolo a generar.

## ¿Qué hace?

Escanea los 16 subsistemas de `~/home/puntobot*` y construye un índice JSON con:

- **Funciones y métodos** — firma completa (visibilidad, static, parámetros con tipos y defaults, return type), clase, namespace, línea y docblock resumido.
- **Clases** — con su FQN.
- **Rutas HTTP** — `Route::get/post/...` de `routes/web.php` y `routes/api.php`.

El índice vive en `index/` (un JSON por subsistema + `_all.json` combinado).

## Comandos

```bash
./kleo-funlib build                 # re-indexar todo (rápido: <1s)
./kleo-funlib search "término"      # buscar funciones
./kleo-funlib search "factura" --subsystem puntobotapi --limit 10
./kleo-funlib search "send" --class BotChannelOutboundService --show-file
./kleo-funlib stats                 # resumen del índice
```

## Ejemplos de salida

```
$ ./kleo-funlib search "sentiment" --limit 3
[puntobotapi] private BotAutoReplyService::detectSentiment(string $lowerContent): string
```

```
$ ./kleo-funlib search "factura" --subsystem puntobotapi --limit 5
[puntobotapi] public ContabilidadChileController::facturasCompra(Request $request): JsonResponse
[puntobotapi] public ContabilidadChilenaService::crearFacturaCompra(int $empresaId, array $datos): array
[puntobotapi] public ContabilidadService::crearFacturaVenta(array $datos): array
...
```

## Integración con Kleo

Cuando una tarea requiera una función que probablemente ya existe, Kleo debe:

1. `./kleo-funlib search "<concepto>"` para localizar la función existente.
2. Leer el archivo fuente indicado (con `--show-file`) y reutilizar la implementación.
3. Solo generar código nuevo si la búsqueda no arroja resultados.

## Detalles técnicos

- Parser PHP con `token_get_all` (sin dependencias externas; requiere PHP ≥ 8.1).
- Prioriza `public_html/app` (lo que sirve el servidor web); usa `laravel/app` como respaldo para evitar duplicados.
- `build_index.php` se puede incluir como librería (`require`) — las funciones de parseo quedan disponibles sin ejecutar el escaneo.

## Estado del índice (última construcción)

Ver `./kleo-funlib stats` para el conteo actual.
