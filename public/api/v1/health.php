<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/private/bootstrap.php';

try {
    $pdo->query('SELECT 1');

    sendJson([
        'status' => 'ok',
        'database' => 'connected',
        'time_utc' => gmdate('c'),
    ]);
} catch (Throwable $exception) {
    sendJson([
        'status' => 'error',
        'database' => 'unavailable',
    ], 503);
}