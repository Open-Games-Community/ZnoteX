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
	echo '<h1>' . t('payment.invalid_request') . '</h1><p>' . t('payment.retry_hint') . '</p>';
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
	echo '<h1>' . t('payment.unavailable') . '</h1><p>' . t('payment.start_failed') . '</p>';
	theme_close();
}
?>
