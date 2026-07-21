<?php
declare(strict_types=1);

namespace PccTunnel\Users;

use PDO;

final class User
{
    public function __construct(private PDO $database)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);
        return $statement->fetch() ?: null;
    }
}
