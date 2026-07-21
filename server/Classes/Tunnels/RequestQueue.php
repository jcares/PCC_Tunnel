<?php
declare(strict_types=1);

namespace PccTunnel\Tunnels;

use PDO;

final class RequestQueue
{
    public function __construct(private PDO $database)
    {
    }

    public function claim(string $clientId): ?array
    {
        $this->database->beginTransaction();
        try {
            $statement = $this->database->prepare("SELECT * FROM requests WHERE client_id = ? AND status = 'pending' ORDER BY created_at LIMIT 1 FOR UPDATE");
            $statement->execute([$clientId]);
            $request = $statement->fetch();
            if (!$request) {
                $this->database->commit();
                return null;
            }
            $update = $this->database->prepare("UPDATE requests SET status = 'processing', claimed_at = UTC_TIMESTAMP() WHERE id = ?");
            $update->execute([$request['id']]);
            $this->database->commit();
            return $request;
        } catch (\Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }
}
