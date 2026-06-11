<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$matchNumber = (int) ($argv[1] ?? 0);

if ($matchNumber <= 0) {
    echo "Usage: php score-match.php MATCH_NUMBER\n";
    exit(1);
}

$matchStatement = $pdo->prepare(
    '
    SELECT
        id,
        match_number,
        home_score,
        away_score,
        status
    FROM matches
    WHERE match_number = :match_number
    '
);

$matchStatement->execute([
    'match_number' => $matchNumber,
]);

$match = $matchStatement->fetch();

if (!$match) {
    echo "Match not found\n";
    exit(1);
}

if ($match['status'] !== 'FINISHED') {
    echo "Match is not FINISHED yet\n";
    exit(1);
}

if ($match['home_score'] === null || $match['away_score'] === null) {
    echo "Match score is missing\n";
    exit(1);
}

$votesStatement = $pdo->prepare(
    '
    SELECT
        id,
        user_id,
        match_id,
        predicted_home_score,
        predicted_away_score
    FROM votes
    WHERE match_id = :match_id
    '
);

$votesStatement->execute([
    'match_id' => $match['id'],
]);

$votes = $votesStatement->fetchAll();

$insertStatement = $pdo->prepare(
    '
    INSERT INTO forecast_scores (
        vote_id,
        user_id,
        match_id,
        outcome_points,
        exact_team_score_points,
        goal_difference_points,
        exact_full_score_bonus,
        points_total,
        scoring_version
    )
    VALUES (
        :vote_id,
        :user_id,
        :match_id,
        :outcome_points,
        :exact_team_score_points,
        :goal_difference_points,
        :exact_full_score_bonus,
        :points_total,
        :scoring_version
    )
    ON DUPLICATE KEY UPDATE
        outcome_points = VALUES(outcome_points),
        exact_team_score_points = VALUES(exact_team_score_points),
        goal_difference_points = VALUES(goal_difference_points),
        exact_full_score_bonus = VALUES(exact_full_score_bonus),
        points_total = VALUES(points_total),
        scoring_version = VALUES(scoring_version),
        calculated_at = CURRENT_TIMESTAMP
    '
);

$pdo->beginTransaction();

try {
    foreach ($votes as $vote) {
        $points = calculateForecastPoints(
            (int) $vote['predicted_home_score'],
            (int) $vote['predicted_away_score'],
            (int) $match['home_score'],
            (int) $match['away_score']
        );

        $insertStatement->execute([
            'vote_id' => $vote['id'],
            'user_id' => $vote['user_id'],
            'match_id' => $vote['match_id'],
            'outcome_points' => $points['outcome_points'],
            'exact_team_score_points' => $points['exact_team_score_points'],
            'goal_difference_points' => $points['goal_difference_points'],
            'exact_full_score_bonus' => $points['exact_full_score_bonus'],
            'points_total' => $points['points_total'],
            'scoring_version' => 'v1',
        ]);
    }

    $pdo->commit();

    echo "Scored match {$matchNumber}. Votes processed: "
        . count($votes)
        . "\n";
} catch (Throwable $exception) {
    $pdo->rollBack();

    echo "Scoring failed: "
        . $exception->getMessage()
        . "\n";

    exit(1);
}
