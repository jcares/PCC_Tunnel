<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'method_not_allowed'], 405);
}

$client = authenticated_client();
touch_client($client['client_id']);
respond(['ok' => true, 'server_time' => time()]);
