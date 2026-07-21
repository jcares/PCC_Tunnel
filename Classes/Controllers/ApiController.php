<?php
declare(strict_types=1);

namespace PccTunnel\Controllers;

final class ApiController
{
    public static function cors(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
    }
}
