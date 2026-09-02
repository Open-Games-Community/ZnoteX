<?php
/**
 * Title: Settings
 * Icon: fa-cogs
 * Group: Overview
 * Order: 20
 * Description: Change the settings that used to mean editing config.php by FTP.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

/**
 * What this page can change.
 *
 * Each entry names a config key, and the value is stored in znote_config so it
 * survives an update to config.php. engine/init.php applies them over the file
 * values, so config.php stays the fallback and nothing here is ever written
 * back into a PHP file.
 *
 *   type: text | textarea | bool | int | select
 */
function acp_settings_schema(): array {
	global $config;

	return array(

		'Site' => array(
			'site_title' => array(
				'label' => 'Site title',
				'type'  => 'text',
				'help'  => 'Shown in the browser tab, the footer and the social banners.',
			),
			'site_title_context' => array(
				'label' => 'Tagline',
				'type'  => 'text',
			),
			'site_url' => array(
				'label' => 'Site URL',
				'type'  => 'text',
				'help'  => 'Used in e-mails and absolute links. No trailing slash.',
			),
		),

		'Maintenance' => array(
			'maintenance' => array(
				'label' => 'Maintenance mode',
				'type'  => 'bool',
				'help'  => 'Visitors see the message below. Admins keep full access.',
			),
			'maintenance_message' => array(
				'label' => 'Maintenance message',
				'type'  => 'textarea',
			),
		),

		'Game server' => array(
			'client' => array(
				'label' => 'Client version',
				'type'  => 'int',
				'help'  => 'For example 1098 for client 10.98.',
			),
			'port' => array(
				'label' => 'Game server port',
				'type'  => 'int',
			),
			'freePremium' => array(
				'label' => 'Free premium',
				'type'  => 'bool',
			),
		),

		'Characters' => array(
			'max_characters' => array(
				'label' => 'Characters per account',
				'type'  => 'int',
			),
			'minL' => array(
				'label' => 'Minimum name length',
				'type'  => 'int',
			),
			'maxL' => array(
				'label' => 'Maximum name length',
				'type'  => 'int',
			),
			'maxW' => array(
				'label' => 'Maximum words in a name',
				'type'  => 'int',
			),
			'create_guild_level' => array(
				'label' => 'Level required to create a guild',
				'type'  => 'int',
			),
		),

		'Content' => array(
			'news_per_page' => array(
				'label' => 'News articles per page',
				'type'  => 'int',
			),
			'UseChangelogTicker' => array(
				'label' => 'Changelog ticker on the front page',
				'type'  => 'bool',
			),
			'allowSubPages' => array(
				'label' => 'Allow theme sub pages',
				'type'  => 'bool',
			),
		),

		'Privacy' => array(
			'log_ip' => array(
				'label' => 'Log visitor IPs',
				'type'  => 'bool',
				'help'  => 'Feeds the Visitors page. Turning it off stops the collection entirely.',
			),
			'admin_show_queries' => array(
				'label' => 'Show SQL queries to admins',
				'type'  => 'bool',
				'help'  => 'Debug overlay at the top of every page, admins only.',
			),
		),
	);
}

/** Cast a submitted value for storage. */
function acp_setting_cast(string $type, $raw): string {
	switch ($type) {
		case 'bool': return empty($raw) ? '0' : '1';
		case 'int':  return (string)intv($raw);
		default:     return trim((string)$raw);
	}
}

// ---------------------------------------------------------------------------
// Save
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$schema = acp_settings_schema();
	$saved  = 0;
	$failed = 0;

	foreach ($schema as $fields) {
		foreach ($fields as $key => $field) {
			$value = acp_setting_cast($field['type'], $_POST['set'][$key] ?? '');
			setting_set('config:' . $key, $value) ? $saved++ : $failed++;
		}
	}

	if ($failed > 0) {
		acp_flash_error($failed . ' setting(s) could not be saved. Is the <code>znote_config</code> table present?');
	} else {
		acp_flash_success($saved . ' settings saved. They take effect immediately.');
	}

	acp_redirect('settings');
}

$schema   = acp_settings_schema();
$hasTable = (mysql_select_single("SELECT `key` FROM `znote_config` LIMIT 1;") !== false)
	|| (mysql_select_multi("SHOW TABLES LIKE 'znote_config';") !== false);
?>

<?php if (!$hasTable): ?>
	<div class="acp-flash acp-flash--error">
		<i class="fa fa-exclamation-triangle"></i>
		<span>
			The <code>znote_config</code> table is missing, so nothing can be saved. Run
			<code>SQL/migrations/2.0.0_znote_config.sql</code> against your database.
		</span>
	</div>
<?php endif; ?>

<div class="acp-flash acp-flash--info">
	<i class="fa fa-info-circle"></i>
	<span>
		These are stored in the database and applied over <code>config.php</code>, which stays the
		fallback. Your file is never rewritten, so an update to ZnoteX cannot lose your settings &mdash;
		and a value you have never touched here keeps following <code>config.php</code>.
	</span>
</div>

<form method="post">
	<?= acp_csrf_field() ?>

	<div class="acp-grid acp-grid--2">
		<?php foreach ($schema as $section => $fields): ?>
			<section class="acp-card">
				<header class="acp-card-head"><h2><?= h($section) ?></h2></header>
				<div class="acp-card-body">
					<?php foreach ($fields as $key => $field):
						$stored  = setting('config:' . $key, null);
						$current = ($stored !== null) ? $stored : (string)($config[$key] ?? '');
						$fromDb  = ($stored !== null);
					?>
						<div class="acp-field">
							<label class="acp-label" for="set_<?= h($key) ?>">
								<?= h($field['label']) ?>
								<?php if (!$fromDb): ?>
									<span class="acp-pill acp-pill--grey" title="Currently following config.php">file</span>
								<?php endif; ?>
							</label>

							<?php if ($field['type'] === 'bool'): ?>
								<label style="display:flex;align-items:center;gap:8px;font-weight:400;">
									<input type="checkbox" id="set_<?= h($key) ?>" name="set[<?= h($key) ?>]" value="1"
										   <?= !empty($current) && $current !== '0' ? 'checked' : '' ?>>
									<span class="is-muted">Enabled</span>
								</label>
							<?php elseif ($field['type'] === 'textarea'): ?>
								<textarea class="acp-textarea" id="set_<?= h($key) ?>" name="set[<?= h($key) ?>]" rows="3"><?= h($current) ?></textarea>
							<?php else: ?>
								<input class="acp-input" id="set_<?= h($key) ?>" name="set[<?= h($key) ?>]"
									   type="<?= $field['type'] === 'int' ? 'number' : 'text' ?>"
									   value="<?= h($current) ?>">
							<?php endif; ?>

							<?php if (!empty($field['help'])): ?>
								<p class="acp-hint"><?= h($field['help']) ?></p>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
	</div>

	<div class="acp-actions">
		<button class="acp-btn acp-btn--green" type="submit"><i class="fa fa-check"></i> Save settings</button>
		<span class="acp-hint">Marked <span class="acp-pill acp-pill--grey">file</span> means the value still comes from config.php.</span>
	</div>
</form>
