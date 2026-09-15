<?php

declare(strict_types=1);

namespace ZnoteX\Tests\Security;

use PHPUnit\Framework\TestCase;

final class PaymentWebhookTest extends TestCase
{
	private const SECRET = 'whsec_test_only_1234567890';

	protected function setUp(): void
	{
		$GLOBALS['config'] = [
			'stripe' => ['webhook_secret' => self::SECRET],
			'mercadopago' => ['webhook_secret' => self::SECRET],
		];
	}

	public function testGenuineStripeSignatureIsAccepted(): void
	{
		$payload = '{"id":"evt_1","type":"checkout.session.completed"}';
		$header = $this->realStripeHeader($payload);

		$this->assertTrue(\payment_gateway_verify_stripe_signature($payload, $header));
	}

	public function testForgedStripeSignatureIsRejected(): void
	{
		$payload = '{"id":"evt_1","type":"checkout.session.completed"}';
		$timestamp = time();
		$forgedSignature = hash_hmac('sha256', $timestamp . '.' . $payload, 'attacker-does-not-know-the-real-secret');
		$header = "t={$timestamp},v1={$forgedSignature}";

		$this->assertFalse(\payment_gateway_verify_stripe_signature($payload, $header));
	}

	public function testTamperedPayloadInvalidatesAGenuineSignature(): void
	{
		$originalPayload = '{"id":"evt_1","amount_total":100}';
		$header = $this->realStripeHeader($originalPayload);

		$tamperedPayload = '{"id":"evt_1","amount_total":999999}';
		$this->assertFalse(\payment_gateway_verify_stripe_signature($tamperedPayload, $header));
	}

	public function testReplayedOldSignatureIsRejected(): void
	{
		$payload = '{"id":"evt_1"}';
		$oldTimestamp = time() - 3600;
		$signature = hash_hmac('sha256', $oldTimestamp . '.' . $payload, self::SECRET);
		$header = "t={$oldTimestamp},v1={$signature}";

		$this->assertFalse(\payment_gateway_verify_stripe_signature($payload, $header));
	}

	public function testMissingHeaderIsRejected(): void
	{
		$this->assertFalse(\payment_gateway_verify_stripe_signature('{}', ''));
	}

	public function testEmptyConfiguredSecretRejectsEverything(): void
	{
		$GLOBALS['config']['stripe']['webhook_secret'] = '';
		$payload = '{"id":"evt_1"}';
		$header = $this->realStripeHeader($payload, 'whatever');

		$this->assertFalse(\payment_gateway_verify_stripe_signature($payload, $header));
	}

	public function testGenuineMercadopagoSignatureIsAccepted(): void
	{
		$dataId = '123456';
		$requestId = 'req-1';
		$header = $this->realMercadopagoHeader($dataId, $requestId, $timestamp = (string)time());

		$this->assertTrue(\payment_gateway_verify_mercadopago_signature($dataId, $requestId, $header));
	}

	public function testForgedMercadopagoSignatureIsRejected(): void
	{
		$dataId = '123456';
		$requestId = 'req-1';
		$timestamp = (string)time();
		$manifest = 'id:' . $dataId . ';request-id:' . $requestId . ';ts:' . $timestamp . ';';
		$forged = hash_hmac('sha256', $manifest, 'not-the-real-secret');
		$header = "ts={$timestamp},v1={$forged}";

		$this->assertFalse(\payment_gateway_verify_mercadopago_signature($dataId, $requestId, $header));
	}

	public function testMercadopagoSignatureBoundToTheWrongDataIdIsRejected(): void
	{
		$requestId = 'req-1';
		$timestamp = (string)time();
		$header = $this->realMercadopagoHeader('legit-payment-id', $requestId, $timestamp);

		// An attacker who intercepted a genuine notification for one payment
		// cannot replay it against a different data id.
		$this->assertFalse(\payment_gateway_verify_mercadopago_signature('someone-elses-payment-id', $requestId, $header));
	}

	public function testAlreadyCreditedTransactionIsNeverCreditedTwice(): void
	{
		$tx = ['credited' => 1, 'account_id' => 5, 'points' => 100, 'price' => 9.99, 'currency' => 'USD'];
		$this->assertSame('already_credited', \payment_gateway_validate_transaction('stripe', $tx, '', []));
	}

	public function testMissingTransactionIsRejected(): void
	{
		$this->assertSame('missing_transaction', \payment_gateway_validate_transaction('stripe', false, '', []));
	}

	public function testProviderReferenceCannotBeSwappedAfterTheFactForStripe(): void
	{
		$tx = [
			'credited' => 0,
			'provider_reference' => 'pi_original_genuine_payment_intent',
			'account_id' => 5,
			'points' => 100,
			'price' => 9.99,
			'currency' => 'USD',
		];

		$result = \payment_gateway_validate_transaction('stripe', $tx, 'pi_attacker_supplied_different_intent', []);
		$this->assertSame('provider_reference_mismatch', $result);
	}

	public function testInvalidAccountOrPointsIsRejected(): void
	{
		$tx = ['credited' => 0, 'account_id' => 0, 'points' => 100, 'price' => 9.99, 'currency' => 'USD'];
		$this->assertSame('invalid_transaction', \payment_gateway_validate_transaction('mercadopago', $tx, '', []));

		$tx['account_id'] = 5;
		$tx['points'] = 0;
		$this->assertSame('invalid_transaction', \payment_gateway_validate_transaction('mercadopago', $tx, '', []));
	}

	public function testAmountMismatchBlocksCrediting(): void
	{
		$tx = ['credited' => 0, 'account_id' => 5, 'points' => 100, 'price' => 9.99, 'currency' => 'USD'];
		$payload = ['transaction_amount' => 0.01, 'currency_id' => 'usd'];

		$this->assertSame('amount_mismatch', \payment_gateway_validate_transaction('mercadopago', $tx, '', $payload));
	}

	public function testLiveModeCannotCreditATestModeTransactionOrViceVersa(): void
	{
		$tx = ['credited' => 0, 'account_id' => 5, 'points' => 100, 'price' => 9.99, 'currency' => 'USD', 'test_mode' => 1];
		$payload = ['transaction_amount' => 9.99, 'currency_id' => 'usd', 'live_mode' => true];

		$this->assertSame('mode_mismatch', \payment_gateway_validate_transaction('mercadopago', $tx, '', $payload));
	}

	public function testAFullyValidTransactionPassesValidation(): void
	{
		$tx = ['credited' => 0, 'account_id' => 5, 'points' => 100, 'price' => 9.99, 'currency' => 'USD', 'test_mode' => 0];
		$payload = ['transaction_amount' => 9.99, 'currency_id' => 'usd', 'live_mode' => true];

		$this->assertSame('ok', \payment_gateway_validate_transaction('mercadopago', $tx, '', $payload));
	}

	private function realStripeHeader(string $payload, ?string $secret = null): string
	{
		$timestamp = time();
		$signature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret ?? self::SECRET);
		return "t={$timestamp},v1={$signature}";
	}

	private function realMercadopagoHeader(string $dataId, string $requestId, string $timestamp): string
	{
		$manifest = 'id:' . $dataId . ';request-id:' . $requestId . ';ts:' . $timestamp . ';';
		$signature = hash_hmac('sha256', $manifest, self::SECRET);
		return "ts={$timestamp},v1={$signature}";
	}
}
