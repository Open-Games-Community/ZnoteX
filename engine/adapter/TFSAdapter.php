<?php
/**
 * TFS_02, TFS_03, TFS_10 and TFS_16 (TFS_16 already runs the TFS_10 schema,
 * normalised in engine/init.php). TFS_03's salted password scheme is the one
 * real behavioural difference among them.
 */
final class TFSAdapter implements ServerAdapterInterface
{
	public function __construct(private string $realEngine) {
	}

	public function key(): string {
		return $this->realEngine;
	}

	public function login(string $username, string $password): int|false {
		if ($this->realEngine === 'TFS_03') {
			return user_login_03($username, $password);
		}
		return user_login($username, $password);
	}

	public function accountIdentityColumn(): string {
		return 'name';
	}

	public function accountDisplayColumn(): string {
		return '`a`.`name`';
	}

	public function supportsLegacyTwoFactor(): bool {
		// accounts.secret and the QR-code flow in twofa.php only exist from TFS 1.2.
		return $this->realEngine === 'TFS_10';
	}

	public function onlineCount(): int {
		return $this->realEngine === 'TFS_10'
			? znote_sql_count("SELECT COUNT(*) AS `c` FROM `players_online`;")
			: znote_sql_count("SELECT COUNT(*) AS `c` FROM `players` WHERE `online` > 0;");
	}

	public function normalizedEngine(): string {
		// TFS_16 already runs the TFS_10 schema (normalised in engine/init.php).
		return $this->realEngine === 'TFS_16' ? 'TFS_10' : $this->realEngine;
	}
}
