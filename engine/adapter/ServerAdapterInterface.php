<?php
/**
 * Server Adapter.
 *
 * Every place that used to branch on $config['ServerEngine'] directly is a
 * place that has to be found and re-checked whenever a new engine shows up.
 * This interface collects the differences that actually matter to the
 * website - how an account logs in, how it is identified, whether the legacy
 * in-game 2FA exists, how "online" is counted - behind one call:
 * znote_server_adapter().
 *
 * This does not replace every scattered ServerEngine check in one pass - that
 * would be the highest-risk change in the codebase, done all at once, with no
 * way to review it in reviewable pieces. It gives new code, and code being
 * touched anyway, somewhere better to live. login.php and the admin
 * dashboard/analytics modules are wired to it as the first, carefully tested
 * examples; the rest can move over one file at a time.
 */

/** COUNT(*) helper shared by the adapters - 0 for a table this engine does not have. */
function znote_sql_count(string $sql, array $params = array()): int {
	$row = db()->fetchOne($sql, $params);
	return is_array($row) && $row ? (int)reset($row) : 0;
}

interface ServerAdapterInterface
{
	/** The ServerEngineReal value this adapter was built for. */
	public function key(): string;

	/**
	 * Authenticates a username/password pair against `accounts`.
	 * Returns the account id, or false.
	 */
	public function login(string $username, string $password): int|false;

	/** The `accounts` column identity is checked against - 'name' everywhere except otHire. */
	public function accountIdentityColumn(): string;

	/** SQL fragment for a human-readable account label in a query - `a`.`name` or `a`.`id`. */
	public function accountDisplayColumn(): string;

	/** Whether the legacy, engine-tied 2FA (accounts.secret, twofa.php) can work here. */
	public function supportsLegacyTwoFactor(): bool;

	/** How many characters are online right now. */
	public function onlineCount(): int;

	/**
	 * The engine value the rest of the codebase should dispatch on for schema
	 * differences: one of 'TFS_02', 'TFS_03', 'TFS_10' or 'OTHIRE'. Canary,
	 * TFS_16 and BlackTek all report 'TFS_10' here - they already run that
	 * schema, which is exactly what $config['ServerEngine'] was normalised to
	 * in engine/init.php. This is the single place that mapping lives now,
	 * instead of every file re-deriving it.
	 */
	public function normalizedEngine(): string;
}
