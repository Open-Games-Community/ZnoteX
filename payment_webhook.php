<?php
if (PHP_VERSION_ID < 80100) {
	http_response_code(500);
	exit('PHP 8.1 or higher is required.');
}

$version = '2.0.0';
$time = time();
$aacQueries = 0;
$accQueriesData = [];

require 'config.php';
require 'engine/database/connect.php';
require 'engine/function/general.php';
require 'engine/function/settings.php';
znote_apply_settings();
require 'engine/function/payments.php';

payment_gateway_ensure_schema();

$provider = strtolower(trim((string)($_GET['provider'] ?? $_POST['provider'] ?? '')));
$payload = file_get_contents('php://input') ?: '';

try {
	if ($provider === 'stripe') {
		$result = payment_gateway_handle_stripe_webhook($payload);
	} elseif ($provider === 'mercadopago') {
		$result = payment_gateway_handle_mercadopago_webhook($payload);
	} else {
		$result = ['code' => 404, 'status' => 'unknown_provider'];
	}
} catch (Throwable $e) {
	error_log('Payment webhook error: ' . $e->getMessage());
	$result = ['code' => 500, 'status' => 'server_error'];
}

http_response_code((int)$result['code']);
header('Content-Type: application/json');
echo json_encode(['received' => $result['code'] < 500, 'status' => $result['status']]);
?>
