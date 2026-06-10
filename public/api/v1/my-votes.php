<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/private/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJson([
        'error' => [
            'code' => 'METHOD_NOT_ALLOWED',
            'message' => 'GET is required.',
        ],
    ], 405);
}

try {
    $telegramUser = validateTelegramInitData(
        getTelegramInitDataFromRequest(),
        $config['telegram']['bot_token'],
        $config['telegram']['max_auth_age_seconds']
    );
} catch (Throwable) {
    sendJson([
        'error' => [
            'code' => 'TELEGRAM_AUTH_FAILED',
            'message' => 'Telegram authentication failed.',
        ],
    ], 401);
}

$userId = upsertTelegramUser(
    $pdo,
    $telegramUser
);

$statement = $pdo->prepare(
    '
    SELECT
        match_id,
        predicted_home_score,
        predicted_away_score,
        prediction AS outcome,
        created_at,
        updated_at
    FROM votes
    WHERE user_id = :user_id
    ORDER BY match_id
    '
);

$statement->execute([
    'user_id' => $userId,
]);

sendJson([
    'user' => [
        'telegram_user_id' =>
            $telegramUser['telegram_user_id'],
        'username' =>
            $telegramUser['username'],
        'first_name' =>
            $telegramUser['first_name'],
    ],
    'votes' => $statement->fetchAll(),
]);