<?php
/**
 * kleo-funlib — build_index.php
 *
 * Escanea los subsistemas PuntoBot (~/home/puntobot*) y extrae:
 *   - funciones y métodos (firma completa + docblock resumido)
 *   - clases (tipo, herencia)
 *   - rutas HTTP (routes/web.php y routes/api.php)
 *
 * Uso:
 *   php build_index.php [--root DIR] [--out DIR] [--only SUBSISTEMA]
 *
 * Salida: <out>/<subsistema>.json por subsistema + <out>/_all.json combinado.
 */

$options = getopt('', ['root::', 'out::', 'only::', 'help']);
$root = $options['root'] ?? ($_SERVER['HOME'] . '/home');
$out  = $options['out']  ?? __DIR__ . '/../index';
$only = $options['only'] ?? null;

$isCliMain = (PHP_SAPI === 'cli') && isset($argv[0]) && realpath($argv[0]) === __FILE__;
if ($isCliMain && isset($options['help'])) {
    fwrite(STDOUT, "kleo-funlib build_index\n  --root DIR   raíz con los subsistemas (default ~/home)\n  --out DIR    carpeta de salida del índice (default ../index)\n  --only NAME  indexar solo un subsistema\n");
    exit(0);
}

if (!$isCliMain) {
    // Modo include: solo definir funciones, no ejecutar.
    return;
}

if (!is_dir($out)) {
    mkdir($out, 0775, true);
}

$subsystems = [];
foreach (glob($root . '/puntobot*') as $dir) {
    if (!is_dir($dir)) continue;
    $name = basename($dir);
    $apps = [];
    // prioridad: public_html/app (lo que sirve el servidor web); laravel/app como respaldo
    if (is_dir("$dir/public_html/app")) $apps[] = "$dir/public_html/app";
    elseif (is_dir("$dir/laravel/app")) $apps[] = "$dir/laravel/app";
    if (!$apps) continue;
    if ($only && $name !== $only) continue;
    $subsystems[$name] = $apps;
}

if (!$subsystems) {
    fwrite(STDERR, "No se encontraron subsistemas en $root\n");
    exit(1);
}

$all = [];
foreach ($subsystems as $name => $apps) {
    echo "== $name ==\n";
    $index = [
        'subsystem' => $name,
        'scanned_at' => date('c'),
        'app_dirs' => $apps,
        'functions' => [],
        'classes' => [],
        'routes' => [],
        'files' => [],
    ];

    $phpFiles = [];
    foreach ($apps as $appDir) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($appDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->getExtension() === 'php') {
                $phpFiles[] = $file->getPathname();
            }
        }
    }
    sort($phpFiles);
    $index['files'] = $phpFiles;

    foreach ($phpFiles as $file) {
        $code = @file_get_contents($file);
        if ($code === false) continue;
        try {
            $tokens = token_get_all($code);
        } catch (Throwable $e) {
            continue;
        }
        parsePhpFile($tokens, $file, $index);
    }

    // Rutas
    foreach ($apps as $appDir) {
        $routesDir = dirname($appDir) . '/routes';
        foreach (['web.php', 'api.php'] as $rf) {
            $rfp = "$routesDir/$rf";
            if (is_file($rfp)) {
                $index['routes'] = array_merge($index['routes'], extractRoutes($rfp));
            }
        }
    }

    // Consolidar clases únicas
    $index['classes'] = array_values(array_unique($index['classes']));
    $index['function_count'] = count($index['functions']);
    $index['route_count'] = count($index['routes']);

    $all[$name] = $index;
    $json = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    file_put_contents("$out/$name.json", $json);
    echo "  {$index['function_count']} funciones, {$index['route_count']} rutas, " . count($index['files']) . " archivos\n";
}

