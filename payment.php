<?php
require_once 'engine/init.php';
protect_page();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: buypoints.php');
	exit;
}

if (!Token::isValid($_POST['token'] ?? null)) {
	http_response_code(400);
	theme_open();
	echo '<h1>Invalid request</h1><p>Reload the buy points page and try again.</p>';
	theme_close();
	exit;
}

$provider = strtolower(trim((string)($_POST['provider'] ?? '')));
$price = $_POST['price'] ?? '';

try {
	$checkout = payment_gateway_create_checkout($provider, (int)$session_user_id, $price);
	header('Location: ' . $checkout['url']);
	exit;
} catch (Throwable $e) {
	error_log('Payment checkout error: ' . $e->getMessage());
	http_response_code(400);
	theme_open();
	echo '<h1>Payment unavailable</h1><p>The payment could not be started. Please contact staff if this continues.</p>';
	theme_close();
}
?>
