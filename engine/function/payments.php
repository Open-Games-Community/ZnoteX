<?php
/**
 * Hosted payment gateways for shop points.
 *
 * Success/failed return pages never credit points. A payment becomes real only
 * after a signed webhook is verified, the provider API confirms the payment,
 * and the stored quote still matches amount, currency, account and points.
 */

function payment_gateway_ensure_schema(): void {
	mysql_update("
		CREATE TABLE IF NOT EXISTS `znote_payment_transactions` (
			`id` bigint NOT NULL AUTO_INCREMENT,
			`provider` varchar(32) NOT NULL,
			`reference` varchar(128) NOT NULL,
			`provider_reference` varchar(128) DEFAULT NULL,
			`account_id` int NOT NULL,
			`price` decimal(11,2) NOT NULL,
			`currency` varchar(8) NOT NULL,
			`points` int NOT NULL,
			`status` varchar(32) NOT NULL DEFAULT 'pending',
			`credited` tinyint NOT NULL DEFAULT '0',
			`test_mode` tinyint NOT NULL DEFAULT '0',
			`created_at` int NOT NULL,
			`updated_at` int NOT NULL,
			`credited_at` int DEFAULT NULL,
			`payload` longtext,
			PRIMARY KEY (`id`),
			UNIQUE KEY `provider_reference_internal` (`provider`, `reference`),
			KEY `provider_reference_external` (`provider`, `provider_reference`),
			KEY `account_status` (`account_id`, `status`, `created_at`)
		) ENGINE=InnoDB;
	");

	mysql_update("
		CREATE TABLE IF NOT EXISTS `znote_payment_events` (
			`id` bigint NOT NULL AUTO_INCREMENT,
			`provider` varchar(32) NOT NULL,
			`event_id` varchar(128) NOT NULL,
			`provider_reference` varchar(128) DEFAULT NULL,
			`payment_reference` varchar(128) DEFAULT NULL,
			`status` varchar(32) NOT NULL DEFAULT 'received',
			`payload` longtext,
			`received_at` int NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `provider_event` (`provider`, `event_id`),
			KEY `payment_reference` (`provider`, `payment_reference`)
		) ENGINE=InnoDB;
	");
}

function payment_gateway_enabled(string $provider): bool {
	global $config;
	return !empty($config[$provider]['enabled']);
}

function payment_gateway_config(string $provider, string $key, $default = '') {
	global $config;
	return $config[$provider][$key] ?? $default;
}

function payment_gateway_public_url(string $path, array $params = []): string {
	global $config;

	$base = trim((string)($config['site_url'] ?? ''), '/');
	if ($base === '') {
		$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
		$base = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
	}

	$url = $base . '/' . ltrim($path, '/');
	if ($params) {
		$url .= '?' . http_build_query($params);
		$url = str_replace('%7BCHECKOUT_SESSION_ID%7D', '{CHECKOUT_SESSION_ID}', $url);
	}

	return $url;
}

function payment_gateway_webhook_url(string $provider): string {
	$configured = trim((string)payment_gateway_config($provider, 'webhook_url', ''));
	return $configured !== ''
		? $configured
		: payment_gateway_public_url('payment_webhook.php', ['provider' => $provider]);
}

function payment_gateway_return_url(string $provider, string $type, array $params = []): string {
	$configured = trim((string)payment_gateway_config($provider, $type, ''));
	if ($configured !== '') {
		if ($params) {
			$configured .= (str_contains($configured, '?') ? '&' : '?') . http_build_query($params);
			$configured = str_replace('%7BCHECKOUT_SESSION_ID%7D', '{CHECKOUT_SESSION_ID}', $configured);
		}
		return $configured;
	}

	return payment_gateway_public_url($type === 'failed' ? 'failed.php' : 'success.php', $params);
}

function payment_gateway_price_tier($price): array|false {
	global $config;

	$requested = number_format((float)$price, 2, '.', '');
	foreach ((array)($config['paypal_prices'] ?? []) as $tierPrice => $tierPoints) {
		$normalized = number_format((float)$tierPrice, 2, '.', '');
		$points = (int)$tierPoints;
		if ($normalized === $requested && (float)$requested > 0 && $points > 0) {
			return [
				'price' => $requested,
				'points' => $points,
			];
		}
	}

	return false;
}

function payment_gateway_create_reference(string $provider): string {
	return $provider . '_' . bin2hex(random_bytes(16));
}

function payment_gateway_sql($value): string {
	return mysql_znote_escape_string((string)($value ?? ''));
}

function payment_gateway_insert_transaction(string $provider, int $accountId, string $price, string $currency, int $points, bool $testMode): string {
	$reference = payment_gateway_create_reference($provider);
	$now = time();

	$p = payment_gateway_sql($provider);
	$r = payment_gateway_sql($reference);
	$c = payment_gateway_sql(strtoupper($currency));
	$priceSql = payment_gateway_sql($price);
	$test = $testMode ? 1 : 0;

	mysql_insert("
		INSERT INTO `znote_payment_transactions`
			(`provider`, `reference`, `account_id`, `price`, `currency`, `points`, `status`, `credited`, `test_mode`, `created_at`, `updated_at`)
		VALUES
			('{$p}', '{$r}', {$accountId}, '{$priceSql}', '{$c}', {$points}, 'pending', 0, {$test}, {$now}, {$now});
	");

	return $reference;
}

function payment_gateway_update_provider_reference(string $provider, string $reference, string $providerReference, array $payload = []): void {
	$p = payment_gateway_sql($provider);
	$r = payment_gateway_sql($reference);
	$pr = payment_gateway_sql($providerReference);
	$body = payment_gateway_sql(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
	$now = time();

	mysql_update("
		UPDATE `znote_payment_transactions`
		SET `provider_reference` = '{$pr}', `payload` = '{$body}', `updated_at` = {$now}
		WHERE `provider` = '{$p}' AND `reference` = '{$r}' LIMIT 1;
	");
}

function payment_gateway_update_status(string $provider, string $reference, string $status, ?string $providerReference = null, array $payload = []): void {
	$p = payment_gateway_sql($provider);
	$r = payment_gateway_sql($reference);
	$s = payment_gateway_sql($status);
	$now = time();
	$sets = ["`status` = '{$s}'", "`updated_at` = {$now}"];

	if ($providerReference !== null && $providerReference !== '') {
		$sets[] = "`provider_reference` = '" . payment_gateway_sql($providerReference) . "'";
	}
	if ($payload) {
		$sets[] = "`payload` = '" . payment_gateway_sql(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)) . "'";
	}

	mysql_update("
		UPDATE `znote_payment_transactions`
		SET " . implode(', ', $sets) . "
		WHERE `provider` = '{$p}' AND `reference` = '{$r}' LIMIT 1;
	");
}

function payment_gateway_log_event(string $provider, string $eventId, ?string $providerReference, ?string $paymentReference, string $status, string $payload): void {
	$p = payment_gateway_sql($provider);
	$e = payment_gateway_sql($eventId !== '' ? $eventId : hash('sha256', $payload));
	$pr = payment_gateway_sql($providerReference ?? '');
	$ref = payment_gateway_sql($paymentReference ?? '');
	$s = payment_gateway_sql($status);
	$body = payment_gateway_sql(substr($payload, 0, 65000));
	$now = time();

	mysql_insert("
		INSERT INTO `znote_payment_events`
			(`provider`, `event_id`, `provider_reference`, `payment_reference`, `status`, `payload`, `received_at`)
		VALUES
			('{$p}', '{$e}', " . ($pr === '' ? 'NULL' : "'{$pr}'") . ", " . ($ref === '' ? 'NULL' : "'{$ref}'") . ", '{$s}', '{$body}', {$now})
		ON DUPLICATE KEY UPDATE `received_at` = `received_at`;
	");
}

function payment_gateway_http(string $method, string $url, array $headers = [], $body = null): array {
	if (!function_exists('curl_init')) {
		return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => 'cURL is not enabled'];
	}

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
	curl_setopt($ch, CURLOPT_TIMEOUT, 45);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
	curl_setopt($ch, CURLOPT_USERAGENT, 'ZnoteX/' . ($GLOBALS['version'] ?? '2.0.0'));

	$ca = __DIR__ . '/../cert/cacert.pem';
	if (is_file($ca)) {
		curl_setopt($ch, CURLOPT_CAINFO, $ca);
	}

	if ($headers) {
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	}
	if ($body !== null) {
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
	}

	$response = curl_exec($ch);
	$error = curl_error($ch);
	$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	$json = null;
	if (is_string($response) && $response !== '') {
		$decoded = json_decode($response, true);
		if (is_array($decoded)) {
			$json = $decoded;
		}
	}

	return [
		'ok' => $status >= 200 && $status < 300 && $response !== false,
		'status' => $status,
		'body' => is_string($response) ? $response : '',
		'json' => $json,
		'error' => $error,
	];
}

function payment_gateway_stripe_api(string $method, string $path, array $params = []): array {
	$secret = trim((string)payment_gateway_config('stripe', 'secret_key', ''));
	if ($secret === '') {
		return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => 'Stripe secret key is missing'];
	}

	$body = strtoupper($method) === 'GET' ? null : http_build_query($params);
	return payment_gateway_http($method, 'https://api.stripe.com' . $path, [
		'Authorization: Bearer ' . $secret,
		'Content-Type: application/x-www-form-urlencoded',
	], $body);
}

function payment_gateway_mercadopago_api(string $method, string $path, array $params = []): array {
	$token = trim((string)payment_gateway_config('mercadopago', 'access_token', ''));
	if ($token === '') {
		return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => 'Mercado Pago access token is missing'];
	}

	$body = strtoupper($method) === 'GET' ? null : json_encode($params, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
	return payment_gateway_http($method, 'https://api.mercadopago.com' . $path, [
		'Authorization: Bearer ' . $token,
		'Content-Type: application/json',
		'Accept: application/json',
	], $body);
}

function payment_gateway_create_checkout(string $provider, int $accountId, $price): array {
	global $config;

	$provider = strtolower($provider);
	if (!in_array($provider, ['stripe', 'mercadopago'], true)) {
		throw new RuntimeException('Unsupported payment provider.');
	}
	if (!payment_gateway_enabled($provider)) {
		throw new RuntimeException('This payment provider is disabled.');
	}

	payment_gateway_ensure_schema();

	$tier = payment_gateway_price_tier($price);
	if ($tier === false) {
		throw new RuntimeException('Invalid point package.');
	}

	$currency = strtoupper(trim((string)payment_gateway_config($provider, 'currency', $config['paypal']['currency'] ?? 'EUR')));
	if (!preg_match('/^[A-Z]{3}$/', $currency)) {
		throw new RuntimeException('Invalid payment currency.');
	}

	$testMode = !empty($config[$provider]['test_mode']);
	$reference = payment_gateway_insert_transaction($provider, $accountId, $tier['price'], $currency, $tier['points'], $testMode);
	$title = $tier['points'] . ' shop points on ' . ($config['site_title'] ?? 'ZnoteX');

	if ($provider === 'stripe') {
		$multiplier = (int)payment_gateway_config('stripe', 'amount_multiplier', 100);
		$amount = (int)round(((float)$tier['price']) * max(1, $multiplier));
		$response = payment_gateway_stripe_api('POST', '/v1/checkout/sessions', [
			'mode' => 'payment',
			'client_reference_id' => $reference,
			'success_url' => payment_gateway_return_url('stripe', 'success', ['provider' => 'stripe', 'session_id' => '{CHECKOUT_SESSION_ID}']),
			'cancel_url' => payment_gateway_return_url('stripe', 'failed', ['provider' => 'stripe']),
			'metadata' => [
				'znote_reference' => $reference,
				'account_id' => (string)$accountId,
				'points' => (string)$tier['points'],
			],
			'line_items' => [[
				'quantity' => 1,
				'price_data' => [
					'currency' => strtolower($currency),
					'unit_amount' => $amount,
					'product_data' => [
						'name' => $title,
					],
				],
			]],
		]);

		if (!$response['ok'] || empty($response['json']['url']) || empty($response['json']['id'])) {
			payment_gateway_update_status('stripe', $reference, 'create_failed', null, $response['json'] ?? []);
			throw new RuntimeException('Stripe checkout creation failed.');
		}

		payment_gateway_update_provider_reference('stripe', $reference, (string)$response['json']['id'], $response['json']);
		return ['url' => (string)$response['json']['url'], 'reference' => $reference];
	}

	$response = payment_gateway_mercadopago_api('POST', '/checkout/preferences', [
		'external_reference' => $reference,
		'notification_url' => payment_gateway_webhook_url('mercadopago'),
		'back_urls' => [
			'success' => payment_gateway_return_url('mercadopago', 'success', ['provider' => 'mercadopago']),
			'failure' => payment_gateway_return_url('mercadopago', 'failed', ['provider' => 'mercadopago']),
			'pending' => payment_gateway_return_url('mercadopago', 'success', ['provider' => 'mercadopago', 'pending' => 1]),
		],
		'metadata' => [
			'znote_reference' => $reference,
			'account_id' => $accountId,
			'points' => $tier['points'],
		],
		'items' => [[
			'title' => $title,
			'quantity' => 1,
			'currency_id' => $currency,
			'unit_price' => (float)$tier['price'],
		]],
	]);

	$url = '';
	if ($testMode && !empty($response['json']['sandbox_init_point'])) {
		$url = (string)$response['json']['sandbox_init_point'];
	} elseif (!empty($response['json']['init_point'])) {
		$url = (string)$response['json']['init_point'];
	}

	if (!$response['ok'] || $url === '' || empty($response['json']['id'])) {
		payment_gateway_update_status('mercadopago', $reference, 'create_failed', null, $response['json'] ?? []);
		throw new RuntimeException('Mercado Pago checkout creation failed.');
	}

	payment_gateway_update_provider_reference('mercadopago', $reference, (string)$response['json']['id'], $response['json']);
	return ['url' => $url, 'reference' => $reference];
}

function payment_gateway_parse_header_signature(string $header): array {
	$out = [];
	foreach (explode(',', $header) as $part) {
		$bits = explode('=', trim($part), 2);
		if (count($bits) === 2) {
			$out[$bits[0]][] = $bits[1];
		}
	}
	return $out;
}

function payment_gateway_verify_stripe_signature(string $payload, string $header): bool {
	$secret = trim((string)payment_gateway_config('stripe', 'webhook_secret', ''));
	if ($secret === '' || $header === '') {
		return false;
	}

	$parts = payment_gateway_parse_header_signature($header);
	$timestamp = isset($parts['t'][0]) ? (int)$parts['t'][0] : 0;
	if ($timestamp <= 0 || abs(time() - $timestamp) > 300 || empty($parts['v1'])) {
		return false;
	}

	$expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
	foreach ($parts['v1'] as $sig) {
		if (hash_equals($expected, $sig)) {
			return true;
		}
	}

	return false;
}

function payment_gateway_verify_mercadopago_signature(string $dataId, string $requestId, string $header): bool {
	$secret = trim((string)payment_gateway_config('mercadopago', 'webhook_secret', ''));
	if ($secret === '' || $dataId === '' || $requestId === '' || $header === '') {
		return false;
	}

	$parts = payment_gateway_parse_header_signature($header);
	$timestamp = isset($parts['ts'][0]) ? (int)$parts['ts'][0] : 0;
	$signature = $parts['v1'][0] ?? '';
	if ($timestamp <= 0 || abs(time() - $timestamp) > 900 || $signature === '') {
		return false;
	}

	$manifest = 'id:' . $dataId . ';request-id:' . $requestId . ';ts:' . $timestamp . ';';
	$expected = hash_hmac('sha256', $manifest, $secret);

	return hash_equals($expected, $signature);
}

function payment_gateway_credit_transaction(string $provider, string $reference, string $providerReference, string $expectedStatus, array $payload = []): string {
	global $connect;

	$p = payment_gateway_sql($provider);
	$r = payment_gateway_sql($reference);
	$pr = payment_gateway_sql($providerReference);
	$now = time();
	$body = payment_gateway_sql(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));

	try {
		mysqli_begin_transaction($connect);

		$result = mysqli_query($connect, "
			SELECT *
			FROM `znote_payment_transactions`
			WHERE `provider` = '{$p}' AND `reference` = '{$r}'
			LIMIT 1
			FOR UPDATE;
		");
		$tx = $result instanceof mysqli_result ? mysqli_fetch_assoc($result) : false;
		if ($result instanceof mysqli_result) {
			mysqli_free_result($result);
		}

		if (!is_array($tx)) {
			mysqli_rollback($connect);
			return 'missing_transaction';
		}
		if ((int)$tx['credited'] === 1) {
			mysqli_commit($connect);
			return 'already_credited';
		}
		if ($providerReference !== '') {
			$storedProviderReference = (string)($tx['provider_reference'] ?? '');
			if ($storedProviderReference !== '' && $storedProviderReference !== $providerReference && $provider === 'stripe') {
				mysqli_rollback($connect);
				return 'provider_reference_mismatch';
			}
		}

		$accountId = (int)$tx['account_id'];
		$points = (int)$tx['points'];
		if ($accountId <= 0 || $points <= 0) {
			mysqli_rollback($connect);
			return 'invalid_transaction';
		}
		if (!payment_gateway_provider_amount_matches($provider, $tx, $payload)) {
			mysqli_rollback($connect);
			payment_gateway_update_status($provider, $reference, 'amount_mismatch', $providerReference, $payload);
			return 'amount_mismatch';
		}

		$account = mysqli_query($connect, "SELECT `id` FROM `znote_accounts` WHERE `account_id` = {$accountId} LIMIT 1 FOR UPDATE;");
		$accountRow = $account instanceof mysqli_result ? mysqli_fetch_assoc($account) : false;
		if ($account instanceof mysqli_result) {
			mysqli_free_result($account);
		}
		if (!is_array($accountRow)) {
			mysqli_query($connect, "INSERT INTO `znote_accounts` (`account_id`, `ip`, `created`, `points`, `flag`) VALUES ({$accountId}, 0, {$now}, 0, '');");
		}

		mysqli_query($connect, "UPDATE `znote_accounts` SET `points` = COALESCE(`points`, 0) + {$points} WHERE `account_id` = {$accountId};");
		mysqli_query($connect, "
			UPDATE `znote_payment_transactions`
			SET `provider_reference` = " . ($pr === '' ? "`provider_reference`" : "'{$pr}'") . ",
				`status` = '" . payment_gateway_sql($expectedStatus) . "',
				`credited` = 1,
				`credited_at` = {$now},
				`updated_at` = {$now},
				`payload` = '{$body}'
			WHERE `id` = " . (int)$tx['id'] . ";
		");

		mysqli_commit($connect);
		return 'credited';
	} catch (Throwable $e) {
		mysqli_rollback($connect);
		error_log('Payment credit failed: ' . $e->getMessage());
		return 'credit_failed';
	}
}

function payment_gateway_provider_amount_matches(string $provider, array $transaction, array $payload): bool {
	$currency = strtoupper((string)($transaction['currency'] ?? ''));
	$price = (float)($transaction['price'] ?? 0);

	if ($provider === 'stripe') {
		$multiplier = (int)payment_gateway_config('stripe', 'amount_multiplier', 100);
		$expectedAmount = (int)round($price * max(1, $multiplier));
		$actualAmount = (int)($payload['amount_total'] ?? 0);
		$actualCurrency = strtoupper((string)($payload['currency'] ?? ''));

		return $expectedAmount > 0 && $actualAmount === $expectedAmount && $actualCurrency === $currency;
	}

	if ($provider === 'mercadopago') {
		$actualAmount = number_format((float)($payload['transaction_amount'] ?? 0), 2, '.', '');
		$expectedAmount = number_format($price, 2, '.', '');
		$actualCurrency = strtoupper((string)($payload['currency_id'] ?? ''));

		return $expectedAmount !== '0.00' && $actualAmount === $expectedAmount && $actualCurrency === $currency;
	}

	return false;
}

function payment_gateway_handle_stripe_webhook(string $payload): array {
	$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
	if (!payment_gateway_verify_stripe_signature($payload, $signature)) {
		return ['code' => 401, 'status' => 'invalid_signature'];
	}

	$event = json_decode($payload, true);
	if (!is_array($event)) {
		return ['code' => 400, 'status' => 'invalid_json'];
	}

	$eventId = (string)($event['id'] ?? hash('sha256', $payload));
	$type = (string)($event['type'] ?? '');
	$session = $event['data']['object'] ?? [];
	$sessionId = is_array($session) ? (string)($session['id'] ?? '') : '';
	$reference = is_array($session) ? (string)($session['client_reference_id'] ?? ($session['metadata']['znote_reference'] ?? '')) : '';

	payment_gateway_log_event('stripe', $eventId, $sessionId, $reference, $type !== '' ? $type : 'received', $payload);

	if (!in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
		return ['code' => 200, 'status' => 'ignored'];
	}
	if ($sessionId === '' || $reference === '') {
		return ['code' => 400, 'status' => 'missing_reference'];
	}

	$response = payment_gateway_stripe_api('GET', '/v1/checkout/sessions/' . rawurlencode($sessionId));
	if (!$response['ok'] || !is_array($response['json'])) {
		return ['code' => 502, 'status' => 'provider_lookup_failed'];
	}

	$verified = $response['json'];
	$verifiedReference = (string)($verified['client_reference_id'] ?? ($verified['metadata']['znote_reference'] ?? ''));
	if ($verifiedReference !== $reference || ($verified['payment_status'] ?? '') !== 'paid') {
		payment_gateway_update_status('stripe', $reference, 'not_paid', $sessionId, $verified);
		return ['code' => 200, 'status' => 'not_paid'];
	}

	$result = payment_gateway_credit_transaction('stripe', $reference, $sessionId, 'paid', $verified);
	return ['code' => 200, 'status' => $result];
}

function payment_gateway_handle_mercadopago_webhook(string $payload): array {
	$body = json_decode($payload, true);
	if (!is_array($body)) {
		$body = [];
	}

	$dataId = (string)($_GET['data.id'] ?? $_GET['id'] ?? $_GET['data_id'] ?? ($body['data']['id'] ?? ''));
	$requestId = (string)($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
	$signature = (string)($_SERVER['HTTP_X_SIGNATURE'] ?? '');

	if (!payment_gateway_verify_mercadopago_signature($dataId, $requestId, $signature)) {
		return ['code' => 401, 'status' => 'invalid_signature'];
	}

	$eventId = (string)($body['id'] ?? (($body['action'] ?? 'payment') . '_' . $dataId));
	$type = (string)($body['type'] ?? $_GET['type'] ?? $_GET['topic'] ?? '');
	payment_gateway_log_event('mercadopago', $eventId, $dataId, null, $type !== '' ? $type : 'received', $payload);

	if ($dataId === '') {
		return ['code' => 400, 'status' => 'missing_payment_id'];
	}
	if ($type !== '' && !in_array($type, ['payment', 'payment.updated', 'payment.created'], true)) {
		return ['code' => 200, 'status' => 'ignored'];
	}

	$response = payment_gateway_mercadopago_api('GET', '/v1/payments/' . rawurlencode($dataId));
	if (!$response['ok'] || !is_array($response['json'])) {
		return ['code' => 502, 'status' => 'provider_lookup_failed'];
	}

	$payment = $response['json'];
	$reference = (string)($payment['external_reference'] ?? ($payment['metadata']['znote_reference'] ?? ''));
	if ($reference === '') {
		return ['code' => 400, 'status' => 'missing_reference'];
	}

	if (($payment['status'] ?? '') !== 'approved') {
		payment_gateway_update_status('mercadopago', $reference, (string)($payment['status'] ?? 'not_approved'), $dataId, $payment);
		return ['code' => 200, 'status' => 'not_approved'];
	}

	$result = payment_gateway_credit_transaction('mercadopago', $reference, $dataId, 'approved', $payment);
	return ['code' => 200, 'status' => $result];
}
?>
