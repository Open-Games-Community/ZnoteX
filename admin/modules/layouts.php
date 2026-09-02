<?php
/**
 * Title: Layout
 * Icon: fa-paint-brush
 * Group: Content
 * Order: 5
 * Description: Pick the theme the public site is dressed in.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

// ---------------------------------------------------------------------------
// Activate a theme
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {

	$key       = theme_sanitize((string)$_POST['install']);
	$overwrite = !empty($_POST['overwrite']);
	$result    = theme_repository_install($key, $overwrite);

	if ($result === '') {
		acp_flash_success('<strong>' . h($key) . '</strong> installed into <code>layouts/' . h($key) . '/</code>.');
	} elseif ($result === 'already-installed') {
		acp_flash_error('<strong>' . h($key) . '</strong> is already installed. Use Reinstall to replace it.');
	} else {
		acp_flash_error('Install failed: ' . h($result));
	}

	acp_redirect('layouts', array('tab' => 'browse'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['uninstall'])) {

	$key    = theme_sanitize((string)$_POST['uninstall']);
	$result = theme_uninstall($key);

	if ($result === '') {
		acp_flash_success('<strong>' . h($key) . '</strong> removed from <code>layouts/</code>.');
	} else {
		acp_flash_error('Could not remove it: ' . h($result));
	}

	acp_redirect('layouts');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['theme_options'])) {

	$target  = theme_sanitize((string)$_POST['theme_options']);
	$themes  = theme_list();
	$options = isset($themes[$target]) ? theme_options($target) : array();

	if ($options === array()) {
		acp_flash_error('That theme has no options to set.');
		acp_redirect('layouts');
	}

	$saved = 0;
	$failed = 0;
	foreach ($options as $key => $option) {
		$value = ($option['type'] === 'checkbox')
			? (empty($_POST['opt'][$key]) ? '' : '1')
			: trim((string)($_POST['opt'][$key] ?? ''));

		setting_set(theme_option_key($target, $key), $value) ? $saved++ : $failed++;
	}

	if ($failed > 0) {
		acp_flash_error($failed . ' setting(s) could not be saved. Is the <code>znote_config</code> table present?');
	} else {
		acp_flash_success('Options saved for <strong>' . h($themes[$target]['name']) . '</strong>.');
	}

	acp_redirect('layouts', array('options' => $target));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$requested = theme_sanitize((string)($_POST['layout'] ?? ''));
	$themes    = theme_list();

	if ($requested === '' || !isset($themes[$requested])) {
		acp_flash_error('Unknown theme.');
	} elseif (theme_file_exists_in($requested, 'shells/default.php') === false) {
		acp_flash_error(
			'<strong>' . h($themes[$requested]['name']) . '</strong> has no '
			. '<code>shells/default.php</code>, so it cannot render a page. Not activated.'
		);
	} elseif (setting_set('layout', $requested)) {
		acp_flash_success('Theme switched to <strong>' . h($themes[$requested]['name']) . '</strong>.');
	} else {
		acp_flash_error('Could not save the setting. Is the <code>znote_config</code> table present? See SQL/migrations/.');
	}

	acp_redirect('layouts');
}

/** A theme is only usable if it can produce a page frame. */
function theme_file_exists_in(string $theme, string $relative): bool {
	return is_file(theme_root() . '/' . $theme . '/' . ltrim($relative, '/'));
}

/**
 * How many shells / views / pages a theme ships, for the card summary.
 *
 * readdir rather than glob: measured on Windows with 100 themes installed,
 * glob takes 159ms and readdir 37ms for the same 309 directory reads. glob
 * builds and sorts a match array we immediately throw away.
 */
function acp_theme_counts(string $theme): array {
	static $cache = [];
	if (isset($cache[$theme])) {
		return $cache[$theme];
	}

	$counts = ['shells' => 0, 'views' => 0, 'pages' => 0];
	$base   = theme_root() . '/' . $theme;

	foreach (array_keys($counts) as $sub) {
		$handle = @opendir($base . '/' . $sub);
		if ($handle === false) {
			continue;
		}
		while (($entry = readdir($handle)) !== false) {
			if (substr($entry, -4) === '.php') {
				$counts[$sub]++;
			}
		}
		closedir($handle);
	}

	return $cache[$theme] = $counts;
}

$themes  = theme_list();
$active  = theme_active();

