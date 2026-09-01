<?php $filepath = '../../../../'; require_once '../../../module.php';

$response['version']['module'] = 1;

UseClass('player');

$player = new Player(1129);
$data = $player->fetch(['name', 'level']);

if ($data === false) {
    $response['error'] = 'Player not found';
} else {
    $response['player'] = $data['name'];
    $response['test']   = $data['level'];
}

SendResponse($response);

?>