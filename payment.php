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
	$paymentMessage = array('title' => t('payment.invalid_request'), 'text' => t('payment.retry_hint'));
	view('payment_message');
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
	$paymentMessage = array('title' => t('payment.unavailable'), 'text' => t('payment.start_failed'));
	view('payment_message');
	theme_close();
}
?>
