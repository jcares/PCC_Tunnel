<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'method_not_allowed'], 405);
}

$registrationKey = env_value('PCC_REGISTRATION_KEY');
if ($registrationKey === null || !hash_equals($registrationKey, (string) ($_SERVER['HTTP_X_PCC_REGISTRATION_KEY'] ?? ''))) {
    respond(['error' => 'registration_not_authorized'], 403);
}

$data = json_body();
$clientId = trim((string) ($data['client_id'] ?? ''));
$name = trim((string) ($data['name'] ?? $clientId));
$token = (string) ($data['token'] ?? '');
if ($clientId === '' || $name === '' || strlen($token) < 16) {
    respond(['error' => 'client_id_name_and_token_required'], 422);
}

$statement = database()->prepare(
    'INSERT INTO clients (client_id, name, token_hash, status, last_seen_at) VALUES (?, ?, ?, \'online\', UTC_TIMESTAMP())
     ON DUPLICATE KEY UPDATE name = VALUES(name), token_hash = VALUES(token_hash), status = \'online\', enabled = 1, last_seen_at = UTC_TIMESTAMP()'
);
$statement->execute([$clientId, $name, password_hash($token, PASSWORD_DEFAULT)]);
write_log('info', 'client_registered', $clientId, ['name' => $name]);
respond(['ok' => true, 'client_id' => $clientId]);
