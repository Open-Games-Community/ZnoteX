<?php
/**
 * Hosted payment gateways for shop points.
 *
 * Success/failed return pages never credit points. A payment becomes real only
 * after a signed webhook is verified, the provider API confirms the payment,
 * and the stored quote still matches amount, currency, account and points.
 */

function payment_gateway_ensure_schema(): void {
	db()->execute("
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

	db()->execute("
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

function payment_gateway_insert_transaction(string $provider, int $accountId, string $price, string $currency, int $points, bool $testMode): string {
	$reference = payment_gateway_create_reference($provider);
	$now = time();
	$test = $testMode ? 1 : 0;

	$inserted = db()->execute("
		INSERT INTO `znote_payment_transactions`
			(`provider`, `reference`, `account_id`, `price`, `currency`, `points`, `status`, `credited`, `test_mode`, `created_at`, `updated_at`)
		VALUES
			(?, ?, ?, ?, ?, ?, 'pending', 0, ?, ?, ?);
	", [$provider, $reference, $accountId, $price, strtoupper($currency), $points, $test, $now, $now]);
	if (!$inserted) {
		throw new RuntimeException('Payment transaction could not be created.');
	}

	return $reference;
}

function payment_gateway_update_provider_reference(string $provider, string $reference, string $providerReference, array $payload = []): bool {
	$body = (string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
	$now = time();

	return db()->execute("
		UPDATE `znote_payment_transactions`
		SET `provider_reference` = ?, `payload` = ?, `updated_at` = ?
		WHERE `provider` = ? AND `reference` = ? LIMIT 1;
	", [$providerReference, $body, $now, $provider, $reference]);
}

function payment_gateway_update_status(string $provider, string $reference, string $status, ?string $providerReference = null, array $payload = []): void {
	$now = time();
	$sets = ["`status` = ?", "`updated_at` = ?"];
	$params = [$status, $now];

	if ($providerReference !== null && $providerReference !== '') {
		$sets[] = "`provider_reference` = ?";
		$params[] = $providerReference;
	}
	if ($payload) {
		$sets[] = "`payload` = ?";
		$params[] = (string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
	}

	$params[] = $provider;
	$params[] = $reference;

	db()->execute("
		UPDATE `znote_payment_transactions`
		SET " . implode(', ', $sets) . "
		WHERE `provider` = ? AND `reference` = ? LIMIT 1;
	", $params);
}

function payment_gateway_log_event(string $provider, string $eventId, ?string $providerReference, ?string $paymentReference, string $status, string $payload): void {
	$eventId = $eventId !== '' ? $eventId : hash('sha256', $payload);
	$eventId = substr($eventId, 0, 128);
	$providerReference = $providerReference !== null ? substr($providerReference, 0, 128) : null;
	$paymentReference = $paymentReference !== null ? substr($paymentReference, 0, 128) : null;
	$providerReference = ($providerReference !== null && $providerReference !== '') ? $providerReference : null;
	$paymentReference = ($paymentReference !== null && $paymentReference !== '') ? $paymentReference : null;
	$body = substr($payload, 0, 65000);
	$now = time();

	db()->execute("
		INSERT INTO `znote_payment_events`
			(`provider`, `event_id`, `provider_reference`, `payment_reference`, `status`, `payload`, `received_at`)
		VALUES
			(?, ?, ?, ?, ?, ?, ?)
		ON DUPLICATE KEY UPDATE `received_at` = `received_at`;
	", [$provider, $eventId, $providerReference, $paymentReference, $status, $body, $now]);
}

function payment_gateway_update_event_status(string $provider, string $eventId, string $status, ?string $paymentReference = null): void {
	$params = [substr($status, 0, 32)];
	$sets = ['`status` = ?'];
	if ($paymentReference !== null && $paymentReference !== '') {
		$sets[] = '`payment_reference` = ?';
		$params[] = substr($paymentReference, 0, 128);
	}
	$params[] = $provider;
	$params[] = substr($eventId, 0, 128);
	db()->execute(
		"UPDATE `znote_payment_events` SET " . implode(', ', $sets) . " WHERE `provider` = ? AND `event_id` = ? LIMIT 1;",
		$params
	);
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
	curl_setopt($ch, CURLOPT_USERAGENT, 'ZnoteX/' . ($GLOBALS['version'] ?? '2.0.1'));

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

		if (!payment_gateway_update_provider_reference('stripe', $reference, (string)$response['json']['id'], $response['json'])) {
			throw new RuntimeException('Stripe checkout could not be stored.');
		}
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

	if (!payment_gateway_update_provider_reference('mercadopago', $reference, (string)$response['json']['id'], $response['json'])) {
		throw new RuntimeException('Mercado Pago checkout could not be stored.');
	}
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

function payment_gateway_validate_transaction(string $provider, $tx, string $providerReference, array $payload): string {
	if (!is_array($tx)) {
		return 'missing_transaction';
	}
	if ((int)$tx['credited'] === 1) {
		return 'already_credited';
	}
	if ($providerReference !== '') {
		$storedProviderReference = (string)($tx['provider_reference'] ?? '');
		if ($storedProviderReference !== '' && $storedProviderReference !== $providerReference && $provider === 'stripe') {
			return 'provider_reference_mismatch';
		}
	}

	$accountId = (int)$tx['account_id'];
	$points = (int)$tx['points'];
	if ($accountId <= 0 || $points <= 0) {
		return 'invalid_transaction';
	}
	if (!payment_gateway_provider_amount_matches($provider, $tx, $payload)) {
		return 'amount_mismatch';
	}
	if (!payment_gateway_provider_mode_matches($tx, $payload)) {
		return 'mode_mismatch';
	}

	return 'ok';
}

function payment_gateway_credit_transaction(string $provider, string $reference, string $providerReference, string $expectedStatus, array $payload = []): string {
	$now = time();
	$body = (string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
	$db = db();

	try {
		if (!$db->beginTransaction()) {
			return 'credit_failed';
		}

		$tx = $db->fetchOne("
			SELECT *
			FROM `znote_payment_transactions`
			WHERE `provider` = ? AND `reference` = ?
			LIMIT 1
			FOR UPDATE;
		", [$provider, $reference]);

		$validation = payment_gateway_validate_transaction($provider, $tx, $providerReference, $payload);
		if ($validation === 'already_credited') {
			$db->commit();
			return 'already_credited';
		}
		if ($validation !== 'ok') {
			$db->rollback();
			if ($validation === 'amount_mismatch' || $validation === 'mode_mismatch') {
				payment_gateway_update_status($provider, $reference, $validation, $providerReference, $payload);
			}
			return $validation;
		}

		$accountId = (int)$tx['account_id'];
		$points = (int)$tx['points'];

		$accountRow = $db->fetchOne(
			"SELECT `id` FROM `znote_accounts` WHERE `account_id` = ? LIMIT 1 FOR UPDATE;",
			[$accountId]
		);
		if (!is_array($accountRow)) {
			if (!$db->execute(
				"INSERT INTO `znote_accounts` (`account_id`, `ip`, `created`, `points`, `flag`) VALUES (?, 0, ?, 0, '');",
				[$accountId, $now]
			)) {
				$db->rollback();
				return 'credit_failed';
			}
		}

		if (!$db->execute(
			"UPDATE `znote_accounts` SET `points` = COALESCE(`points`, 0) + ? WHERE `account_id` = ?;",
			[$points, $accountId]
		)) {
			$db->rollback();
			return 'credit_failed';
		}
		if (!$db->execute("
			UPDATE `znote_payment_transactions`
			SET `provider_reference` = COALESCE(NULLIF(?, ''), `provider_reference`),
				`status` = ?,
				`credited` = 1,
				`credited_at` = ?,
				`updated_at` = ?,
				`payload` = ?
			WHERE `id` = ?;
		", [$providerReference, $expectedStatus, $now, $now, $body, (int)$tx['id']])) {
			$db->rollback();
			return 'credit_failed';
		}

		if (!$db->commit()) {
			$db->rollback();
			return 'credit_failed';
		}
		try {
			payment_gateway_fire_completed($provider, $reference, $providerReference, $expectedStatus, $accountId, $points, $tx, $payload);
		} catch (Throwable $hookError) {
			error_log('Payment completed hook failed: ' . $hookError->getMessage());
		}
		return 'credited';
	} catch (Throwable $e) {
		$db->rollback();
		error_log('Payment credit failed: ' . $e->getMessage());
		return 'credit_failed';
	}
}

function payment_gateway_fire_completed(string $provider, string $reference, string $providerReference, string $status, int $accountId, int $points, array $tx, array $payload): void {
	if (!function_exists('znote_hook')) {
		return;
	}
	znote_hook('payment.completed', array(
		'provider' => $provider,
		'reference' => $reference,
		'provider_reference' => $providerReference,
		'account_id' => $accountId,
		'price' => $tx['price'] ?? null,
		'currency' => $tx['currency'] ?? null,
		'points' => $points,
		'status' => $status,
		'payload' => $payload,
	));
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

function payment_gateway_provider_mode_matches(array $transaction, array $payload): bool {
	if (array_key_exists('livemode', $payload)) {
		return (bool)$payload['livemode'] !== ((int)($transaction['test_mode'] ?? 0) === 1);
	}
	if (array_key_exists('live_mode', $payload)) {
		return (bool)$payload['live_mode'] !== ((int)($transaction['test_mode'] ?? 0) === 1);
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
		payment_gateway_update_event_status('stripe', $eventId, 'ignored', $reference);
		return ['code' => 200, 'status' => 'ignored'];
	}
	if ($sessionId === '' || $reference === '') {
		payment_gateway_update_event_status('stripe', $eventId, 'missing_reference', $reference);
		return ['code' => 400, 'status' => 'missing_reference'];
	}

	$response = payment_gateway_stripe_api('GET', '/v1/checkout/sessions/' . rawurlencode($sessionId));
	if (!$response['ok'] || !is_array($response['json'])) {
		payment_gateway_update_event_status('stripe', $eventId, 'provider_lookup_failed', $reference);
		return ['code' => 502, 'status' => 'provider_lookup_failed'];
	}

	$verified = $response['json'];
	$verifiedReference = (string)($verified['client_reference_id'] ?? ($verified['metadata']['znote_reference'] ?? ''));
	if ($verifiedReference !== $reference || ($verified['payment_status'] ?? '') !== 'paid') {
		payment_gateway_update_status('stripe', $reference, 'not_paid', $sessionId, $verified);
		payment_gateway_update_event_status('stripe', $eventId, 'not_paid', $reference);
		return ['code' => 200, 'status' => 'not_paid'];
	}

	$result = payment_gateway_credit_transaction('stripe', $reference, $sessionId, 'paid', $verified);
	payment_gateway_update_event_status('stripe', $eventId, $result, $reference);
	return ['code' => $result === 'credit_failed' || $result === 'missing_transaction' ? 500 : 200, 'status' => $result];
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
		payment_gateway_update_event_status('mercadopago', $eventId, 'missing_payment_id');
		return ['code' => 400, 'status' => 'missing_payment_id'];
	}
	if ($type !== '' && !in_array($type, ['payment', 'payment.updated', 'payment.created'], true)) {
		payment_gateway_update_event_status('mercadopago', $eventId, 'ignored');
		return ['code' => 200, 'status' => 'ignored'];
	}

	$response = payment_gateway_mercadopago_api('GET', '/v1/payments/' . rawurlencode($dataId));
	if (!$response['ok'] || !is_array($response['json'])) {
		payment_gateway_update_event_status('mercadopago', $eventId, 'provider_lookup_failed');
		return ['code' => 502, 'status' => 'provider_lookup_failed'];
	}

	$payment = $response['json'];
	$reference = (string)($payment['external_reference'] ?? ($payment['metadata']['znote_reference'] ?? ''));
	if ($reference === '') {
		payment_gateway_update_event_status('mercadopago', $eventId, 'missing_reference');
		return ['code' => 400, 'status' => 'missing_reference'];
	}

	if (($payment['status'] ?? '') !== 'approved') {
		payment_gateway_update_status('mercadopago', $reference, (string)($payment['status'] ?? 'not_approved'), $dataId, $payment);
		payment_gateway_update_event_status('mercadopago', $eventId, 'not_approved', $reference);
		return ['code' => 200, 'status' => 'not_approved'];
	}

	$result = payment_gateway_credit_transaction('mercadopago', $reference, $dataId, 'approved', $payment);
	payment_gateway_update_event_status('mercadopago', $eventId, $result, $reference);
	return ['code' => $result === 'credit_failed' || $result === 'missing_transaction' ? 500 : 200, 'status' => $result];
}
?>
