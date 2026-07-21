<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'method_not_allowed'], 405);
}

$client = authenticated_client();
$data = json_body();
$requestId = (string) ($data['request_id'] ?? '');
$statusCode = (int) ($data['status_code'] ?? 502);
$headers = is_array($data['headers'] ?? null) ? $data['headers'] : [];
$body = base64_decode((string) ($data['body'] ?? ''), true);
if ($requestId === '' || $body === false || $statusCode < 100 || $statusCode > 599) {
    respond(['error' => 'invalid_response'], 422);
}

$db = database();
$db->beginTransaction();
$check = $db->prepare("SELECT id FROM requests WHERE id = ? AND client_id = ? AND status = 'processing' FOR UPDATE");
$check->execute([$requestId, $client['client_id']]);
if (!$check->fetch()) {
    $db->rollBack();
    respond(['error' => 'request_not_owned'], 404);
}
$insert = $db->prepare('INSERT INTO responses (request_id, status_code, headers, body) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status_code = VALUES(status_code), headers = VALUES(headers), body = VALUES(body)');
$insert->execute([$requestId, $statusCode, json_encode($headers, JSON_UNESCAPED_SLASHES), $body]);
$update = $db->prepare("UPDATE requests SET status = 'completed', completed_at = UTC_TIMESTAMP() WHERE id = ?");
$update->execute([$requestId]);
$db->commit();
touch_client($client['client_id']);
write_log('info', 'response_received', $client['client_id'], ['request_id' => $requestId, 'status_code' => $statusCode]);
respond(['ok' => true]);
