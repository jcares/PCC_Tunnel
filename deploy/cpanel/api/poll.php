<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'method_not_allowed'], 405);
}

$client = authenticated_client();
touch_client($client['client_id']);
$queue = new \PccTunnel\Tunnels\RequestQueue(database());
for ($attempt = 0; $attempt < 25; $attempt++) {
    $request = $queue->claim($client['client_id']);
    if ($request) {
        respond([
            'request_id' => $request['id'],
            'method' => $request['method'],
            'path' => $request['path'],
            'headers' => json_decode($request['headers'], true) ?: [],
            'body' => base64_encode($request['body']),
        ]);
    }
    usleep(500000);
}
respond_empty();
