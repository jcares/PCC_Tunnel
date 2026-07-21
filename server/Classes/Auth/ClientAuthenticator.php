<?php
declare(strict_types=1);

namespace PccTunnel\Auth;

use PccTunnel\Models\Client;

final class ClientAuthenticator
{
    public function authenticate(array $server, string $body): ?array
    {
        $clientId = (string) ($server['HTTP_X_PCC_CLIENT_ID'] ?? '');
        $token = (string) ($server['HTTP_X_PCC_TOKEN'] ?? '');
        $timestamp = (string) ($server['HTTP_X_PCC_TIMESTAMP'] ?? '');
        $signature = (string) ($server['HTTP_X_PCC_SIGNATURE'] ?? '');
        if ($clientId === '' || $token === '' || $timestamp === '' || $signature === '' || !ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 120) {
            return null;
        }

        $client = (new Client(\database()))->findEnabled($clientId);
        if (!$client || !password_verify($token, $client['token_hash'])) {
            return null;
        }

        $expected = hash_hmac('sha256', $timestamp . "\n" . $body, $token);
        return hash_equals($expected, $signature) ? $client : null;
    }
}
