<?php
/**
 * BlackTek (https://github.com/Black-Tek/BlackTek-Server): a TFS fork whose
 * schema.sql - checked against its `master` branch - matches TFS_10 in every
 * way that matters here: `accounts` has `name` and a `secret` char(16) column,
 * `players_online` exists, `houses` uses the TFS column names (not Canary's
 * renamed internal_bid/bid_end_date/...), and both `guild_membership` and
 * `guild_ranks` exist. So it is normalised to TFS_10 for querying (see
 * engine/init.php) and the legacy accounts.secret 2FA flow works unmodified.
 */
final class BlackTekAdapter implements ServerAdapterInterface
{
	public function key(): string {
		return 'BLACKTEK';
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
		return true;
	}

	public function onlineCount(): int {
		return znote_sql_count("SELECT COUNT(*) AS `c` FROM `players_online`;");
	}

	public function normalizedEngine(): string {
		return 'TFS_10';
	}
}