// The Browse tab is a separate view over the same module.
if (($_GET['tab'] ?? '') === 'browse') {
	include ACP_ROOT . '/modules/_partials/layouts_browse.php';
	return;
}

// ---------------------------------------------------------------------------
// Search and paging.
//
// Both server side on purpose: only the themes actually on screen get their
// shells/views/pages counted, so the page costs the same with 5 themes
// installed or 500. A client-side filter would have had to render every card.
// ---------------------------------------------------------------------------
const ACP_THEMES_PER_PAGE = 12;

$search = trim((string)($_GET['q'] ?? ''));
$total  = count($themes);

if ($search !== '') {
	$needle  = strtolower($search);
	$themes = array_filter($themes, static function (array $t) use ($needle): bool {
		return str_contains(strtolower($t['name'] . ' ' . $t['key'] . ' ' . $t['author'] . ' ' . $t['description']), $needle);
	});
}

$matched   = count($themes);
$pageCount = max(1, (int)ceil($matched / ACP_THEMES_PER_PAGE));
$page      = max(1, min($pageCount, intv($_GET['tp'] ?? 1)));
$themes    = array_slice($themes, ($page - 1) * ACP_THEMES_PER_PAGE, ACP_THEMES_PER_PAGE, true);

/** Keep the current search when building a paging link. */
function acp_theme_page_url(int $page, string $search): string {
	$params = ['tp' => $page];
	if ($search !== '') {
		$params['q'] = $search;
	}
	return acp_url('layouts', $params);
}
$hasTable = (mysql_select_single("SELECT `value` FROM `znote_config` WHERE `key` = 'layout' LIMIT 1;") !== false);
?>

<?php if (!$hasTable): ?>
	<div class="acp-flash acp-flash--error">
		<i class="fa fa-exclamation-triangle"></i>
		<span>
			The <code>znote_config</code> table is missing, so the theme cannot be saved and the
			site stays on <strong>default</strong>. Run
			<code>SQL/migrations/2.0.0_znote_config.sql</code> against your database.
		</span>
	</div>
<?php endif; ?>

<div class="acp-toolbar">
	<div></div>
	<div class="acp-actions is-tight">
		<?php if (theme_repository_config()['enabled']): ?>
			<a class="acp-btn" href="<?= h(acp_url('layouts', array('tab' => 'browse'))) ?>">
				<i class="fa fa-cloud-download"></i> Browse themes
			</a>
		<?php endif; ?>
	</div>
</div>

<div class="acp-stats">
	<?php
	acp_stat('Installed themes', count($themes), 'fa-paint-brush', null, 'purple');
	acp_stat('Active', $themes[$active]['name'] ?? $active, 'fa-check-circle', null, 'green');
	?>
</div>

<?php if ($total > ACP_THEMES_PER_PAGE || $search !== ''): ?>
	<div class="acp-toolbar">
		<form method="get" style="display:flex;gap:8px;flex:1 1 320px;max-width:460px;">
			<input type="hidden" name="p" value="layouts">
			<input class="acp-input" type="search" name="q" value="<?= h($search) ?>"
				   placeholder="Search themes by name, folder or author&hellip;">
			<button class="acp-btn" type="submit"><i class="fa fa-search"></i></button>
			<?php if ($search !== ''): ?>
				<a class="acp-btn acp-btn--ghost" href="<?= h(acp_url('layouts')) ?>">Clear</a>
			<?php endif; ?>
		</form>
		<span class="is-muted">
			<?php if ($search !== ''): ?>
				<?= (int)$matched ?> of <?= (int)$total ?> themes
			<?php else: ?>
				<?= (int)$total ?> themes
			<?php endif; ?>
			<?php if ($pageCount > 1): ?>
				&middot; page <?= (int)$page ?> / <?= (int)$pageCount ?>
			<?php endif; ?>
		</span>
	</div>
<?php endif; ?>

