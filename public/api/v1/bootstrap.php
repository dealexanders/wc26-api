<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/private/bootstrap.php';

$configRows = $pdo
    ->query(
        '
        SELECT config_key, config_value
        FROM app_config
        '
    )
    ->fetchAll();

$appConfig = [];

foreach ($configRows as $row) {
    $appConfig[$row['config_key']] = json_decode(
        $row['config_value'],
        true,
        512,
        JSON_THROW_ON_ERROR
    );
}

$teams = $pdo
    ->query(
        '
        SELECT
            id,
            code,
            name,
            flag_code
        FROM teams
        WHERE is_active = 1
        ORDER BY name
        '
    )
    ->fetchAll();

$groups = $pdo
    ->query(
        '
        SELECT
            g.group_code,
            g.display_name,
            g.display_order,
            g.accent_color,
            t.id AS team_id,
            t.code AS team_code,
            t.name AS team_name,
            t.flag_code,
            gt.position_order
        FROM tournament_groups g
        JOIN group_teams gt
            ON gt.group_id = g.id
        JOIN teams t
            ON t.id = gt.team_id
        ORDER BY
            g.display_order,
            gt.position_order
        '
    )
    ->fetchAll();

sendJson([
    'site' => $appConfig['site'] ?? [],
    'navigation' => $appConfig['navigation'] ?? [],
    'content' => $appConfig['content'] ?? [],
    'theme' => $appConfig['theme'] ?? [],
    'teams' => $teams,
    'groups' => $groups,
]);