file_put_contents("$out/_all.json", json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo "TOTAL: " . count($subsystems) . " subsistemas -> $out/_all.json\n";

/* ------------------------------------------------------------------ */

function parsePhpFile(array $tokens, string $file, array &$index): void
{
    $n = count($tokens);
    $i = 0;
    $namespace = '';
    $classStack = []; // [name, type, visibility-agnostic]
    $lastDoc = null;

    while ($i < $n) {
        $t = $tokens[$i];

        if (is_array($t)) {
            $id = $t[0];
            $text = $t[1];
            $line = $t[2];

            if ($id === T_DOC_COMMENT) {
                $lastDoc = $text;
                $i++;
                continue;
            }
            if ($id === T_NAMESPACE) {
                $ns = '';
                $i++;
                while ($i < $n && is_array($tokens[$i]) && in_array($tokens[$i][0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    $ns .= $tokens[$i][1];
                    $i++;
                }
                $namespace = $ns;
                $i++;
                continue;
            }
            if ($id === T_CLASS || $id === T_INTERFACE || $id === T_TRAIT || $id === T_ENUM) {
                // buscar nombre
                $j = $i + 1;
                $name = '';
                while ($j < $n) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) { $name = $tokens[$j][1]; break; }
                    if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE], true)) { $j++; continue; }
                    if ($tokens[$j] === '{' || $tokens[$j] === ';') break;
                    $j++;
                }
                if ($name !== '') {
                    $type = $id === T_CLASS ? 'class' : ($id === T_INTERFACE ? 'interface' : ($id === T_TRAIT ? 'trait' : 'enum'));
                    $classStack[] = $name;
                    $fq = $namespace !== '' ? "$namespace\\$name" : $name;
                    $index['classes'][] = $fq;
                }
                $i = $j + 1;
                continue;
            }
            if ($id === T_FUNCTION) {
                // anónima? (fn o function seguida de '(' o 'use')
                $j = $i + 1;
                $skipWs = $j;
                while ($skipWs < $n && is_array($tokens[$skipWs]) && $tokens[$skipWs][0] === T_WHITESPACE) $skipWs++;
                $next = $tokens[$skipWs] ?? null;
                if (is_array($next) && $next[0] === T_STRING) {
                    $fnName = $next[1];
                    // recolectar modificadores desde el último doc hasta aquí
                    $mods = collectModifiers($tokens, $i, $lastDoc);
                    $sig = parseFunctionSignature($tokens, $skipWs, $fnName);
                    $isStatic = in_array('static', $mods['flags'], true);
                    $isAbstract = in_array('abstract', $mods['flags'], true);
                    $visibility = $mods['visibility'];
                    $record = [
                        'name' => $fnName,
                        'type' => $classStack ? 'method' : 'function',
                        'class' => $classStack ? end($classStack) : null,
                        'namespace' => $namespace,
                        'visibility' => $visibility,
                        'static' => $isStatic,
                        'abstract' => $isAbstract,
                        'params' => $sig['params'],
                        'return_type' => $sig['return_type'],
                        'signature' => $sig['signature'],
                        'summary' => docSummary($lastDoc),
                        'doc' => $lastDoc,
                        'file' => $file,
                        'line' => $line,
                    ];
                    $index['functions'][] = $record;
                    $lastDoc = null; // no reutilizar docblock entre métodos
                }
                $i = $skipWs + 1;
                continue;
            }
            $i++;
            continue;
        }
        $i++;
    }
}

function collectModifiers(array $tokens, int $fnIndex, ?string &$lastDoc): array
{
    $flags = [];
    $visibility = null;
    $j = $fnIndex - 1;
    while ($j >= 0) {
        $t = $tokens[$j];
        if (is_array($t)) {
            $id = $t[0];
            $text = strtolower(trim($t[1]));
            if ($id === T_WHITESPACE) { $j--; continue; }
            if (in_array($text, ['public', 'protected', 'private'], true)) { $visibility = $text; }
            elseif (in_array($text, ['static', 'abstract', 'final', 'readonly'], true)) { $flags[] = $text; }
            elseif (in_array($text, ['function', ';', '{', '}', '->', '::'], true)) { break; }
            elseif (in_array($id, [T_FUNCTION, T_DOC_COMMENT], true)) { break; }
            else { $j--; continue; }
        } else {
            if (in_array($t, [';', '{', '}'], true)) break;
        }
        $j--;
    }
    return ['flags' => array_values(array_unique($flags)), 'visibility' => $visibility ?? 'public'];
}

