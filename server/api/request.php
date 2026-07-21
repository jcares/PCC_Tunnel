<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = $_SERVER['REQUEST_URI'] ?? '/';
$clientId = trim((string) ($_SERVER['HTTP_X_PCC_CLIENT_ID'] ?? ''));
if ($clientId === '') {
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $statement = database()->prepare('SELECT client_id FROM domains WHERE hostname = ? AND enabled = 1 LIMIT 1');
    $statement->execute([explode(':', $host)[0]]);
    $clientId = (string) ($statement->fetchColumn() ?: '');
}
if ($clientId === '') {
    respond(['error' => 'client_route_not_found'], 404);
}

$id = sprintf('%s-%s-%s-%s-%s', bin2hex(random_bytes(4)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(6)));
$headers = array_filter(request_headers(), static fn (string $name): bool => !in_array(strtolower($name), ['connection', 'content-length', 'host', 'transfer-encoding'], true), ARRAY_FILTER_USE_KEY);
$statement = database()->prepare('INSERT INTO requests (id, client_id, method, path, headers, body) VALUES (?, ?, ?, ?, ?, ?)');
$statement->execute([$id, $clientId, $method, $path, json_encode($headers, JSON_UNESCAPED_SLASHES), request_body()]);
write_log('info', 'request_queued', $clientId, ['request_id' => $id, 'method' => $method]);

$db = database();
for ($attempt = 0; $attempt < 60; $attempt++) {
    $result = $db->prepare('SELECT status_code, headers, body FROM responses WHERE request_id = ? LIMIT 1');
    $result->execute([$id]);
    $response = $result->fetch();
    if ($response) {
        foreach (json_decode($response['headers'], true) ?: [] as $name => $value) {
            if (!in_array(strtolower((string) $name), ['connection', 'content-length', 'transfer-encoding'], true)) {
                header($name . ': ' . str_replace(["\r", "\n"], '', (string) $value));
            }
        }
        http_response_code((int) $response['status_code']);
        echo $response['body'];
        exit;
    }
    usleep(500000);
}

$expire = $db->prepare("UPDATE requests SET status = 'expired' WHERE id = ? AND status IN ('pending', 'processing')");
$expire->execute([$id]);
write_log('warn', 'request_expired', $clientId, ['request_id' => $id]);
respond(['error' => 'upstream_timeout'], 504);
