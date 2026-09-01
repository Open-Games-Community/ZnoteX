<?php
require_once '../../module.php';

// Module version
$response['version']['module'] = 1;

// Secure GET rows
$rows = isset($_GET['rows']) && is_numeric($_GET['rows'])
    ? max(1, (int)$_GET['rows'])
    : 10;

// Hard limit safety (anti abuse)
$rows = min($rows, 100);

$response['config']['rows'] = $rows;

// Fetch players
$query = "
    SELECT
        p.name,
        p.level,
        p.experience,
        p.vocation,
        p.lastlogin,
        z.created
    FROM players AS p
    INNER JOIN znote_players AS z
        ON p.id = z.player_id
    WHERE p.group_id < 2
    ORDER BY p.experience DESC
    LIMIT {$rows}
";

$players = mysql_select_multi($query);

// Always return array
if (!is_array($players)) {
    $players = [];
}

// Add vocation name safely
foreach ($players as &$player) {
    $vocId = (int)($player['vocation'] ?? -1);
    $player['vocation_name'] = $config['vocations'][$vocId] ?? 'Unknown';
}

$response['data']['players'] = $players;

SendResponse($response);

?>