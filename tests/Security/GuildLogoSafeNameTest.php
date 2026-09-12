<?php

declare(strict_types=1);

namespace ZnoteX\Tests\Security;

use PHPUnit\Framework\TestCase;

final class GuildLogoSafeNameTest extends TestCase
{
	public function testAcceptsAnOrdinaryGuildName(): void
	{
		$this->assertSame('Knights of ZnoteX', \guild_logo_safe_name('Knights of ZnoteX'));
	}

	public function testTrimsWhitespace(): void
	{
		$this->assertSame('Guardians', \guild_logo_safe_name('  Guardians  '));
	}

	public function testRejectsEmptyName(): void
	{
		$this->assertNull(\guild_logo_safe_name(''));
		$this->assertNull(\guild_logo_safe_name('   '));
	}

	public function testRejectsNameLongerThanSixtyCharacters(): void
	{
		$this->assertNull(\guild_logo_safe_name(str_repeat('a', 61)));
		$this->assertNotNull(\guild_logo_safe_name(str_repeat('a', 60)));
	}

	/** @dataProvider pathTraversalProvider */
	public function testRejectsPathTraversalAttempts(string $malicious): void
	{
		$this->assertNull(\guild_logo_safe_name($malicious));
	}

	public static function pathTraversalProvider(): array
	{
		return [
			'parent directory' => ['../../../etc/passwd'],
			'dot dot in the middle' => ['guild..name'],
			'forward slash' => ['guild/name'],
			'backslash' => ['guild\\name'],
			'null byte' => ["guild\0name"],
			'windows traversal' => ['..\\..\\config.local.php'],
		];
	}
}