function parseFunctionSignature(array $tokens, int $nameIndex, string $fnName): array
{
    // avanzar hasta '('
    $n = count($tokens);
    $j = $nameIndex + 1;
    while ($j < $n && $tokens[$j] !== '(') $j++;

    // parsear parámetros hasta ')' con profundidad
    $params = [];
    $j++; // saltar '('
    $depth = 0;
    $cur = ['name' => null, 'type' => '', 'default' => null, 'byref' => false, 'variadic' => false, 'raw' => ''];
    $seenName = false;

    while ($j < $n) {
        $t = $tokens[$j];
        if ($t === '(' || $t === '[' || $t === '{') {
            $depth++;
            $cur['raw'] .= is_array($t) ? $t[1] : $t;
            $j++;
            continue;
        }
        if ($t === ')' || $t === ']' || $t === '}') {
            if ($depth === 0 && $t === ')') break;
            $depth--;
            $cur['raw'] .= is_array($t) ? $t[1] : $t;
            $j++;
            continue;
        }
        if (is_array($t)) {
            $id = $t[0];
            $text = $t[1];
            if ($depth === 0 && $id === T_VARIABLE && !$seenName) {
                $cur['name'] = $text;
                $cur['byref'] = (trim($cur['raw']) === '&') || str_ends_with(trim($cur['raw']), '&');
                $seenName = true;
                $cur['raw'] .= $text;
                $j++;
                continue;
            }
            if ($depth === 0 && $id === T_ELLIPSIS) {
                $cur['variadic'] = true;
                $cur['raw'] .= '...';
                $j++;
                continue;
            }
            if ($depth === 0 && $id === T_STRING && $text === '...') {
                $cur['variadic'] = true;
                $cur['raw'] .= '...';
                $j++;
                continue;
            }
            if ($depth === 0 && $id === T_STRING && strtolower($text) === 'null' && !$seenName) {
                $cur['type'] = trim($cur['type'] . ' null');
                $cur['raw'] .= $text;
                $j++;
                continue;
            }
            if ($depth === 0 && !$seenName && in_array($id, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR, T_ARRAY, T_CALLABLE, T_STATIC], true)) {
                $cur['type'] = trim($cur['type'] . ' ' . $text);
                $cur['raw'] .= $text;
                $j++;
                continue;
            }
            if ($depth === 0 && !$seenName && $text === '?') {
                $cur['type'] = '?' . $cur['type'];
                $cur['raw'] .= '?';
                $j++;
                continue;
            }
            if ($depth === 0 && !$seenName && ($text === '|' || $text === '&')) {
                $cur['type'] .= $text;
                $cur['raw'] .= $text;
                $j++;
                continue;
            }
            if ($depth === 0 && $seenName && $t === '=') {
                $cur['default'] = '';
                $cur['raw'] .= ' = ';
                $j++;
                // capturar default hasta ',' o ')' (con profundidad)
                $d = 0;
                $closedByParen = false;
                while ($j < $n) {
                    $dt = $tokens[$j];
                    if (is_array($dt)) {
                        $dtext = $dt[1];
                        if ($d === 0 && $dtext === ',') { break; }
                        $cur['default'] .= $dtext;
                        $cur['raw'] .= $dtext;
                        $j++;
                        continue;
                    }
                    $dchar = $dt;
                    if ($d === 0 && $dchar === ',') break;
                    if ($d === 0 && $dchar === ')') { $closedByParen = true; break; }
                    if ($dchar === '(' || $dchar === '[' || $dchar === '{') $d++;
                    if ($dchar === ')' || $dchar === ']' || $dchar === '}') $d--;
                    $cur['default'] .= $dchar;
                    $cur['raw'] .= $dchar;
                    $j++;
                }
                $cur['default'] = trim((string)$cur['default']);
                $params[] = $cur;
                $cur = ['name' => null, 'type' => '', 'default' => null, 'byref' => false, 'variadic' => false, 'raw' => ''];
                $seenName = false;
                if ($closedByParen) {
                    // el ')' ya consumido: salir del while principal, $j apunta tras ')'
                    break;
                }
                $j++;
                continue;
            }
            if ($depth === 0 && $id === T_VARIABLE && $seenName) {
                // segundo variable? (tipo raro) — cerrar param anterior
                $params[] = $cur;
                $cur = ['name' => null, 'type' => '', 'default' => null, 'byref' => false, 'variadic' => false, 'raw' => ''];
                $seenName = false;
                continue;
            }
            $cur['raw'] .= $text;
            $j++;
            continue;
        }
        // símbolo simple
        if ($depth === 0 && $t === ',') {
            $params[] = $cur;
            $cur = ['name' => null, 'type' => '', 'default' => null, 'byref' => false, 'variadic' => false, 'raw' => ''];
            $seenName = false;
            $j++;
            continue;
        }
        if ($depth === 0 && $seenName && $t === '=') {
            $cur['default'] = '';
            $cur['raw'] .= ' = ';
            $j++;
            // capturar default hasta ',' o ')' (con profundidad)
            $d = 0;
            $closedByParen = false;
            while ($j < $n) {
                $dt = $tokens[$j];
                if (is_array($dt)) {
                    $dtext = $dt[1];
                    if ($d === 0 && $dtext === ',') { break; }
                    $cur['default'] .= $dtext;
                    $cur['raw'] .= $dtext;
                    $j++;
                    continue;
                }
                $dchar = $dt;
                if ($d === 0 && $dchar === ',') break;
                if ($d === 0 && $dchar === ')') { $closedByParen = true; break; }
                if ($dchar === '(' || $dchar === '[' || $dchar === '{') $d++;
                if ($dchar === ')' || $dchar === ']' || $dchar === '}') $d--;
                $cur['default'] .= $dchar;
                $cur['raw'] .= $dchar;
                $j++;
            }
            $cur['default'] = trim((string)$cur['default']);
            $params[] = $cur;
            $cur = ['name' => null, 'type' => '', 'default' => null, 'byref' => false, 'variadic' => false, 'raw' => ''];
            $seenName = false;
            if ($closedByParen) {
                // ')' ya consumido: salir del while principal
                break;
            }
            $j++;
            continue;
        }
        if ($depth === 0 && !$seenName && ($t === '?' || $t === '|' || $t === '&')) {
            $cur['type'] .= $t;
            $cur['raw'] .= $t;
            $j++;
            continue;
        }
        $cur['raw'] .= is_array($t) ? $t[1] : $t;
        $j++;
    }
    if ($cur['name'] !== null || trim($cur['raw']) !== '') {
        $params[] = $cur;
    }
    // descartar params residuales vacíos (solo whitespace)
    $params = array_values(array_filter($params, fn($p) => $p['name'] !== null || trim($p['raw']) !== ''));

    // return type: tras ')' hasta '{' o ';'
    $returnType = '';
    $j++; // saltar ')'
    while ($j < $n) {
        $t = $tokens[$j];
        if (is_array($t)) {
            $text = $t[1];
            if ($text === '{' || $text === ';') break;
            if ($text === 'use') break; // closure use
            if (trim($text) === ':') { $j++; continue; } // ': ' del return type
            $returnType .= $text;
            $j++;
            continue;
        }
        if ($t === '{' || $t === ';') break;
        if ($t === ':') { $j++; continue; }
        $returnType .= $t;
        $j++;
    }
    $returnType = trim(preg_replace('/\s+/', ' ', $returnType));
    if ($returnType === '') $returnType = null;

    // normalizar tipos: quitar espacios tras ? | &
    foreach ($params as &$p) {
        $p['type'] = preg_replace('/\s*([?|&])\s*/', '$1', trim($p['type']));
        $p['type'] = preg_replace('/\s+/', ' ', $p['type']);
    }
    unset($p);

    // firma legible
    $parts = [];
    foreach ($params as $p) {
        $s = '';
        if ($p['type'] !== '') $s .= $p['type'] . ' ';
        if ($p['byref']) $s .= '&';
        if ($p['variadic']) $s .= '...';
        $s .= $p['name'] ?? '?';
        if ($p['default'] !== null) $s .= ' = ' . $p['default'];
        $parts[] = $s;
    }
    $signature = $fnName . '(' . implode(', ', $parts) . ')' . ($returnType !== null ? ': ' . $returnType : '');

    return ['params' => $params, 'return_type' => $returnType, 'signature' => $signature];
}

