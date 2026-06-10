<?php

declare(strict_types=1);

function validateTelegramInitData(
    string $initData,
    string $botToken,
    int $maxAgeSeconds = 3600
): array {
    if ($initData === '') {
        throw new RuntimeException(
            'Telegram initData is missing.'
        );
    }

    parse_str($initData, $data);

    if (
        !isset($data['hash'])
        || !is_string($data['hash'])
    ) {
        throw new RuntimeException(
            'Telegram hash is missing.'
        );
    }

    $receivedHash = $data['hash'];
    unset($data['hash']);

    ksort($data);

    $dataCheckParts = [];

    foreach ($data as $key => $value) {
        if (!is_string($value)) {
            continue;
        }

        $dataCheckParts[] = $key . '=' . $value;
    }

    $dataCheckString = implode(
        "\n",
        $dataCheckParts
    );

    $secretKey = hash_hmac(
        'sha256',
        $botToken,
        'WebAppData',
        true
    );

    $calculatedHash = hash_hmac(
        'sha256',
        $dataCheckString,
        $secretKey
    );

    if (!hash_equals(
        $calculatedHash,
        $receivedHash
    )) {
        throw new RuntimeException(
            'Telegram initData signature is invalid.'
        );
    }

    $authDate = isset($data['auth_date'])
        ? (int) $data['auth_date']
        : 0;

    if ($authDate <= 0) {
        throw new RuntimeException(
            'Telegram auth_date is missing.'
        );
    }

    $age = time() - $authDate;

    if (
        $age < -60
        || $age > $maxAgeSeconds
    ) {
        throw new RuntimeException(
            'Telegram authentication data has expired.'
        );
    }

    if (
        !isset($data['user'])
        || !is_string($data['user'])
    ) {
        throw new RuntimeException(
            'Telegram user data is missing.'
        );
    }

    $user = json_decode(
        $data['user'],
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (!isset($user['id'])) {
        throw new RuntimeException(
            'Telegram user ID is missing.'
        );
    }

    return [
        'telegram_user_id' =>
            (string) $user['id'],

        'username' =>
            $user['username'] ?? null,

        'first_name' =>
            $user['first_name'] ?? null,

        'last_name' =>
            $user['last_name'] ?? null,

        'language_code' =>
            $user['language_code'] ?? null,

        'is_premium' =>
            isset($user['is_premium'])
                ? (bool) $user['is_premium']
                : null,

        'auth_date' =>
            $authDate,
    ];
}


function getTelegramInitDataFromRequest(): string
{
    return trim(
        $_SERVER['HTTP_X_TELEGRAM_INIT_DATA']
        ?? ''
    );
}


function upsertTelegramUser(
    PDO $pdo,
    array $telegramUser
): int {
    $statement = $pdo->prepare(
        '
        INSERT INTO telegram_users (
            telegram_user_id,
            username,
            first_name,
            last_name,
            language_code,
            is_premium
        )
        VALUES (
            :telegram_user_id,
            :username,
            :first_name,
            :last_name,
            :language_code,
            :is_premium
        )
        ON DUPLICATE KEY UPDATE
            username = VALUES(username),
            first_name = VALUES(first_name),
            last_name = VALUES(last_name),
            language_code = VALUES(language_code),
            is_premium = VALUES(is_premium),
            last_seen_at = CURRENT_TIMESTAMP
        '
    );

    $statement->execute([
        'telegram_user_id' =>
            $telegramUser['telegram_user_id'],

        'username' =>
            $telegramUser['username'],

        'first_name' =>
            $telegramUser['first_name'],

        'last_name' =>
            $telegramUser['last_name'],

        'language_code' =>
            $telegramUser['language_code'],

        'is_premium' =>
            $telegramUser['is_premium'],
    ]);

    $select = $pdo->prepare(
        '
        SELECT id
        FROM telegram_users
        WHERE telegram_user_id =
            :telegram_user_id
        '
    );

    $select->execute([
        'telegram_user_id' =>
            $telegramUser['telegram_user_id'],
    ]);

    $userId = $select->fetchColumn();

    if ($userId === false) {
        throw new RuntimeException(
            'Cannot load Telegram user.'
        );
    }

    return (int) $userId;
}
