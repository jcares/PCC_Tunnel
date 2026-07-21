<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/bootstrap.php';

$files = glob(__DIR__ . '/migrations/*.sql') ?: [];
sort($files, SORT_NATURAL);
foreach ($files as $file) {
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('No se pudo leer la migración: ' . basename($file));
    }
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            database()->exec($statement);
        }
    }
}

echo "Database ready\n";