<div class="acp-media">
	<?php foreach ($themes as $key => $theme):
		$isActive   = ($key === $active);
		$isUsable   = theme_file_exists_in($key, 'shells/default.php');
		$counts     = acp_theme_counts($key);
		$views      = $counts['views'];
		$pages      = $counts['pages'];
		$shells     = $counts['shells'];
	?>
		<article class="acp-media-item"<?= $isActive ? ' style="border-color:var(--acp-green);"' : '' ?>>
			<?php if (!empty($theme['screenshot'])): ?>
				<img src="<?= h(acp_site($theme['screenshot'])) ?>" alt="<?= h($theme['name']) ?>" loading="lazy">
			<?php else: ?>
				<div style="display:grid;place-items:center;height:170px;background:var(--acp-panel-2);color:var(--acp-fg-muted);">
					<span><i class="fa fa-picture-o"></i> &nbsp;no screenshot.png</span>
				</div>
			<?php endif; ?>

			<div class="acp-media-body">
				<h3>
					<?= h($theme['name']) ?>
					<?php if ($isActive): ?>
						<span class="acp-pill acp-pill--green">Active</span>
					<?php endif; ?>
					<?php if (!empty($theme['is_example'])): ?>
						<span class="acp-pill acp-pill--grey">Template</span>
					<?php endif; ?>
				</h3>

				<p>
					<?= h($theme['description']) ?>
				</p>

				<p class="is-muted" style="font-size:12px;">
					<code>layouts/<?= h($key) ?>/</code>
					<?php if ($theme['author'] !== ''): ?>
						&middot; by <?= h($theme['author']) ?>
					<?php endif; ?>
					<?php if ($theme['version'] !== ''): ?>
						&middot; v<?= h($theme['version']) ?>
					<?php endif; ?>
				</p>

				<p style="font-size:12px;">
					<span class="acp-pill acp-pill--blue"><?= $shells ?> shell<?= $shells === 1 ? '' : 's' ?></span>
					<span class="acp-pill acp-pill--grey"><?= $views ?> view<?= $views === 1 ? '' : 's' ?></span>
					<span class="acp-pill acp-pill--grey"><?= $pages ?> extra page<?= $pages === 1 ? '' : 's' ?></span>
				</p>

				<?php if (!$isUsable): ?>
					<p class="is-muted" style="font-size:12px;color:var(--acp-red);">
						No <code>shells/default.php</code> &mdash; this theme cannot be activated.
					</p>
				<?php elseif ($views === 0): ?>
					<p class="is-muted" style="font-size:12px;">
						No views of its own: every page uses the default theme's markup,
						wrapped in this theme's shell and styled by its CSS.
					</p>
				<?php endif; ?>
			</div>

			<div class="acp-media-foot">
				<?php if ($isActive): ?>
					<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_site('index.php')) ?>" target="_blank" rel="noopener">
						<i class="fa fa-external-link"></i> View site
					</a>
				<?php else: ?>
					<form class="acp-inline-form" method="post"
						  data-confirm="Switch the public site to this theme?">
						<?= acp_csrf_field() ?>
						<input type="hidden" name="layout" value="<?= h($key) ?>">
						<button class="acp-btn acp-btn--sm" type="submit" <?= $isUsable ? '' : 'disabled' ?>>
							<i class="fa fa-check"></i> Activate
						</button>
					</form>
				<?php endif; ?>

				<?php if (theme_options($key)): ?>
					<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('layouts', array('options' => $key))) ?>">
						<i class="fa fa-sliders"></i> Options
					</a>
				<?php endif; ?>

				<?php if (!$isActive && $key !== 'default' && empty($theme['is_example'])): ?>
					<form class="acp-inline-form" method="post"
						  data-confirm="Delete layouts/<?= h($key) ?>/ and everything in it? This cannot be undone.">
						<?= acp_csrf_field() ?>
						<input type="hidden" name="uninstall" value="<?= h($key) ?>">
						<button class="acp-btn acp-btn--red acp-btn--sm" type="submit">
							<i class="fa fa-trash"></i> Remove
						</button>
					</form>
				<?php endif; ?>
			</div>
		</article>
	<?php endforeach; ?>
</div>

