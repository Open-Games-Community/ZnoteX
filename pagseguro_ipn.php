<?php
	// Require the functions to fetch config values
	require 'config.php';

	$pagseguro = $config['pagseguro'];
	$notificationCode = $_POST['notificationCode'] ?? null;
	$notificationType = $_POST['notificationType'] ?? null;

	// Require the functions to connect to database
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

	// Util function to insert log
	function report($code, $details = '') {
		$connectedIp = $_SERVER['REMOTE_ADDR'];
		$details = getValue($details);
		$details .= '\nConnection from IP: '. $connectedIp;
		mysql_insert('INSERT INTO `znote_pagseguro_notifications` VALUES (null, \'' . getValue($code) . '\', \'' . $details . '\', CURRENT_TIMESTAMP)');
	}

	function VerifyPagseguroIPN($code) {
		global $pagseguro;
		$url = $pagseguro['urls']['ws'];

		$cURL = curl_init();
		curl_setopt($cURL, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($cURL, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($cURL, CURLOPT_URL, 'https://' . $url . '/v3/transactions/notifications/' . $code . '?email=' . $pagseguro['email'] . '&token=' . $pagseguro['token']);
		curl_setopt($cURL, CURLOPT_HEADER, false);
		curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($cURL, CURLOPT_FORBID_REUSE, true);
		curl_setopt($cURL, CURLOPT_FRESH_CONNECT, true);
		curl_setopt($cURL, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($cURL, CURLOPT_TIMEOUT, 60);
		curl_setopt($cURL, CURLINFO_HEADER_OUT, true);
		curl_setopt($cURL, CURLOPT_HTTPHEADER, array(
			'Connection: close',
			'Expect: ',
		));
		$Response = curl_exec($cURL);
		$Status = (int)curl_getinfo($cURL, CURLINFO_HTTP_CODE);
		curl_close($cURL);

		$output = print_r($Response, true);
		if(empty($Response) OR !$Status){
			return null;
		}
		if(intval($Status / 100) != 2){
			return false;
		}
		return trim($Response);
	}

	// Send an empty HTTP 200 OK response to acknowledge receipt of the notification
	header('HTTP/1.1 200 OK');

	if(empty($notificationCode) || empty($notificationType)){
		report($notificationCode, 'notificationCode or notificationType is empty. Type: ' . $notificationType . ', Code: ' . $notificationCode);
		exit();
	}

	if ($notificationType !== 'transaction') {
		report($notificationCode, 'Unknown ' . $notificationType . ' notificationType');
		exit();
	}

	$rawPayment = VerifyPagseguroIPN($notificationCode);
	$payment = simplexml_load_string((string)$rawPayment);
	if ($payment === false) {
		die('Error: invalid PagSeguro response.');
	}
	$paymentStatus = (int) $payment->status;
	$paymentCode = sanitize($payment->code);

	report($notificationCode, $rawPayment);

	// Updating Payment Status
	mysql_update('UPDATE `znote_pagseguro` SET `payment_status` = ' . $paymentStatus . ' WHERE `transaction` = \'' . $paymentCode . '\' ');

	// Check that the payment_status is Completed
	if ($paymentStatus == 3) {

		// Check that transaction has not been previously processed
		$transaction = mysql_select_single('SELECT `transaction`, `completed` FROM `znote_pagseguro` WHERE `transaction`= \'' . $paymentCode .'\'');
		$status = true;
		$customRaw = (string)$payment->reference;
		$custom = (int)$customRaw;

		if (!is_array($transaction) || $transaction['completed'] == '1') {
			$status = false;
		}

		if ($payment->grossAmount == 0.0) $status = false; // Wrong ammount of money
		$item = $payment->items->item[0];
		$paymentData = array(
			'provider' => 'pagseguro',
			'reference' => (string)$paymentCode,
			'custom' => $customRaw,
			'account_id' => $custom,
			'price' => (float)$item->amount,
			'currency' => $pagseguro['currency'] ?? '',
			'points' => (int)$item->quantity,
			'status' => 'completed',
			'raw' => $rawPayment,
			'resolved' => false,
		);
		if (function_exists('znote_hook_filter')) {
			$paymentData = znote_hook_filter('payment.resolve', $paymentData, array('provider' => 'pagseguro', 'raw' => $rawPayment));
		}
		if (!empty($paymentData['resolved'])) {
			$custom = (int)($paymentData['account_id'] ?? 0);
		} elseif ($item->amount != ($pagseguro['price'] / 100)) $status = false;
		if (number_format((float)$item->amount, 2, '.', '') !== number_format((float)($paymentData['price'] ?? 0), 2, '.', '')) $status = false;
		if ($custom <= 0) $status = false;

		if ($status) {
			// transaction log
			mysql_update('UPDATE `znote_pagseguro` SET `completed` = 1 WHERE `transaction` = \'' . $paymentCode . '\'');

			// Process payment
			$data = mysql_select_single("SELECT `points` AS `old_points` FROM `znote_accounts` WHERE `account_id`='$custom';");

			// Give points to user
			if (is_array($data)) {
				$paidPoints = (int)($paymentData['points'] ?? $item->quantity);
				$new_points = (int)$data['old_points'] + $paidPoints;
				mysql_update("UPDATE `znote_accounts` SET `points`='$new_points' WHERE `account_id`='$custom'");
				if (function_exists('znote_hook')) {
					znote_hook('payment.completed', array_merge($paymentData, array(
						'provider' => 'pagseguro',
						'reference' => (string)$paymentCode,
						'custom' => $customRaw,
						'account_id' => $custom,
						'price' => $paymentData['price'] ?? (float)$item->amount,
						'currency' => $paymentData['currency'] ?? ($pagseguro['currency'] ?? ''),
						'points' => $paidPoints,
						'status' => 'completed',
					)));
				}
			} else {
				report($notificationCode, 'No znote_accounts row for account_id ' . $custom);
			}
		}
	} else if ($paymentStatus == 7) {
		mysql_update('UPDATE `znote_pagseguro` SET `completed` = 1 WHERE `transaction` = \'' . $paymentCode . '\' ');
	}
?>
