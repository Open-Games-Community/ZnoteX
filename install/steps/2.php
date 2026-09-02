<?php
/**
 * Step 2 - database.
 *
 * This is where the installer refuses if the OT server's own schema is not
 * there. ZnoteX reads `accounts` and `players`; it has never created them and
 * will not pretend to. Importing TFS/Canary's schema.sql is the server owner's
 * job, and doing it after ZnoteX would overwrite what we are about to write.
 */

if (!defined('ZNOTE_INSTALL')) { http_response_code(403); die('Direct access denied.'); }

$tested  = false;
$tables  = null;
$connect = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	install_state(array(
		'sqlHost'     => trim((string)($_POST['sqlHost'] ?? '127.0.0.1')),
		'sqlUser'     => trim((string)($_POST['sqlUser'] ?? '')),
		'sqlPassword' => (string)($_POST['sqlPassword'] ?? ''),
		'sqlDatabase' => trim((string)($_POST['sqlDatabase'] ?? '')),
	));

	$tested = true;
	$link   = install_connect($connect);

	if ($link === null) {
		install_error('Could not connect: ' . ih((string)$connect));
	} else {
		$tables = install_server_tables($link);
		$link->close();

		if (!$tables['ok']) {
			install_error(
				'Connected, but this database has no <code>accounts</code> / <code>players</code> tables. '
				. 'Import your server\'s own <code>schema.sql</code> first, then come back.'
			);
		} elseif (!empty($_POST['continue'])) {
			install_max_step(3);
			header('Location: ' . install_url(3));
			exit;
		}
	}
}

// Pre-fill from config.php so a normal install is mostly clicking Next.
$saved = install_saved_config();
$value = static function (string $key, string $fallback = '') use ($saved) {
	$fromState = install_get($key, null);
	if ($fromState !== null && $fromState !== '') {
		return (string)$fromState;
	}
	return (string)($saved[$key] ?? $fallback);
};
?>
<h1>Database</h1>
<p class="lead">
	The same database your Open Tibia server uses. ZnoteX adds its own
	<code>znote_*</code> tables beside the server's.
</p>

<form method="post">
	<div class="row">
		<div class="field">
			<label class="lbl" for="sqlHost">Host</label>
			<input type="text" id="sqlHost" name="sqlHost" value="<?= ih($value('sqlHost', '127.0.0.1')) ?>" required>
		</div>
		<div class="field">
			<label class="lbl" for="sqlDatabase">Database</label>
			<input type="text" id="sqlDatabase" name="sqlDatabase" value="<?= ih($value('sqlDatabase')) ?>" required>
		</div>
	</div>

	<div class="row">
		<div class="field">
			<label class="lbl" for="sqlUser">User</label>
			<input type="text" id="sqlUser" name="sqlUser" value="<?= ih($value('sqlUser')) ?>" required>
			<p class="hint">Do not use <code>root</code> on a public server.</p>
		</div>
		<div class="field">
			<label class="lbl" for="sqlPassword">Password</label>
			<input type="password" id="sqlPassword" name="sqlPassword" value="<?= ih($value('sqlPassword')) ?>">
		</div>
	</div>

	<?php if ($tables !== null): ?>
		<h2>Server schema</h2>

		<?php if ($tables['ok']): ?>
			<p class="good">Connected, and your server's tables are there.</p>
		<?php else: ?>
			<div class="warn">
				<strong>The server schema is missing.</strong><br>
				ZnoteX reads the tables your OT server creates &mdash; it does not create them, and
				importing them afterwards would wipe what ZnoteX writes. Import your server's
				<code>schema.sql</code> into <code><?= ih(install_get('sqlDatabase')) ?></code>, then
				press Test again.
			</div>
		<?php endif; ?>

		<table>
			<tr><th>Table</th><th>Status</th><th></th></tr>
			<?php foreach ($tables['required'] as $table => $found): ?>
				<tr>
					<td><code><?= ih($table) ?></code></td>
					<td class="<?= $found ? 'ok' : 'no' ?>"><?= $found ? '&#10003; present' : '&#10007; missing' ?></td>
					<td>required</td>
				</tr>
			<?php endforeach; ?>
			<?php foreach ($tables['optional'] as $table => $found): ?>
				<tr>
					<td><code><?= ih($table) ?></code></td>
					<td><?= $found ? '&#10003; present' : '&mdash;' ?></td>
					<td>optional &mdash; some pages need it</td>
				</tr>
			<?php endforeach; ?>
		</table>

		<?php if (!empty($tables['znote_installed'])): ?>
			<p class="warn">
				A <code>znote</code> table already exists. The next step will not touch tables that
				are already there, so nothing you have will be lost.
			</p>
		<?php endif; ?>
	<?php endif; ?>

	<div class="actions">
		<button class="btn ghost" type="submit" name="test" value="1">Test connection</button>
		<?php if ($tables !== null && $tables['ok']): ?>
			<button class="btn" type="submit" name="continue" value="1">Continue</button>
		<?php endif; ?>
		<a class="btn ghost" href="<?= install_url(1) ?>">Back</a>
	</div>
</form>
