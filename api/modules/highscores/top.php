<?php
require_once '../../module.php';

// Module version
$response['version']['module'] = 1;

function highscoresTopSkillName(int $type): string {
    $types = [
        1 => 'Club', 2 => 'Sword', 3 => 'Axe', 4 => 'Distance', 5 => 'Shield',
        6 => 'Fish', 7 => 'Experience', 8 => 'Magic Level', 9 => 'Fist',
    ];
    return $types[$type] ?? 'Experience';
}

$type = isset($_GET['type']) ? (int)getValue($_GET['type'] ?? null) : 7;
if ($type > 9 || $type < 1) $type = 7;

$configVocations = $config['vocations'];
$vocationIds = array_keys($configVocations);

$vocation = 'all';
if (isset($_GET['vocation']) && is_numeric($_GET['vocation'])) {
    $vocation = (int)$_GET['vocation'];
    if (!in_array($vocation, $vocationIds, true)) {
        $vocation = 'all';
    }
}

$highscore = $config['highscore'];

$rows = isset($_GET['rows']) && is_numeric($_GET['rows'])
    ? max(1, (int)$_GET['rows'])
    : (int)$highscore['rows'];
$rows = min($rows, 100);

$loadFlags = ($config['country_flags']['enabled'] && $config['country_flags']['highscores']) ? true : false;
$loadOutfits = ($config['show_outfits']['highscores']) ? true : false;

$defaultRows = (int) $highscore['rows'];
$cache = new Cache('engine/cache/highscores');
if ($rows === $defaultRows && !$cache->hasExpired()) {
    $vocGroups = $cache->load();
} else {
    $vocGroups = fetchAllScores($rows, znote_server_adapter()->normalizedEngine(), $highscore['ignoreGroupId'], $configVocations, $vocation, $loadFlags, $loadOutfits);
    if ($rows === $defaultRows) {
        $cache->setContent($vocGroups);
        $cache->save();
    }
}

$vocGroup = [];
if ($vocGroups) {
    $vocGroup = is_array($vocGroups[$vocation]) ? $vocGroups[$vocation] : $vocGroups[$vocGroups[$vocation]];
}

$response['config'] = [
    'type' => $type,
    'skill_name' => highscoresTopSkillName($type),
    'vocation' => $vocation,
    'rows' => $rows,
];
$response['data']['rows'] = $vocGroup[$type] ?? [];

SendResponse($response);
?>
