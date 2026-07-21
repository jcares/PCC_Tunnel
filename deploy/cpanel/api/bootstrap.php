<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$pccConfig = [];
$configPath = __DIR__ . '/../config.php';
if (is_file($configPath)) {
    $loadedConfig = require $configPath;
    if (is_array($loadedConfig)) {
        $pccConfig = $loadedConfig;
    }
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'PccTunnel\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__ . '/../Classes/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

function env_value(string $name, ?string $default = null): ?string
{
    global $pccConfig;
    if (array_key_exists($name, $pccConfig) && $pccConfig[$name] !== '') {
        return (string) $pccConfig[$name];
    }
    $value = getenv($name);
    return $value === false || $value === '' ? $default : $value;
}

function database(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        env_value('PCC_DB_HOST', '127.0.0.1'),
        env_value('PCC_DB_NAME', 'pcc_tunnel')
    );
    $pdo = new PDO($dsn, env_value('PCC_DB_USER', 'root'), env_value('PCC_DB_PASS', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function json_body(): array
{
    $raw = request_body();
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        respond(['error' => 'invalid_json'], 400);
    }
    return $data;
}

function request_body(): string
{
    static $body;
    if ($body === null) {
        $body = file_get_contents('php://input') ?: '';
    }
    return $body;
}

function request_headers(): array
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    if ($headers === []) {
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($name, 5))] = (string) $value;
            }
        }
    }
    return $headers;
}

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function respond_empty(int $status = 204): never
{
    http_response_code($status);
    exit;
}

function write_log(string $level, string $event, ?string $clientId = null, array $context = []): void
{
    $statement = database()->prepare('INSERT INTO logs (level, event, client_id, context) VALUES (?, ?, ?, ?)');
    $statement->execute([$level, $event, $clientId, json_encode($context, JSON_UNESCAPED_SLASHES)]);
}
