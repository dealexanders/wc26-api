<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/private/bootstrap.php';

$sql = '
    SELECT
        m.id,
        m.match_number,
        m.stage,
        g.group_code,
        m.kickoff_at_utc,
        m.source_timezone,
        m.status,
        m.home_score,
        m.away_score,
        m.voting_enabled,
        m.voting_opens_at_utc,
        m.voting_closes_at_utc,

        v.name AS venue_name,
        v.city AS venue_city,
        v.timezone AS venue_timezone,

        ht.id AS home_team_id,
        ht.code AS home_team_code,
        ht.name AS home_team_name,
        ht.flag_code AS home_flag_code,
        m.home_placeholder,

        at.id AS away_team_id,
        at.code AS away_team_code,
        at.name AS away_team_name,
        at.flag_code AS away_flag_code,
        m.away_placeholder,

        SUM(vt.prediction = "HOME_WIN") AS home_win_votes,
        SUM(vt.prediction = "DRAW") AS draw_votes,
        SUM(vt.prediction = "AWAY_WIN") AS away_win_votes,
        COUNT(vt.id) AS total_votes,
        COUNT(
            CASE
                WHEN vt.predicted_home_score IS NOT NULL
                 AND vt.predicted_away_score IS NOT NULL
                THEN 1
            END
        ) AS score_forecast_count

    FROM matches m

    LEFT JOIN tournament_groups g
        ON g.id = m.group_id

    LEFT JOIN venues v
        ON v.id = m.venue_id

    LEFT JOIN teams ht
        ON ht.id = m.home_team_id

    LEFT JOIN teams at
        ON at.id = m.away_team_id

    LEFT JOIN votes vt
        ON vt.match_id = m.id

    GROUP BY m.id
    ORDER BY m.kickoff_at_utc, m.match_number
';

$matches = $pdo->query($sql)->fetchAll();

sendJson([
    'matches' => $matches,
]);