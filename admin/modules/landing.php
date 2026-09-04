<?php
/**
 * Title: Landing Page
 * Icon: fa-flag
 * Group: Content
 * Order: 8
 * Description: Show a standalone page from landing/ instead of the front page.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$enabled = empty($_POST['enabled']) ? '' : '1';
	$file    = trim((string)($_POST['file'] ?? ''));

	if ($file !== '' && !landing_is_valid_file($file)) {
		acp_flash_error('That file name is not allowed. Use a single .html or .php file inside landing/.');
		acp_redirect('landing');
	}

	if ($enabled === '1') {
		if (!is_dir(landing_root())) {
			acp_flash_error('There is no <code>landing/</code> folder yet. Create it and put your page inside.');
			acp_redirect('landing');
		}
		if ($file === '' || !is_file(landing_root() . '/' . $file)) {
			acp_flash_error('Pick a page that exists in <code>landing/</code> before switching this on.');
			acp_redirect('landing');
		}
	}

	setting_set('landing.enabled', $enabled);
	setting_set('landing.file', $file);

	acp_flash_success($enabled === '1'
		? 'Landing page is live. Visitors now see <code>landing/' . h($file) . '</code>.'
		: 'Landing page is off. The front page is back to normal.');

	acp_redirect('landing');
}

$files     = landing_available_files();
$hasFolder = is_dir(landing_root());
$enabled   = landing_enabled();
$current   = landing_file();
$live      = landing_ready();
?>

<div class="acp-toolbar">
	<div>
		<?php if ($live): ?>
			<span class="acp-pill acp-pill--green">Landing page is live</span>
		<?php elseif ($enabled): ?>
			<span class="acp-pill acp-pill--red">Switched on, but the file is missing</span>
		<?php else: ?>
			<span class="acp-pill acp-pill--grey">Off &mdash; the normal front page is shown</span>
		<?php endif; ?>
	</div>
	<div class="acp-actions is-tight">
		<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_site()) ?>" target="_blank" rel="noopener">
			<i class="fa fa-external-link"></i> Open the site
		</a>
		<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_site('index.php?site=1')) ?>" target="_blank" rel="noopener">
			<i class="fa fa-sign-in"></i> Skip the landing page
		</a>
	</div>
</div>

<div class="acp-grid acp-grid--2">

	<section class="acp-card">
		<header class="acp-card-head">
			<h2>Landing page</h2>
			<p>A page of your own at the site root, before visitors reach the community site.</p>
		</header>
		<div class="acp-card-body">

			<?php if (!$hasFolder): ?>
				<div class="acp-flash acp-flash--error">
					<i class="fa fa-exclamation-triangle"></i>
					<span>No <code>landing/</code> folder. Create it at the root of ZnoteX and put your
					<code>index.html</code> (or <code>index.php</code>) and its css/, img/ and js/ inside.</span>
				</div>
			<?php elseif (!$files): ?>
				<div class="acp-flash acp-flash--error">
					<i class="fa fa-exclamation-triangle"></i>
					<span><code>landing/</code> exists but holds no <code>.html</code> or <code>.php</code> page.</span>
				</div>
			<?php endif; ?>

			<form method="post">
				<?= acp_csrf_field() ?>

				<div class="acp-field">
					<label class="acp-label" for="file">Page to show</label>
					<select class="acp-select" id="file" name="file" <?= $files ? '' : 'disabled' ?>>
						<?php if (!$files): ?>
							<option value="">Nothing in landing/ yet</option>
						<?php endif; ?>
						<?php foreach ($files as $name): ?>
							<option value="<?= h($name) ?>" <?= $name === $current ? 'selected' : '' ?>>
								<?= h('landing/' . $name) ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="acp-field">
					<label style="display:flex;align-items:center;gap:8px;font-weight:400;">
						<input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
						<span>Show this page at the site root</span>
					</label>
				</div>

				<div class="acp-actions">
					<button class="acp-btn acp-btn--green" type="submit">
						<i class="fa fa-check"></i> Save
					</button>
				</div>
			</form>
		</div>
	</section>

	<section class="acp-card">
		<header class="acp-card-head">
			<h2>How it behaves</h2>
			<p>What visitors get, and how they reach the real site.</p>
		</header>
		<div class="acp-card-body">
			<table class="acp-table">
				<tbody>
					<tr>
						<td>Folder</td>
						<td><code><?= h(ZNOTE_LANDING_DIR) ?>/</code> <?= $hasFolder ? '' : '<em>(missing)</em>' ?></td>
					</tr>
					<tr>
						<td>Pages found</td>
						<td><?= $files ? h(implode(', ', $files)) : '&mdash;' ?></td>
					</tr>
					<tr>
						<td>Way in</td>
						<td><code>index.php?site=1</code></td>
					</tr>
				</tbody>
			</table>

			<p class="acp-hint">
				Only the front page is replaced. Every other page &mdash; login, highscores, the admin
				panel &mdash; stays reachable as usual, so you cannot lock yourself out.
			</p>
			<p class="acp-hint">
				Your css, images and scripts keep their normal relative paths: ZnoteX adds a
				<code>&lt;base&gt;</code> pointing at <code><?= h(ZNOTE_LANDING_DIR) ?>/</code> so they resolve.
				The link that enters the site must start with a slash, for example
				<code>&lt;a href="/index.php?site=1"&gt;Enter&lt;/a&gt;</code>. Once a visitor follows it,
				the landing page stays out of the way for the rest of their visit.
			</p>
		</div>
	</section>

</div>
