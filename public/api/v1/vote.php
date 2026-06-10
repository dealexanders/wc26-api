<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/private/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson([
        'error' => [
            'code' => 'METHOD_NOT_ALLOWED',
            'message' => 'POST is required.',
        ],
    ], 405);
}

$body = readJsonBody();

$matchId = filter_var(
    $body['match_id'] ?? null,
    FILTER_VALIDATE_INT
);

$homeScore = filter_var(
    $body['home_score'] ?? null,
    FILTER_VALIDATE_INT
);

$awayScore = filter_var(
    $body['away_score'] ?? null,
    FILTER_VALIDATE_INT
);

if ($matchId === false || $matchId <= 0) {
    sendJson([
        'error' => [
            'code' => 'INVALID_MATCH_ID',
            'message' => 'A valid match_id is required.',
        ],
    ], 422);
}

if (
    !isValidForecastScore($homeScore)
    || !isValidForecastScore($awayScore)
) {
    sendJson([
        'error' => [
            'code' => 'INVALID_FORECAST_SCORE',
            'message' =>
                'Both forecast scores must be integers from 0 to 20.',
        ],
    ], 422);
}

$prediction = calculatePredictionOutcome(
    $homeScore,
    $awayScore
);

try {
    $telegramUser = validateTelegramInitData(
        getTelegramInitDataFromRequest(),
        $config['telegram']['bot_token'],
        $config['telegram']['max_auth_age_seconds']
    );
} catch (Throwable $exception) {
    sendJson([
        'error' => [
            'code' => 'TELEGRAM_AUTH_FAILED',
            'message' => 'Telegram authentication failed.',
        ],
    ], 401);
}

$pdo->beginTransaction();

try {
    $userId = upsertTelegramUser(
        $pdo,
        $telegramUser
    );

    $matchStatement = $pdo->prepare(
        '
        SELECT
            id,
            status,
            voting_enabled,
            voting_opens_at_utc,
            voting_closes_at_utc,
            home_team_id,
            away_team_id
        FROM matches
        WHERE id = :match_id
        FOR UPDATE
        '
    );

    $matchStatement->execute([
        'match_id' => $matchId,
    ]);

    $match = $matchStatement->fetch();

    if ($match === false) {
        $pdo->rollBack();

        sendJson([
            'error' => [
                'code' => 'MATCH_NOT_FOUND',
                'message' => 'Match was not found.',
            ],
        ], 404);
    }

    $nowUtc = new DateTimeImmutable(
        'now',
        new DateTimeZone('UTC')
    );

    $opensAt = $match['voting_opens_at_utc']
        ? new DateTimeImmutable(
            $match['voting_opens_at_utc'],
            new DateTimeZone('UTC')
        )
        : null;

    $closesAt = new DateTimeImmutable(
        $match['voting_closes_at_utc'],
        new DateTimeZone('UTC')
    );

    if ((int) $match['voting_enabled'] !== 1) {
        $pdo->rollBack();

        sendJson([
            'error' => [
                'code' => 'VOTING_DISABLED',
                'message' =>
                    'Voting is disabled for this match.',
            ],
        ], 409);
    }

    if ($match['status'] !== 'SCHEDULED') {
        $pdo->rollBack();

        sendJson([
            'error' => [
                'code' => 'MATCH_ALREADY_STARTED',
                'message' =>
                    'Voting is available only for scheduled matches.',
            ],
        ], 409);
    }

    if ($opensAt !== null && $nowUtc < $opensAt) {
        $pdo->rollBack();

        sendJson([
            'error' => [
                'code' => 'VOTING_NOT_OPEN',
                'message' =>
                    'Voting has not opened yet.',
            ],
        ], 409);
    }

    if ($nowUtc >= $closesAt) {
        $pdo->rollBack();

        sendJson([
            'error' => [
                'code' => 'VOTING_CLOSED',
                'message' =>
                    'Voting for this match is closed.',
            ],
        ], 409);
    }

    if (
        $match['home_team_id'] === null
        || $match['away_team_id'] === null
    ) {
        $pdo->rollBack();

        sendJson([
            'error' => [
                'code' => 'TEAMS_NOT_CONFIRMED',
                'message' =>
                    'Voting opens after both teams are confirmed.',
            ],
        ], 409);
    }

    $voteStatement = $pdo->prepare(
        '
        INSERT INTO votes (
            user_id,
            match_id,
            predicted_home_score,
            predicted_away_score,
            prediction
        )
        VALUES (
            :user_id,
            :match_id,
            :predicted_home_score,
            :predicted_away_score,
            :prediction
        )
        ON DUPLICATE KEY UPDATE
            predicted_home_score =
                VALUES(predicted_home_score),

            predicted_away_score =
                VALUES(predicted_away_score),

            prediction =
                VALUES(prediction),

            updated_at =
                CURRENT_TIMESTAMP
        '
    );

    $voteStatement->execute([
        'user_id' => $userId,
        'match_id' => $matchId,
        'predicted_home_score' => $homeScore,
        'predicted_away_score' => $awayScore,
        'prediction' => $prediction,
    ]);

    $pdo->commit();

    sendJson([
        'success' => true,
        'forecast' => [
            'match_id' => $matchId,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'outcome' => $prediction,
        ],
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($exception->getMessage());

    sendJson([
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' =>
                'The vote could not be saved.',
        ],
    ], 500);
}