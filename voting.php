<?php
require_once 'engine/init.php';
theme_open();

$otservers_eu_voting = $config['otservers_eu_voting'];
$votingMessage = '';

if ($otservers_eu_voting['enabled']) {
	if (user_logged_in()) {
		$isRewardRequest = isset($_GET['action']) && $_GET['action'] === 'reward';
		if (!$isRewardRequest) {
			$result = vote($user_data['id'], $otservers_eu_voting);
			if ($result === false) {
				$votingMessage = t('voting.request_failed');
			} else {
				header('Location: ' . $result['voteLink']);
				die;
			}
		} else {
			$result = checkHasVoted($user_data['id'], $otservers_eu_voting);
			if ($result !== false) {
				if ($result['voted'] === true) {
					$points = $otservers_eu_voting['points'];
					$pointsText = $points === '1' ? t('voting.point_singular') : t('voting.point_plural');
					db()->execute("UPDATE `znote_accounts` SET `points` = `points` + ? WHERE `account_id` = ?", [(int)$points, (int)$user_data['id']]);
					$votingMessage = t('voting.rewarded', ['points' => $points, 'unit' => $pointsText]);
				} else {
					$votingMessage = t('voting.not_voted');
				}
			} else {
				$votingMessage = t('voting.cannot_verify');
			}
		}
	} else {
		header('Location: ' . $otservers_eu_voting['simpleVoteUrl']);
		die;
	}
} else {
	$votingMessage = t('voting.disabled');
}

view('voting');
theme_close();

function vote($otUserId, $otservers_eu_voting) {
	$context  = stream_context_create([
		'http' => [
			'header'  => "Content-type: application/json",
			'method'  => 'POST',
			'content' => json_encode([
				'otUserId' => $otUserId,
				'secretToken' => $otservers_eu_voting['secretToken'],
				'landingPage' => $otservers_eu_voting['landingPage']
			])
		]
	]);
	$result = file_get_contents($otservers_eu_voting['voteUrl'], false, $context);
	return $result !== false ? json_decode($result, true) : false;
}

function checkHasVoted($otUserId, $otservers_eu_voting) {
	$context  = stream_context_create([
		'http' => [
			'header'  => "Content-type: application/json",
			'method'  => 'POST',
			'content' => json_encode([
				'otUserId' => $otUserId,
				'secretToken' => $otservers_eu_voting['secretToken'],
				'consume' => true
			])
		]
	]);
	$result = file_get_contents($otservers_eu_voting['voteCheckUrl'], false, $context);
	return $result !== false ? json_decode($result, true) : false;
}
