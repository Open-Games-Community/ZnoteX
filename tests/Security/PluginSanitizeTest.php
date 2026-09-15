<?php

declare(strict_types=1);

namespace ZnoteX\Tests\Security;

use PHPUnit\Framework\TestCase;

final class PluginSanitizeTest extends TestCase
{
	public function testAcceptsAnOrdinaryPluginName(): void
	{
		$this->assertSame('boosted_creatures', \znote_plugin_sanitize('boosted_creatures'));
	}

	public function testLowercasesAndTrims(): void
	{
		$this->assertSame('shop-coupons', \znote_plugin_sanitize('  Shop-Coupons  '));
	}

	/** @dataProvider pathTraversalProvider */
	public function testRejectsPathTraversalAttempts(string $malicious): void
	{
		$this->assertSame('', \znote_plugin_sanitize($malicious));
	}

	public static function pathTraversalProvider(): array
	{
		return [
			'parent directory' => ['../../../etc/passwd'],
			'nested traversal' => ['plugins/../../config.local.php'],
			'absolute unix path' => ['/etc/passwd'],
			'absolute windows path' => ['C:\\Windows\\System32'],
			'null byte' => ["plugin\0.php"],
			'slash' => ['foo/bar'],
			'backslash' => ['foo\\bar'],
			'empty string' => [''],
			'only dots' => ['..'],
			'too long' => [str_repeat('a', 65)],
		];
	}

	public function testExtensionApiRejectsAnInvalidPluginName(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		\ZnoteExtensionApi::plugin('../../../etc/passwd');
	}

	public function testExtensionApiAcceptsAValidPluginName(): void
	{
		$api = \ZnoteExtensionApi::plugin('boosted_creatures');
		$this->assertSame('boosted_creatures', $api->name());
		$this->assertSame('plugin', $api->kind());
	}

	/** @dataProvider relativePathTraversalProvider */
	public function testRelativePathHelperRejectsTraversal(string $malicious): void
	{
		$this->assertSame('', \znote_extension_relative_path($malicious));
	}

	public static function relativePathTraversalProvider(): array
	{
		return [
			'parent directory' => ['../../../etc/passwd'],
			'nested traversal' => ['css/../../config.local.php'],
			'absolute windows path' => ['C:\\Windows\\System32'],
			'null byte' => ["css/style\0.css"],
			'only dots' => ['..'],
			'single dot segment' => ['css/./style.css'],
			'empty string' => [''],
		];
	}

	public function testRelativePathHelperAcceptsAnOrdinaryAssetPath(): void
	{
		$this->assertSame('css/style.css', \znote_extension_relative_path('css/style.css'));
	}

	public function testRelativePathHelperNormalisesBackslashesAndLeadingSlash(): void
	{
		$this->assertSame('css/style.css', \znote_extension_relative_path('\\css\\style.css'));
		$this->assertSame('css/style.css', \znote_extension_relative_path('/css/style.css/'));
	}
}
