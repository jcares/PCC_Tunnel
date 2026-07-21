<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

session_start();

if (!isset($_SESSION['admin_email'])) {
    respond(['error' => 'authentication_required'], 401);
}

if (!isset($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

function admin_csrf_valid(): bool
{
    $headers = request_headers();
    $token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';
    return is_string($token) && $token !== '' && hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $token);
}

function setting_values(): array
{
    $statement = database()->prepare(
        "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('update_channel', 'github_repository', 'update_check_interval', 'wizard_completed')"
    );
    $statement->execute();
    $settings = [];
    foreach ($statement->fetchAll() as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return [
        'update_channel' => in_array($settings['update_channel'] ?? 'stable', ['stable', 'beta', 'dev'], true) ? $settings['update_channel'] : 'stable',
        'github_repository' => (string) ($settings['github_repository'] ?? ''),
        'update_check_interval' => max(60, min(604800, (int) ($settings['update_check_interval'] ?? 86400))),
        'wizard_completed' => ($settings['wizard_completed'] ?? '0') === '1',
    ];
}

function github_metadata(string $repository, string $channel): array
{
    if ($repository === '') {
        return ['configured' => false, 'message' => 'No hay repositorio configurado.'];
    }
    if (!preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repository)) {
        return ['configured' => false, 'message' => 'El repositorio configurado no es válido.'];
    }

    $url = 'https://api.github.com/repos/' . rawurlencode(strtok($repository, '/')) . '/' . rawurlencode(substr($repository, strpos($repository, '/') + 1)) . '/releases/latest';
    $context = stream_context_create(['http' => [
        'method' => 'GET',
        'timeout' => 5,
        'ignore_errors' => true,
        'header' => "Accept: application/vnd.github+json\r\nUser-Agent: PCC-Tunnel-Panel\r\n",
    ]]);
    $body = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $match)) {
            $status = (int) $match[1];
            break;
        }
    }
    $release = is_string($body) ? json_decode($body, true) : null;
    if ($status < 200 || $status >= 300 || !is_array($release)) {
        return ['configured' => true, 'available' => false, 'channel' => $channel, 'message' => 'No se pudo consultar GitHub.'];
    }
    return [
        'configured' => true,
        'available' => true,
        'channel' => $channel,
        'version' => isset($release['tag_name']) ? (string) $release['tag_name'] : null,
        'published_at' => isset($release['published_at']) ? (string) $release['published_at'] : null,
        'url' => isset($release['html_url']) ? (string) $release['html_url'] : null,
    ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';
if (!is_string($action) || !in_array($action, ['health', 'settings', 'logs', 'updates'], true)) {
    respond(['error' => 'invalid_action'], 400);
}

if ($method === 'POST' && $action !== 'settings') {
    respond(['error' => 'method_not_allowed'], 405);
}
if ($method !== 'GET' && $method !== 'POST') {
    respond(['error' => 'method_not_allowed'], 405);
}
if ($method === 'POST' && !admin_csrf_valid()) {
    respond(['error' => 'csrf_failed'], 403);
}

if ($action === 'health') {
    require_once __DIR__ . '/health.php';
    exit;
}

if ($action === 'settings') {
    if ($method === 'POST') {
        $input = json_body();
        $channel = $input['update_channel'] ?? null;
        $repository = $input['github_repository'] ?? null;
        $interval = $input['update_check_interval'] ?? 86400;
        $wizard = $input['wizard_completed'] ?? null;
        if (!is_string($channel) || !in_array($channel, ['stable', 'beta', 'dev'], true)) {
            respond(['error' => 'invalid_update_channel'], 422);
        }
        if (!is_string($repository) || strlen($repository) > 255 || ($repository !== '' && !preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repository))) {
            respond(['error' => 'invalid_github_repository'], 422);
        }
        if (!is_int($interval) && !(is_string($interval) && ctype_digit($interval))) {
            respond(['error' => 'invalid_update_check_interval'], 422);
        }
        $interval = (int) $interval;
        if ($interval < 60 || $interval > 604800 || !is_bool($wizard)) {
            respond(['error' => 'invalid_settings'], 422);
        }
        $statement = database()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ([
            'update_channel' => $channel,
            'github_repository' => $repository,
            'update_check_interval' => (string) $interval,
            'wizard_completed' => $wizard ? '1' : '0',
        ] as $key => $value) {
            $statement->execute([$key, $value]);
        }
    } elseif ($method !== 'GET') {
        respond(['error' => 'method_not_allowed'], 405);
    }
    respond(['settings' => setting_values(), 'csrf_token' => $_SESSION['admin_csrf']]);
}

if ($action === 'logs') {
    $limit = filter_var($_GET['limit'] ?? 50, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 250]]);
    if ($limit === false) {
        respond(['error' => 'invalid_limit'], 422);
    }
    $statement = database()->prepare('SELECT id, level, event, client_id, context, created_at FROM logs ORDER BY created_at DESC LIMIT ' . (int) $limit);
    $statement->execute();
    respond(['logs' => $statement->fetchAll()]);
}

if ($action === 'updates') {
    $settings = setting_values();
    respond(['update' => github_metadata($settings['github_repository'], $settings['update_channel']), 'checked_at' => gmdate('c')]);
}
