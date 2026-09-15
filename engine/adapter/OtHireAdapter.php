<?php
/**
 * otHire: accounts have no `name` column, an account is identified by its
 * numeric id everywhere a TFS-based engine would use a name.
 */
final class OtHireAdapter implements ServerAdapterInterface
{
	public function key(): string {
		return 'OTHIRE';
	}

	public function login(string $username, string $password): int|false {
		return user_login($username, $password);
	}

	public function accountIdentityColumn(): string {
		return 'id';
	}

	public function accountDisplayColumn(): string {
		return '`a`.`id`';
	}

	public function supportsLegacyTwoFactor(): bool {
		return false;
	}

	public function onlineCount(): int {
		return znote_sql_count("SELECT COUNT(*) AS `c` FROM `players` WHERE `online` > 0;");
	}

	public function normalizedEngine(): string {
		return 'OTHIRE';
	}
}
