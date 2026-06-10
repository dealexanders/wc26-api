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

$matchId = filter_input(
    INPUT_GET,
    'match_id',
    FILTER_VALIDATE_INT
);

if ($matchId === false || $matchId === null || $matchId <= 0) {
    sendJson([
        'error' => [
            'code' => 'INVALID_MATCH_ID',
            'message' => 'A valid match_id is required.',
        ],
    ], 422);
}

$statement = $pdo->prepare(
    '
    SELECT
        v.predicted_home_score,
        v.predicted_away_score,
        v.prediction AS outcome,
        v.updated_at,

        u.username,
        u.first_name,
        u.last_name

    FROM votes v

    JOIN telegram_users u
        ON u.id = v.user_id

    WHERE v.match_id = :match_id

    ORDER BY v.updated_at ASC
    '
);

$statement->execute([
    'match_id' => $matchId,
]);

$rows = $statement->fetchAll();

$forecasts = array_map(
    static function (array $row): array {
        $rawName =
            $row['username']
            ?: trim(
                ($row['first_name'] ?? '')
                . ' '
                . ($row['last_name'] ?? '')
            );

        return [
            'voter_name' =>
                maskName($rawName),

            'home_score' =>
                (int) $row['predicted_home_score'],

            'away_score' =>
                (int) $row['predicted_away_score'],

            'outcome' =>
                $row['outcome'],
        ];
    },
    $rows
);

sendJson([
    'match_id' => $matchId,
    'forecasts' => $forecasts,
]);