<?php if ($matched === 0): ?>
	<section class="acp-card">
		<div class="acp-card-body">
			<?php acp_empty('No theme matches "' . $search . '".', 'fa-search'); ?>
			<div class="acp-actions" style="justify-content:center;">
				<a class="acp-btn acp-btn--ghost" href="<?= h(acp_url('layouts')) ?>">Show all themes</a>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php if ($pageCount > 1): ?>
	<nav class="acp-actions" style="justify-content:center;margin:18px 0 24px;" aria-label="Theme pages">
		<a class="acp-btn acp-btn--ghost acp-btn--sm<?= $page <= 1 ? ' is-disabled' : '' ?>"
		   href="<?= h(acp_theme_page_url(max(1, $page - 1), $search)) ?>"
		   <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
			<i class="fa fa-angle-left"></i> Previous
		</a>

		<?php for ($i = 1; $i <= $pageCount; $i++): ?>
			<?php if ($i === $page): ?>
				<span class="acp-btn acp-btn--sm"><?= $i ?></span>
			<?php else: ?>
				<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_theme_page_url($i, $search)) ?>"><?= $i ?></a>
			<?php endif; ?>
		<?php endfor; ?>

		<a class="acp-btn acp-btn--ghost acp-btn--sm<?= $page >= $pageCount ? ' is-disabled' : '' ?>"
		   href="<?= h(acp_theme_page_url(min($pageCount, $page + 1), $search)) ?>"
		   <?= $page >= $pageCount ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
			Next <i class="fa fa-angle-right"></i>
		</a>
	</nav>
<?php endif; ?>

<?php
$optionTheme = theme_sanitize((string)($_GET['options'] ?? ''));
$optionList  = ($optionTheme !== '' && isset($themes[$optionTheme])) ? theme_options($optionTheme) : array();

if ($optionList):
?>
	<section class="acp-card" id="theme-options">
		<header class="acp-card-head">
			<h2><?= h($themes[$optionTheme]['name']) ?> &mdash; options</h2>
			<p>Saved per theme, in the database. The theme's files are never modified.</p>
		</header>
		<div class="acp-card-body">
			<form method="post">
				<?= acp_csrf_field() ?>
				<input type="hidden" name="theme_options" value="<?= h($optionTheme) ?>">

				<?php foreach ($optionList as $optKey => $opt):
					$value = theme_option($optKey, '', $optionTheme);
				?>
					<div class="acp-field">
						<label class="acp-label" for="opt_<?= h($optKey) ?>"><?= h($opt['label']) ?></label>

						<?php if ($opt['type'] === 'textarea'): ?>
							<textarea class="acp-textarea" id="opt_<?= h($optKey) ?>" name="opt[<?= h($optKey) ?>]" rows="3"><?= h($value) ?></textarea>
						<?php elseif ($opt['type'] === 'checkbox'): ?>
							<label style="display:flex;align-items:center;gap:8px;font-weight:400;">
								<input type="checkbox" id="opt_<?= h($optKey) ?>" name="opt[<?= h($optKey) ?>]" value="1" <?= $value !== '' ? 'checked' : '' ?>>
								<span class="is-muted">Enabled</span>
							</label>
						<?php else: ?>
							<input class="acp-input" id="opt_<?= h($optKey) ?>" name="opt[<?= h($optKey) ?>]"
								   type="<?= $opt['type'] === 'url' ? 'url' : 'text' ?>"
								   value="<?= h($value) ?>"
								   placeholder="<?= h($opt['default']) ?>">
						<?php endif; ?>

						<?php if ($opt['help'] !== ''): ?>
							<p class="acp-hint"><?= h($opt['help']) ?></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>

				<div class="acp-actions">
					<button class="acp-btn acp-btn--green" type="submit"><i class="fa fa-check"></i> Save options</button>
					<a class="acp-btn acp-btn--ghost" href="<?= h(acp_url('layouts')) ?>">Close</a>
					<span class="acp-hint">Leave a field empty to fall back to the theme's own default.</span>
				</div>
			</form>
		</div>
	</section>
<?php endif; ?>

<section class="acp-card">
	<header class="acp-card-head">
		<h2>Making a theme</h2>
		<p>The short version</p>
	</header>
	<div class="acp-card-body">
		<p>
			Copy <code>layouts/_example/</code>, rename the folder, edit
			<code>theme.json</code>. Your theme is listed here immediately.
		</p>
		<p>
			The only file a theme truly needs is <code>shells/default.php</code> &mdash; the frame
			your pages render inside. Anything else you leave out is taken from the default theme,
			so a stylesheet and a shell are enough to redress the entire site.
		</p>
		<p>
			The picture on these cards is a file named <code>screenshot.png</code> at the root of
			the theme folder &mdash; nothing to declare, drop it in and reload this page.
			Any size works; roughly 3:2 shows best.
		</p>
		<p>
			Full instructions, including the list of CSS classes the pages emit, are in
			<code>layouts/README.md</code>.
		</p>
	</div>
</section>
