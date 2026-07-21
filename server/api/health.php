<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['admin_email'])) {
    respond(['error' => 'authentication_required'], 401);
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    respond(['error' => 'method_not_allowed'], 405);
}

$dbStatus = 'ok';
$clientsOnline = 0;
$recentHeartbeats = 0;
try {
    $db = database();
    $clientsOnline = (int) $db->query("SELECT COUNT(*) FROM clients WHERE status = 'online'")->fetchColumn();
    $recentHeartbeats = (int) $db->query("SELECT COUNT(*) FROM clients WHERE last_seen_at >= UTC_TIMESTAMP() - INTERVAL 5 MINUTE")->fetchColumn();
} catch (Throwable $exception) {
    $dbStatus = 'error';
}

$disk = null;
$diskPath = __DIR__;
$totalDisk = @disk_total_space($diskPath);
$freeDisk = @disk_free_space($diskPath);
if (is_float($totalDisk) || is_int($totalDisk)) {
    $disk = [
        'total_bytes' => (int) $totalDisk,
        'free_bytes' => is_int($freeDisk) || is_float($freeDisk) ? (int) $freeDisk : null,
    ];
}

$memory = null;
$meminfo = @file('/proc/meminfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (is_array($meminfo)) {
    $values = [];
    foreach ($meminfo as $line) {
        if (preg_match('/^(MemTotal|MemAvailable):\s+(\d+)\s+kB$/', $line, $match)) {
            $values[$match[1]] = (int) $match[2] * 1024;
        }
    }
    if (isset($values['MemTotal'])) {
        $memory = ['total_bytes' => $values['MemTotal'], 'available_bytes' => $values['MemAvailable'] ?? null];
    }
}

respond([
    'status' => $dbStatus === 'ok' ? 'ok' : 'degraded',
    'database' => $dbStatus,
    'clients_online' => $clientsOnline,
    'heartbeats_recent' => $recentHeartbeats,
    'disk' => $disk,
    'memory' => $memory,
    'checked_at' => gmdate('c'),
]);
