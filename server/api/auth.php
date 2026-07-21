<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function authenticated_client(): array
{
    $client = (new \PccTunnel\Auth\ClientAuthenticator())->authenticate($_SERVER, request_body());
    if ($client === null) {
        respond(['error' => 'authentication_required'], 401);
    }
    return $client;
}

function touch_client(string $clientId): void
{
    (new \PccTunnel\Models\Client(database()))->touch($clientId);
}
