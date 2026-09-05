<?php
/**
 * Admin action log.
 *
 * Records mutating actions taken from the admin panel - bans, skill edits,
 * points, settings changes, plugin lifecycle, etc - into `znote_admin_log`.
 * Admin modules call acp_log() right after a successful write; the table is
 * migrated separately (SQL/migrations/2.0.0_admin_log.sql), so acp_log()
 * silently no-ops on databases that have not run it yet, matching the
 * fallback pattern used by theme_menu_items() and friends.
 */

function acp_log_table_exists(): bool {
	return znote_table_exists('znote_admin_log');
}

/**
 * @param string $action  Dotted code, e.g. "player.ban" - see acp_log_action_label().
 * @param string $target  What the action was applied to, e.g. a character name.
 * @param array  $details Small set of extra facts (old/new values, reason...),
 *                         stored as JSON and shown expanded in the log viewer.
 */
function acp_log(string $action, string $target = '', array $details = array()): bool {
	if (!acp_log_table_exists()) {
		return false;
	}

	$adminId   = isset($GLOBALS['session_user_id']) ? (int)$GLOBALS['session_user_id'] : 0;
	$adminName = isset($GLOBALS['user_data']['name']) ? (string)$GLOBALS['user_data']['name'] : '';

	$detailsJson = '';
	if ($details) {
		$encoded = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if ($encoded !== false) {
			$detailsJson = $encoded;
		}
	}

	return (bool)mysql_insert("
		INSERT INTO `znote_admin_log` (`admin_id`, `admin_name`, `action`, `target`, `details`, `ip`, `created`)
		VALUES (
			" . $adminId . ",
			'" . mysql_znote_escape_string($adminName) . "',
			'" . mysql_znote_escape_string(substr($action, 0, 64)) . "',
			'" . mysql_znote_escape_string(substr($target, 0, 191)) . "',
			'" . mysql_znote_escape_string($detailsJson) . "',
			'" . mysql_znote_escape_string(getIP()) . "',
			" . time() . "
		);
	");
}
