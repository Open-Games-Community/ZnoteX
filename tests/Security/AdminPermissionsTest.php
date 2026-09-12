<?php

declare(strict_types=1);

namespace ZnoteX\Tests\Security;

use PHPUnit\Framework\TestCase;

final class AdminPermissionsTest extends TestCase
{
	protected function setUp(): void
	{
		$GLOBALS['config'] = [
			'ServerEngine' => 'TFS_10',
			'page_admin_access' => ['OwnerAccount'],
			'page_admin_roles' => [
				'ModeratorAccount' => ['moderator'],
				'SupportAccount' => 'support',
			],
		];
	}

	public function testOwnerNameGrantsTheOwnerRole(): void
	{
		$roles = \admin_roles(['name' => 'OwnerAccount']);
		$this->assertSame(['owner'], $roles);
	}

	public function testAssignedRoleIsHonoured(): void
	{
		$roles = \admin_roles(['name' => 'ModeratorAccount']);
		$this->assertSame(['moderator'], $roles);
	}

	public function testASingleAssignedRoleStringIsNormalisedToAnArray(): void
	{
		$roles = \admin_roles(['name' => 'SupportAccount']);
		$this->assertSame(['support'], $roles);
	}

	public function testUnknownAccountGetsNoRoles(): void
	{
		$roles = \admin_roles(['name' => 'SomeoneElse']);
		$this->assertSame([], $roles);
	}

	public function testNonArrayUserDataGetsNoRoles(): void
	{
		$this->assertSame([], \admin_roles(null));
		$this->assertSame([], \admin_roles(false));
	}

	public function testOthireIdentityIsTheAccountIdNotTheName(): void
	{
		$GLOBALS['config']['ServerEngine'] = 'OTHIRE';
		$GLOBALS['config']['page_admin_access'] = [42];

		$this->assertSame(['owner'], \admin_roles(['id' => 42, 'name' => 'OwnerAccount']));
		$this->assertSame([], \admin_roles(['id' => 99, 'name' => 'OwnerAccount']));
	}

	public function testOwnerCanReachEveryModule(): void
	{
		$this->assertTrue(\acp_can_module('update', ['name' => 'OwnerAccount']));
		$this->assertTrue(\acp_can_module('settings', ['name' => 'OwnerAccount']));
	}

	public function testRoleOnlyReachesItsOwnModules(): void
	{
		$this->assertTrue(\acp_can_module('gallery', ['name' => 'ModeratorAccount']));
		$this->assertFalse(\acp_can_module('accounts', ['name' => 'ModeratorAccount']));
	}

	public function testModuleWithNoRoleMappingIsOwnerOnly(): void
	{
		// 'update' intentionally has no entry in acp_module_roles(), so only
		// the owner bypass in acp_can_module() may reach it - not any role.
		$this->assertFalse(\acp_can_module('update', ['name' => 'ModeratorAccount']));
		$this->assertFalse(\acp_can_module('update', ['name' => 'SupportAccount']));
	}

	public function testAnonymousVisitorHasNoAccess(): void
	{
		$this->assertFalse(\acp_can_module('dashboard', null));
	}
}
