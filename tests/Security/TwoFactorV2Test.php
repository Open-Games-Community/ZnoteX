<?php

declare(strict_types=1);

namespace ZnoteX\Tests\Security;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/engine/function/rfc6238.php';
require_once dirname(__DIR__, 2) . '/engine/function/twofa2.php';

final class TwoFactorV2Test extends TestCase
{
	protected function setUp(): void
	{
		$GLOBALS['config'] = [
			'session_prefix' => 'znote_',
			'twoFactorV2' => [],
		];
	}

	public function testConfigFallsBackToSafeDefaultsWhenNothingIsSet(): void
	{
		$cfg = \znote2fa_v2_config();

		$this->assertFalse($cfg['enabled']);
		$this->assertTrue($cfg['email_otp_enabled']);
		$this->assertFalse($cfg['force_admins']);
		$this->assertSame(10, $cfg['recovery_codes_count']);
		$this->assertSame(30, $cfg['trusted_device_days']);
	}

	public function testConfigHonoursExplicitValues(): void
	{
		$GLOBALS['config']['twoFactorV2'] = [
			'enabled' => true,
			'email_otp_enabled' => false,
			'force_admins' => true,
			'recovery_codes_count' => 0,
			'trusted_device_days' => -5,
		];

		$cfg = \znote2fa_v2_config();

		$this->assertTrue($cfg['enabled']);
		$this->assertFalse($cfg['email_otp_enabled']);
		$this->assertTrue($cfg['force_admins']);
		$this->assertSame(1, $cfg['recovery_codes_count']);
		$this->assertSame(0, $cfg['trusted_device_days']);
	}

	public function testEnabledMirrorsTheConfig(): void
	{
		$this->assertFalse(\znote2fa_v2_enabled());
		$GLOBALS['config']['twoFactorV2']['enabled'] = true;
		$this->assertTrue(\znote2fa_v2_enabled());
	}

	public function testAdminEnforcementDoesNotCreateAnImpossibleLoginChallenge(): void
	{
		$this->assertFalse(\znote2fa_login_challenge_required(true, ['any_enabled' => false]));
		$this->assertTrue(\znote2fa_login_challenge_required(true, ['any_enabled' => true]));
		$this->assertFalse(\znote2fa_login_challenge_required(false, ['any_enabled' => true]));
	}

	public function testTrustedCookieNameFollowsTheSessionPrefix(): void
	{
		$this->assertSame('znote_2fa_trust', \znote2fa_trusted_cookie_name());

		$GLOBALS['config']['session_prefix'] = 'myserver_';
		$this->assertSame('myserver_2fa_trust', \znote2fa_trusted_cookie_name());
	}

	public function testRecoveryFormatUppercasesAndStripsSeparators(): void
	{
		$this->assertSame('ABCDE12345', \znote2fa_recovery_format('abcde-12345'));
		$this->assertSame('ABCDE12345', \znote2fa_recovery_format(' abcde 12345 '));
	}

	public function testRecoveryFormatRejectsPunctuationOnly(): void
	{
		$this->assertSame('', \znote2fa_recovery_format('---'));
	}

	public function testRecoveryDecodeToleratesMissingOrMalformedStorage(): void
	{
		$this->assertSame([], \znote2fa_recovery_decode(null));
		$this->assertSame([], \znote2fa_recovery_decode(''));
		$this->assertSame([], \znote2fa_recovery_decode('not json'));
		$this->assertSame(['a', 'b'], \znote2fa_recovery_decode('["a","b"]'));
	}

	public function testVerifyLoginInputRejectsEmptyInput(): void
	{
		$this->assertFalse(\znote2fa_verify_login_input(1, ''));
		$this->assertFalse(\znote2fa_verify_login_input(1, '   '));
	}
}
