<?php

declare(strict_types=1);

namespace ZnoteX\Tests\Security;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/engine/adapter/ServerAdapterInterface.php';
require_once dirname(__DIR__, 2) . '/engine/adapter/TFSAdapter.php';
require_once dirname(__DIR__, 2) . '/engine/adapter/CanaryAdapter.php';
require_once dirname(__DIR__, 2) . '/engine/adapter/OtHireAdapter.php';
require_once dirname(__DIR__, 2) . '/engine/adapter/BlackTekAdapter.php';
require_once dirname(__DIR__, 2) . '/engine/adapter/factory.php';

final class ServerAdapterTest extends TestCase
{
	protected function setUp(): void
	{
		$GLOBALS['config'] = [
			'ServerEngine' => 'TFS_10',
			'ServerEngineReal' => 'TFS_10',
		];
	}

	/** @dataProvider engineProvider */
	public function testFactoryPicksTheRightAdapterClass(string $engine, string $expectedClass): void
	{
		$adapter = \znote_server_adapter($engine);
		$this->assertInstanceOf($expectedClass, $adapter);
		$this->assertSame($engine, $adapter->key());
	}

	public static function engineProvider(): array
	{
		return [
			'TFS_02' => ['TFS_02', \TFSAdapter::class],
			'TFS_03' => ['TFS_03', \TFSAdapter::class],
			'TFS_10' => ['TFS_10', \TFSAdapter::class],
			'TFS_16' => ['TFS_16', \TFSAdapter::class],
			'OTHIRE' => ['OTHIRE', \OtHireAdapter::class],
			'CANARY' => ['CANARY', \CanaryAdapter::class],
			'BLACKTEK' => ['BLACKTEK', \BlackTekAdapter::class],
		];
	}

	public function testFactoryFallsBackToGlobalConfigWhenNoEngineIsGiven(): void
	{
		$GLOBALS['config']['ServerEngineReal'] = 'OTHIRE';
		$adapter = \znote_server_adapter();
		$this->assertInstanceOf(\OtHireAdapter::class, $adapter);
	}

	public function testFactoryCachesOneInstancePerEngine(): void
	{
		$first = \znote_server_adapter('TFS_10');
		$second = \znote_server_adapter('TFS_10');
		$this->assertSame($first, $second);
	}

	public function testLegacyTwoFactorSupportMatchesWhichEnginesHaveAccountsSecret(): void
	{
		// TFS_10, TFS_16 (normalised to TFS_10) and BlackTek all ship an
		// accounts.secret column; TFS_02, TFS_03, Canary and otHire do not.
		$this->assertTrue((new \TFSAdapter('TFS_10'))->supportsLegacyTwoFactor());
		$this->assertFalse((new \TFSAdapter('TFS_02'))->supportsLegacyTwoFactor());
		$this->assertFalse((new \TFSAdapter('TFS_03'))->supportsLegacyTwoFactor());
		$this->assertFalse((new \TFSAdapter('TFS_16'))->supportsLegacyTwoFactor());
		$this->assertFalse((new \CanaryAdapter())->supportsLegacyTwoFactor());
		$this->assertFalse((new \OtHireAdapter())->supportsLegacyTwoFactor());
		$this->assertTrue((new \BlackTekAdapter())->supportsLegacyTwoFactor());
	}

	public function testOthireIdentifiesAccountsByIdNotName(): void
	{
		$adapter = new \OtHireAdapter();
		$this->assertSame('id', $adapter->accountIdentityColumn());
		$this->assertSame('`a`.`id`', $adapter->accountDisplayColumn());
	}

	/** @dataProvider normalizedEngineProvider */
	public function testNormalizedEngineMatchesTheSchemaAnAdapterActuallyRuns(string $realEngine, string $expected): void
	{
		$this->assertSame($expected, \znote_server_adapter($realEngine)->normalizedEngine());
	}

	public static function normalizedEngineProvider(): array
	{
		return [
			'TFS_02 stays TFS_02' => ['TFS_02', 'TFS_02'],
			'TFS_03 stays TFS_03' => ['TFS_03', 'TFS_03'],
			'TFS_10 stays TFS_10' => ['TFS_10', 'TFS_10'],
			'TFS_16 normalises to TFS_10' => ['TFS_16', 'TFS_10'],
			'Canary normalises to TFS_10' => ['CANARY', 'TFS_10'],
			'BlackTek normalises to TFS_10' => ['BLACKTEK', 'TFS_10'],
			'otHire stays OTHIRE' => ['OTHIRE', 'OTHIRE'],
		];
	}

	public function testEveryOtherAdapterIdentifiesAccountsByName(): void
	{
		foreach ([new \TFSAdapter('TFS_10'), new \CanaryAdapter(), new \BlackTekAdapter()] as $adapter) {
			$this->assertSame('name', $adapter->accountIdentityColumn());
			$this->assertSame('`a`.`name`', $adapter->accountDisplayColumn());
		}
	}
}
