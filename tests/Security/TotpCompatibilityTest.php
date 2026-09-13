<?php

declare(strict_types=1);

namespace ZnoteX\Tests\Security;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/engine/function/rfc6238.php';

final class TotpCompatibilityTest extends TestCase
{
	public function testBase32DecodesRfc4648Vectors(): void
	{
		$vectors = [
			'MY======' => 'f',
			'MZXQ====' => 'fo',
			'MZXW6===' => 'foo',
			'MZXW6YQ=' => 'foob',
			'MZXW6YTB' => 'fooba',
			'MZXW6YTBOI======' => 'foobar',
		];

		foreach ($vectors as $encoded => $plain) {
			$this->assertSame($plain, \Base32Static::decode($encoded));
		}
	}

	public function testTwentyCharacterUnpaddedAuthenticatorSecretRoundTrips(): void
	{
		$plain = 'Hello World!';
		$secret = \Base32Static::encode($plain, false);

		$this->assertSame(20, strlen($secret));
		$this->assertSame($plain, \Base32Static::decode($secret));
	}

	public function testLowercaseUnpaddedSecretsAreAccepted(): void
	{
		$this->assertSame('Hello World!', \Base32Static::decode('jbswy3dpeblw64tmmqqq'));
	}

	public function testMalformedBase32IsRejected(): void
	{
		$this->assertFalse(\Base32Static::decode('INVALID-SECRET'));
		$this->assertFalse(\Base32Static::decode('M=ZXW6==='));
	}

	public function testAuthenticatorUriDeclaresPortableTotpParameters(): void
	{
		$url = \TokenAuth6238::getBarCodeUrl('account', 'localhost', 'JBSWY3DPEHPK3PXP', 'ZnoteX');
		parse_str((string)parse_url($url, PHP_URL_QUERY), $qrQuery);
		$this->assertArrayHasKey('data', $qrQuery);

		$otpauth = (string)$qrQuery['data'];
		$this->assertStringStartsWith('otpauth://totp/account%40localhost?', $otpauth);
		parse_str((string)parse_url($otpauth, PHP_URL_QUERY), $totpQuery);

		$this->assertSame('JBSWY3DPEHPK3PXP', $totpQuery['secret'] ?? null);
		$this->assertSame('ZnoteX', $totpQuery['issuer'] ?? null);
		$this->assertSame('SHA1', $totpQuery['algorithm'] ?? null);
		$this->assertSame('6', $totpQuery['digits'] ?? null);
		$this->assertSame('30', $totpQuery['period'] ?? null);
	}
}
