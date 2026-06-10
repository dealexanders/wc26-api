<?php

declare(strict_types=1);

function calculatePredictionOutcome(
    int $homeScore,
    int $awayScore
): string {
    if ($homeScore > $awayScore) {
        return 'HOME_WIN';
    }

    if ($homeScore < $awayScore) {
        return 'AWAY_WIN';
    }

    return 'DRAW';
}

function isValidForecastScore(mixed $score): bool
{
    return (
        $score !== false
        && is_int($score)
        && $score >= 0
        && $score <= 20
    );
}

function maskName(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return 'Anonymous';
    }

    $length = mb_strlen($value);

    if ($length === 1) {
        return $value . '***';
    }

    if ($length === 2) {
        return mb_substr($value, 0, 1) . '*';
    }

    return (
        mb_substr($value, 0, 2)
        . str_repeat('*', min($length - 2, 5))
    );
}
