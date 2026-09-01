<?php
require_once '../../module.php';

// Module version
$response['version']['module'] = 1;

// Secure config access
$towns = $config['towns'] ?? [];
$availableTowns = $config['available_towns'] ?? [];

// Store all towns
$response['data']['towns'] = $towns;

// Store available towns only if they exist
$response['data']['available'] = [];

foreach ($availableTowns as $id) {
    if (isset($towns[$id])) {
        $response['data']['available'][$id] = $towns[$id];
    }
}

SendResponse($response);
?>