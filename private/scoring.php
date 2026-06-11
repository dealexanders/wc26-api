<?php

declare(strict_types=1);

function calculateOutcome(
    int $firstScore,
    int $secondScore
): string {
    if ($firstScore > $secondScore) {
        return 'HOME_WIN';
    }

    if ($firstScore < $secondScore) {
        return 'AWAY_WIN';
    }

    return 'DRAW';
}

function calculateForecastPoints(
    int $predictedHomeScore,
    int $predictedAwayScore,
    int $actualHomeScore,
    int $actualAwayScore
): array {
    $predictedOutcome = calculateOutcome(
        $predictedHomeScore,
        $predictedAwayScore
    );

    $actualOutcome = calculateOutcome(
        $actualHomeScore,
        $actualAwayScore
    );

    $outcomePoints =
        $predictedOutcome === $actualOutcome
            ? 6
            : 0;

    $exactTeamScorePoints =
        (
            $predictedHomeScore === $actualHomeScore
            || $predictedAwayScore === $actualAwayScore
        )
            ? 1
            : 0;

    $goalDifferencePoints =
        (
            ($predictedHomeScore - $predictedAwayScore)
            ===
            ($actualHomeScore - $actualAwayScore)
        )
            ? 1
            : 0;

    $exactFullScoreBonus =
        (
            $predictedHomeScore === $actualHomeScore
            && $predictedAwayScore === $actualAwayScore
        )
            ? 1
            : 0;

    $pointsTotal =
        $outcomePoints
        + $exactTeamScorePoints
        + $goalDifferencePoints
        + $exactFullScoreBonus;

    return [
        'outcome_points' => $outcomePoints,
        'exact_team_score_points' => $exactTeamScorePoints,
        'goal_difference_points' => $goalDifferencePoints,
        'exact_full_score_bonus' => $exactFullScoreBonus,
        'points_total' => $pointsTotal,
    ];
}
