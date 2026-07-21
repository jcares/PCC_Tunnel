<?php
declare(strict_types=1);

namespace PccTunnel\Logs;

final class EventLogger
{
    public function record(string $level, string $event, ?string $clientId = null, array $context = []): void
    {
        \write_log($level, $event, $clientId, $context);
    }
}
