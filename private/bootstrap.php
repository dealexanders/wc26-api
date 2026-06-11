<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/responses.php';
require_once __DIR__ . '/forecasts.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/scoring.php';

$config = require __DIR__ . '/config.php';

applyCors($config['cors']['allowed_origins']);

$pdo = createDatabaseConnection($config);
