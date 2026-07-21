<?php
declare(strict_types=1);

namespace PccTunnel\Models;

use PDO;

final class Client
{
    public function __construct(private PDO $database)
    {
    }

    public function findEnabled(string $clientId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM clients WHERE client_id = ? AND enabled = 1 LIMIT 1');
        $statement->execute([$clientId]);
        return $statement->fetch() ?: null;
    }

    public function touch(string $clientId): void
    {
        $statement = $this->database->prepare("UPDATE clients SET status = 'online', last_seen_at = UTC_TIMESTAMP() WHERE client_id = ?");
        $statement->execute([$clientId]);
    }
}
