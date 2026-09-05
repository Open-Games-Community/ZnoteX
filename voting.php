<?php
require_once 'engine/init.php';
theme_open();

$otservers_eu_voting = $config['otservers_eu_voting'];

if ($otservers_eu_voting['enabled']) {
	if (user_logged_in()) {
		$isRewardRequest = isset($_GET['action']) && $_GET['action'] === 'reward';
		if (!$isRewardRequest) {
			$result = vote($user_data['id'], $otservers_eu_voting);
			if ($result === false) {
				echo '<p>'. t('voting.request_failed'). '</p>';
			} else {
				header('Location: ' . $result['voteLink']);
				die;
			}
		} else {
			$result = checkHasVoted($user_data['id'], $otservers_eu_voting);
			if ($result !== false) {
				if ($result['voted'] === true) {
					$points = $otservers_eu_voting['points'];
					$pointsText = $points === '1' ? 'point' : 'points';
					mysql_update("UPDATE `znote_accounts` SET `points` = `points` + '$points' WHERE `account_id`=" . $user_data['id']);
					echo "<p>Thank you for voting! You have been rewarded with $points $pointsText!</p>";
				} else {
					echo '<p>'. t('voting.not_voted'). '</p>';
				}
			} else {
				echo '<p>'. t('voting.cannot_verify'). '</p>';
			}
		}
	} else {
		header('Location: ' . $otservers_eu_voting['simpleVoteUrl']);
		die;
	}
} else {
	echo '<p>'. t('voting.disabled'). '</p>';
}

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
