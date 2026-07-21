<?php
declare(strict_types=1);

if (!is_file(__DIR__ . '/config.php')) {
    require __DIR__ . '/setup.php';
    exit;
}

header('Location: panel/', true, 302);
exit;
