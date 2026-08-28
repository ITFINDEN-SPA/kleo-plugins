<?php
/**
 * kleo-funlib — search.php
 *
 * Busca en la biblioteca de funciones PuntoBot (índice generado por build_index.php).
 *
 * Uso:
 *   php search.php "término" [--subsystem NOMBRE] [--type method|function] [--limit N] [--show-file] [--class CLASE]
 *
 * Ejemplos:
 *   php search.php "sentiment"
 *   php search.php "conversation" --subsystem puntobotapi --limit 10
 *   php search.php "send" --class BotChannelOutboundService
 */

$options = getopt('', ['subsystem::', 'type::', 'limit::', 'show-file', 'class::', 'help', 'index::']);
$query = $argv[1] ?? null;

if (isset($options['help']) || !$query) {
    fwrite(STDOUT, "kleo-funlib search\n  php search.php \"término\" [--subsystem N] [--type method|function] [--limit N] [--show-file] [--class C]\n");
    exit($query ? 0 : 1);
}

$indexDir = $options['index'] ?? __DIR__ . '/../index';
$allFile = "$indexDir/_all.json";
if (!is_file($allFile)) {
    fwrite(STDERR, "Índice no encontrado: $allFile. Ejecuta primero: php build_index.php\n");
    exit(1);
}

$all = json_decode(file_get_contents($allFile), true);
$subsystemFilter = $options['subsystem'] ?? null;
$typeFilter = $options['type'] ?? null;
$classFilter = $options['class'] ?? null;
$limit = (int)($options['limit'] ?? 15);
$showFile = isset($options['show-file']);

$q = mb_strtolower(trim($query));
$results = [];

foreach ($all as $subsystem => $data) {
    if ($subsystemFilter && $subsystem !== $subsystemFilter) continue;
    foreach ($data['functions'] as $f) {
        if ($typeFilter && $f['type'] !== $typeFilter) continue;
        if ($classFilter && ($f['class'] ?? null) !== $classFilter) continue;

        $haystack = mb_strtolower(implode(' ', array_filter([
            $f['name'],
            $f['class'] ?? '',
            $f['namespace'] ?? '',
            $f['signature'] ?? '',
            $f['summary'] ?? '',
        ])));

        $score = 0;
        if (mb_strpos(mb_strtolower($f['name']), $q) !== false) $score += 100;
        if (mb_strpos(mb_strtolower($f['class'] ?? ''), $q) !== false) $score += 50;
        if (mb_strpos(mb_strtolower($f['summary'] ?? ''), $q) !== false) $score += 20;
        if (mb_strpos(mb_strtolower($f['signature'] ?? ''), $q) !== false) $score += 10;
        if ($score === 0 && mb_strpos($haystack, $q) === false) continue;
        if ($score === 0) $score = 1;

        $results[] = ['score' => $score, 'subsystem' => $subsystem, 'f' => $f];
    }
}

usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

if (!$results) {
    fwrite(STDOUT, "Sin resultados para: $query\n");
    exit(0);
}

$count = count($results);
$shown = 0;
foreach ($results as $r) {
    if ($shown >= $limit) break;
    $f = $r['f'];
    $who = $f['type'] === 'method' ? ($f['class'] ?? '?') . '::' . $f['signature'] : $f['signature'];
    $vis = $f['visibility'] ?? 'public';
    $stat = $f['static'] ? 'static ' : '';
    $line = "[{$r['subsystem']}] {$vis} {$stat}{$who}";
    if ($showFile) {
        $line .= "\n    " . str_replace($_SERVER['HOME'], '~', $f['file']) . ':' . $f['line'];
    }
    if ($f['summary']) {
        $line .= "\n    " . $f['summary'];
    }
    fwrite(STDOUT, $line . "\n");
    $shown++;
}

fwrite(STDOUT, "\n($shown de $count resultados)\n");
