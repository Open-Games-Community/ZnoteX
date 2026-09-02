<?php
/**
 * Step 6 - write the configuration and lock the installer.
 *
 * The generated values go to config.local.php, which config.php includes at
 * the end. config.php is never rewritten: it stays the documented default file
 * you can update with a new ZnoteX release without losing your settings, and
 * a 1000-line file edited by regular expression is a file waiting to break.
 */

if (!defined('ZNOTE_INSTALL')) { http_response_code(403); die('Direct access denied.'); }

/**
 * The file we are about to write, also shown for manual copying.
 *
 * $includeAdmin is false when page_admin_access is being written straight into
 * config.php instead - it must appear in exactly one of the two files, since
 * config.local.php is included last and would otherwise win.
 */
function install_config_contents(bool $includeAdmin = true): string {
	$quote = static function (string $value): string {
		return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), $value) . "'";
	};

	$admin = (string)install_get('admin_character');
	$nl    = "\n";

	$out  = '<?php' . $nl;
	$out .= '/**' . $nl;
	$out .= ' * Written by the ZnoteX installer.' . $nl;
	$out .= ' *' . $nl;
	$out .= ' * config.php is included first and holds every default; this file overrides the' . $nl;
	$out .= ' * handful of values specific to this install. Updating ZnoteX therefore means' . $nl;
	$out .= ' * replacing config.php and leaving this file alone.' . $nl;
	$out .= ' *' . $nl;
	$out .= ' * Most other settings are editable from Admin Panel > Settings.' . $nl;
	$out .= ' */' . $nl . $nl;

	$out .= '$config[\'sqlHost\']     = ' . $quote((string)install_get('sqlHost')) . ';' . $nl;
	$out .= '$config[\'sqlUser\']     = ' . $quote((string)install_get('sqlUser')) . ';' . $nl;
	$out .= '$config[\'sqlPassword\'] = ' . $quote((string)install_get('sqlPassword')) . ';' . $nl;
	$out .= '$config[\'sqlDatabase\'] = ' . $quote((string)install_get('sqlDatabase')) . ';' . $nl . $nl;

	$out .= '$config[\'ServerEngine\'] = ' . $quote((string)install_get('ServerEngine', 'TFS_10')) . ';' . $nl;
	$out .= '$config[\'site_title\']   = ' . $quote((string)install_get('site_title')) . ';' . $nl;
	$out .= '$config[\'site_url\']     = ' . $quote((string)install_get('site_url')) . ';' . $nl;

	$path = (string)install_get('server_path');
	if ($path !== '') {
		$out .= '$config[\'server_path\']  = ' . $quote($path) . ';' . $nl;
	}

	if ($includeAdmin) {
		$out .= $nl . '// Admin access is granted by character name.' . $nl;
		$out .= '$config[\'page_admin_access\'] = array(' . $nl;
		$out .= "\t" . $quote($admin) . ',' . $nl;
		$out .= ');' . $nl;
	} else {
		$out .= $nl . '// page_admin_access was written straight into config.php instead.' . $nl;
	}

	return $out;
}

$written    = false;
$writeErr   = '';
$adminNote  = '';
$adminInMain = ($_SERVER['REQUEST_METHOD'] === 'POST')
	? !empty($_POST['admin_in_config'])
	: false;

$contents = install_config_contents(!$adminInMain);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	// Do this first: if it fails we fall back to config.local.php rather than
	// finishing with an admin who cannot reach the panel.
	if ($adminInMain) {
		$result = install_write_admin_to_config((string)install_get('admin_character'));
		if ($result !== '') {
			$adminNote   = $result . ' The admin name went to config.local.php instead.';
			$adminInMain = false;
			$contents    = install_config_contents(true);
		} else {
			$adminNote = 'The admin name was written into config.php, and config.php.bak keeps the previous version.';
		}
	}

	$target = install_config_file();

	if (@file_put_contents($target, $contents) === false) {
		$writeErr = 'Could not write ' . $target . '. Create it by hand with the contents below.';
	} else {
		$written = true;

		// The lock. Written last, so a failed write above does not lock a site
		// that is not actually configured.
		@file_put_contents(install_lock_file(),
			"Installed on " . date('Y-m-d H:i:s') . "\n"
			. "Delete this file only if you mean to run the installer again.\n");

		install_reset();
	}
}
?>
<h1><?= $written ? 'Installed' : 'Finish' ?></h1>

<?php if ($written): ?>

	<p class="good">
		ZnoteX is installed. <code>config.local.php</code> is written and the installer is locked.
	</p>

	<?php if ($adminNote !== ''): ?>
		<p class="info"><?= ih($adminNote) ?></p>
	<?php endif; ?>

	<h2>Two things left</h2>
	<ul class="checks">
		<li>
			<span class="state opt">1</span>
			<span class="what">
				Delete the <code>install/</code> folder
				<span class="detail">It refuses to run now, but there is no reason to leave it on a public server.</span>
			</span>
		</li>
		<li>
			<span class="state opt">2</span>
			<span class="what">
				Log in and look around
				<span class="detail">Admin Panel &rarr; Settings covers almost everything you would otherwise edit by hand.</span>
			</span>
		</li>
	</ul>

	<div class="actions">
		<a class="btn" href="../index.php">Open the site</a>
		<a class="btn ghost" href="../admin/index.php">Admin panel</a>
	</div>

<?php else: ?>

	<p class="lead">Review what will be written, then finish.</p>

	<table>
		<tr><th>Setting</th><th>Value</th></tr>
		<tr><td>Database</td><td><code><?= ih(install_get('sqlUser')) ?>@<?= ih(install_get('sqlHost')) ?> / <?= ih(install_get('sqlDatabase')) ?></code></td></tr>
		<tr><td>Server engine</td><td><code><?= ih(install_get('ServerEngine')) ?></code></td></tr>
		<tr><td>Site name</td><td><?= ih(install_get('site_title')) ?></td></tr>
		<tr><td>Site URL</td><td><?= ih(install_get('site_url')) ?></td></tr>
		<tr><td>Administrator</td><td><?= ih(install_get('admin_character')) ?> <span class="detail">(character name)</span></td></tr>
	</table>

	<?php if ($writeErr !== ''): ?>
		<p class="bad"><?= ih($writeErr) ?></p>
		<h2>Create config.local.php yourself</h2>
		<p>Put this in the ZnoteX root, next to <code>config.php</code>:</p>
		<pre><code><?= ih($contents) ?></code></pre>
	<?php endif; ?>

	<div class="info">
		These go to <code>config.local.php</code>. Your <code>config.php</code> is left untouched,
		so a future ZnoteX update can replace it without losing anything.
	</div>

	<form method="post">
		<div class="field">
			<label style="display:flex;align-items:flex-start;gap:9px;font-weight:400;">
				<input type="checkbox" name="admin_in_config" value="1" style="margin-top:4px;width:auto;">
				<span>
					Put the admin name in <code>config.php</code> instead of <code>config.local.php</code>
					<span class="detail" style="display:block;color:var(--muted);font-size:12.5px;">
						Only the <code>page_admin_access</code> array is touched, a <code>.bak</code> is kept,
						and the result is syntax-checked before saving. Be aware that a future ZnoteX update
						replacing <code>config.php</code> would take the name with it and lock you out of the
						panel &mdash; which is exactly what <code>config.local.php</code> avoids.
					</span>
				</span>
			</label>
		</div>

		<div class="actions">
			<button class="btn green" type="submit">Write the config and finish</button>
			<a class="btn ghost" href="<?= install_url(5) ?>">Back</a>
		</div>
	</form>

<?php endif; ?>
