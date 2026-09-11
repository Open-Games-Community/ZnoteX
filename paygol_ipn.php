<?php
require 'config.php';
require 'engine/database/connect.php';

// Fetch and sanitize POST and GET values
function getValue($value) {
	return (!empty($value)) ? sanitize($value) : false;
}
function sanitize($data) {
	return htmlentities(strip_tags(mysql_znote_escape_string($data)));
}

require_once 'engine/function/translate.php';
require_once 'engine/function/settings.php';
require_once 'engine/function/users.php';
require_once 'engine/function/plugins.php';
znote_apply_settings();
znote_plugins_load();

// get the variables from PayGol system
$message_id	= getValue($_GET['message_id'] ?? null);
$service_id	= getValue($_GET['service_id'] ?? null);
$shortcode	= getValue($_GET['shortcode'] ?? null);
$keyword	= getValue($_GET['keyword'] ?? null);
$message	= getValue($_GET['message'] ?? null);
$sender		= getValue($_GET['sender'] ?? null);
$operator	= getValue($_GET['operator'] ?? null);
$country	= getValue($_GET['country'] ?? null);
$custom_raw	= (string)($_GET['custom'] ?? '');
$custom		= getValue($custom_raw);
$points		= getValue($_GET['points'] ?? null);
$price		= getValue($_GET['price'] ?? null);
$currency	= getValue($_GET['currency'] ?? null);
$secret		= getValue($_GET['secret'] ?? null);

// config paygol settings
$paygol = $config['paygol'];

// Check for valid secret key
if($secret != $paygol['secretKey']) {
	header("HTTP/1.0 403 Forbidden");
	die("Error: secretKey does not match.");
}

// Check if request serviceID is the same as it is in config
if($service_id != $paygol['serviceID']) {
	header("HTTP/1.0 403 Forbidden");
	die("Error: serviceID does not match.");
}

$new_points = $paygol['points'];
$paymentData = array(
	'provider' => 'paygol',
	'reference' => (string)$message_id,
	'custom' => $custom_raw,
	'account_id' => (int)$custom,
	'price' => $price,
	'currency' => $currency,
	'points' => (int)$new_points,
	'status' => 'completed',
	'raw' => $_GET,
	'resolved' => false,
);
if (function_exists('znote_hook_filter')) {
	$paymentData = znote_hook_filter('payment.resolve', $paymentData, array('provider' => 'paygol', 'raw' => $_GET));
}
if (!empty($paymentData['resolved'])) {
	$custom = (int)($paymentData['account_id'] ?? 0);
	$new_points = (int)($paymentData['points'] ?? 0);
	if (number_format((float)$price, 2, '.', '') !== number_format((float)($paymentData['price'] ?? 0), 2, '.', '')) {
		header("HTTP/1.0 403 Forbidden");
		die("Error: payment price mismatch.");
	}
}

// Check that this message_id has not already been credited
if ($message_id !== false) {
	$duplicate = db()->fetchOne("SELECT `id` FROM `znote_paygol` WHERE `message_id` = ? LIMIT 1;", [$message_id]);
	if ($duplicate !== false) {
		header("HTTP/1.0 200 OK");
		die("Error: message_id already processed.");
	}
}

// Re-check for a duplicate and credit inside one locked transaction, so two
// concurrent notifications for the same message_id cannot both credit points.
$creditResult = db()->transaction(function ($db) use ($custom, $price, $new_points, $message_id, $service_id, $shortcode, $keyword, $message, $sender, $operator, $country, $currency) {
	if ($message_id !== false) {
		$dup = $db->fetchOne("SELECT `id` FROM `znote_paygol` WHERE `message_id` = ? LIMIT 1 FOR UPDATE;", [$message_id]);
		if ($dup !== false) {
			return 'duplicate';
		}
	}

	$db->execute(
		"INSERT INTO `znote_paygol` VALUES ('', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
		[$custom, $price, $new_points, $message_id, $service_id, $shortcode, $keyword, $message, $sender, $operator, $country, $currency]
	);

	$account = $db->fetchOne("SELECT `points` FROM `znote_accounts` WHERE `account_id` = ? LIMIT 1 FOR UPDATE;", [$custom]);
	if (!is_array($account)) {
		return 'no_account';
	}

	$creditedPoints = (int)$account['points'] + $new_points;
	$db->execute("UPDATE `znote_accounts` SET `points` = ? WHERE `account_id` = ?", [$creditedPoints, $custom]);

	return 'credited';
});

if ($creditResult === 'credited' && function_exists('znote_hook')) {
	znote_hook('payment.completed', array_merge($paymentData, array(
		'provider' => 'paygol',
		'reference' => (string)$message_id,
		'custom' => $custom_raw,
		'account_id' => (int)$custom,
		'price' => $paymentData['price'] ?? $price,
		'currency' => $paymentData['currency'] ?? $currency,
		'points' => $paymentData['points'] ?? $paygol['points'],
		'status' => 'completed',
	)));
}
