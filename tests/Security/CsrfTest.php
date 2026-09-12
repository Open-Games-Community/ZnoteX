<?php

declare(strict_types=1);

namespace ZnoteX\Tests\Security;

use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
	protected function setUp(): void
	{
		$_SESSION = [];
		$_POST = [];
	}

	public function testTokenIsGeneratedOnFirstUse(): void
	{
		$this->assertArrayNotHasKey('acp_csrf', $_SESSION);
		$token = \acp_csrf();
		$this->assertNotSame('', $token);
		$this->assertSame($token, $_SESSION['acp_csrf']);
	}

	public function testTokenIsStableAcrossCalls(): void
	{
		$first = \acp_csrf();
		$second = \acp_csrf();
		$this->assertSame($first, $second);
	}

	public function testFieldEmbedsTheCurrentToken(): void
	{
		$token = \acp_csrf();
		$field = \acp_csrf_field();
		$this->assertStringContainsString('name="csrf_token"', $field);
		$this->assertStringContainsString(htmlspecialchars($token, ENT_QUOTES, 'UTF-8'), $field);
	}

	public function testVerifyRejectsMissingToken(): void
	{
		\acp_csrf();
		$_POST = [];
		$this->assertFalse(\acp_verify_csrf());
	}

	public function testVerifyRejectsWrongToken(): void
	{
		\acp_csrf();
		$_POST['csrf_token'] = 'attacker-supplied-value';
		$this->assertFalse(\acp_verify_csrf());
	}

	public function testVerifyRejectsNonStringToken(): void
	{
		\acp_csrf();
		$_POST['csrf_token'] = ['not', 'a', 'string'];
		$this->assertFalse(\acp_verify_csrf());
	}

	public function testVerifyAcceptsTheRealToken(): void
	{
		$token = \acp_csrf();
		$_POST['csrf_token'] = $token;
		$this->assertTrue(\acp_verify_csrf());
	}

	public function testEachSessionGetsAnIndependentToken(): void
	{
		$tokenA = \acp_csrf();
		$_SESSION = [];
		$tokenB = \acp_csrf();
		$this->assertNotSame($tokenA, $tokenB);
	}
}