function docSummary(?string $doc): ?string
{
    if (!$doc) return null;
    // primer párrafo de descripción: líneas antes del primer tag @
    $desc = [];
    $lines = explode("\n", $doc);
    foreach ($lines as $line) {
        $line = trim(preg_replace('/^\/\*\*|\*\/$/', '', $line));
        $line = trim($line, "* \t");
        if (str_starts_with($line, '@')) break;
        if ($line === '') { if ($desc) break; continue; }
        $desc[] = $line;
    }
    if (!$desc) return null;
    return mb_substr(implode(' ', $desc), 0, 300);
}

function extractRoutes(string $file): array
{
    $code = @file_get_contents($file);
    if ($code === false) return [];
    $routes = [];
    // Route::get('uri', [C::class,'m'])->name('x')
    if (preg_match_all(
        '/Route::(get|post|put|patch|delete|any|match|resource|redirect|view)\s*\(\s*(\'|")([^\'"]+)(\'|")\s*(?:,\s*(?:\[([^\]]+)\]|(\'|")([^\'"]+)(\'|")))?/',
        $code,
        $m,
        PREG_SET_ORDER
    )) {
        foreach ($m as $r) {
            $routes[] = [
                'method' => strtoupper($r[1]),
                'uri' => $r[3],
                'action' => isset($r[7]) && $r[7] !== '' ? $r[7] : (isset($r[5]) ? trim($r[5]) : null),
                'file' => $file,
            ];
        }
    }
    return $routes;
}
