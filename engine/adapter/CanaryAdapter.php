<?php
/**
 * Canary. Normalised to the TFS_10 schema for querying (see engine/init.php),
 * but it is not TFS: the legacy accounts.secret 2FA flow does not apply, which
 * is why engine/init.php also forces twoFactorAuthenticator off for it.
 */
final class CanaryAdapter implements ServerAdapterInterface
{
	public function key(): string {
		return 'CANARY';
	}

	public function login(string $username, string $password): int|false {
		return user_login($username, $password);
	}

	public function accountIdentityColumn(): string {
		return 'name';
	}

	public function accountDisplayColumn(): string {
		return '`a`.`name`';
	}

	public function supportsLegacyTwoFactor(): bool {
		return false;
	}

	public function onlineCount(): int {
		return znote_sql_count("SELECT COUNT(*) AS `c` FROM `players_online`;");
	}

	public function normalizedEngine(): string {
		return 'TFS_10';
	}
}
