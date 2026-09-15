<?php
require_once '../../module.php';

// Module version
$response['version']['module'] = 1;

// Secure GET name
$name = getValue($_GET['name'] ?? null);

if ($name === false || $name === '') {
    $response['error'] = 'Missing name parameter.';
    SendResponse($response);
    exit;
}

$id = user_character_id($name);
if ($id === false) {
    $response['error'] = 'Character not found.';
    SendResponse($response);
    exit;
}

// Respect the same "hide my character" flag the rest of the site honours.
if (user_character_hide($name)) {
    $response['error'] = 'This character is hidden.';
    SendResponse($response);
    exit;
}

UseClass('player');

$player = new Player($id);
$data = $player->fetch([
    'name', 'level', 'vocation', 'sex',
    'looktype', 'lookhead', 'lookbody', 'looklegs', 'lookfeet', 'lookaddons',
    'health', 'healthmax', 'mana', 'manamax', 'soul', 'cap',
    'experience', 'maglevel', 'town_id',
    'lastlogin', 'lastlogout', 'online', 'created', 'comment',
]);

if ($data === false) {
    $response['error'] = 'Character not found.';
    SendResponse($response);
    exit;
}

$data['vocation_name'] = vocation_id_to_name((int)($data['vocation'] ?? -1));

$townId = (int)($data['town_id'] ?? 0);
$data['town_name'] = $config['towns'][$townId] ?? null;

$guild = get_player_guild_data($id);
if ($guild !== false) {
    $data['guild'] = [
        'name'      => get_guild_name((int)$guild['guild_id']) ?: null,
        'rank_name' => $guild['rank_name'] ?? null,
    ];
}

$response['data'] = $data;

SendResponse($response);
?>
