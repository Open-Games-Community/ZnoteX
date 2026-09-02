<?php
/** Step 3 - which server this site sits in front of, and how it is named. */

if (!defined('ZNOTE_INSTALL')) { http_response_code(403); die('Direct access denied.'); }

$engines = array(
	'TFS_10' => 'TFS 1.1 - 1.4.2',
	'TFS_16' => 'TFS 1.6',
	'CANARY' => 'Canary / OTServBR-Global',
	'TFS_03' => 'TFS 0.3.6+ / 0.4 / OTX',
	'TFS_02' => 'TFS 0.2.13+',
	'OTHIRE' => 'OTHire',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$engine = (string)($_POST['ServerEngine'] ?? '');
	$title  = trim((string)($_POST['site_title'] ?? ''));
	$url    = rtrim(trim((string)($_POST['site_url'] ?? '')), '/');

	if (!isset($engines[$engine])) {
		install_error('Pick a server engine.');
	} elseif ($title === '') {
		install_error('The site needs a name.');
	} else {
		install_state(array(
			'ServerEngine' => $engine,
			'site_title'   => $title,
			'site_url'     => $url,
			'server_path'  => rtrim(trim((string)($_POST['server_path'] ?? '')), '/\\'),
		));
		install_max_step(4);
		header('Location: ' . install_url(4));
		exit;
	}
}

// A sensible default URL, from the request itself.
$guessUrl = install_get('site_url');
if ($guessUrl === '') {
	$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	$host     = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
	$base     = rtrim(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')), '/\\');
	$base     = preg_replace('#/install$#', '', $base);
	$guessUrl = $scheme . '://' . $host . $base;
}
?>
<h1>Server</h1>
<p class="lead">Which engine you run, and how the site introduces itself.</p>

<form method="post">
	<div class="field">
		<label class="lbl" for="ServerEngine">Server engine</label>
		<select id="ServerEngine" name="ServerEngine">
			<?php foreach ($engines as $key => $label): ?>
				<option value="<?= ih($key) ?>" <?= install_get('ServerEngine', 'TFS_10') === $key ? 'selected' : '' ?>>
					<?= ih($label) ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="hint">
			TFS 1.0 is not supported. TFS 1.6 and Canary differ from 1.x only in a few column
			names, which ZnoteX handles for you.
		</p>
	</div>

	<div class="row">
		<div class="field">
			<label class="lbl" for="site_title">Site name</label>
			<input type="text" id="site_title" name="site_title"
				   value="<?= ih(install_get('site_title', 'My Open Tibia Server')) ?>" required>
		</div>
		<div class="field">
			<label class="lbl" for="site_url">Site URL</label>
			<input type="text" id="site_url" name="site_url" value="<?= ih($guessUrl) ?>">
			<p class="hint">Used in e-mails. No trailing slash.</p>
		</div>
	</div>

	<div class="field">
		<label class="lbl" for="server_path">Server folder <em>(optional)</em></label>
		<input type="text" id="server_path" name="server_path"
			   value="<?= ih(install_get('server_path')) ?>"
			   placeholder="C:/forgottenserver  or  /home/ots">
		<p class="hint">
			Where <code>data/</code> and <code>config.lua</code> live. Needed by the creature
			library, the spell list and the server info page. You can set it later.
		</p>
	</div>

	<div class="actions">
		<button class="btn" type="submit">Continue</button>
		<a class="btn ghost" href="<?= install_url(2) ?>">Back</a>
	</div>
</form>
