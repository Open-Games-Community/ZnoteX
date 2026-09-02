<?php
/**
 * Title: Menus
 * Icon: fa-bars
 * Group: Content
 * Order: 20
 * Description: Add, reorder and hide the links your theme shows.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

const ACP_MENU_VISIBILITY = array(
	'all'   => 'Everyone',
	'guest' => 'Logged out only',
	'user'  => 'Logged in only',
	'admin' => 'Admins only',
);

$locations = theme_menu_locations();
$location  = (string)($_GET['loc'] ?? array_key_first($locations));
if (!isset($locations[$location])) {
	$location = (string)array_key_first($locations);
}

$hasTable = znote_table_exists('znote_menu');

// ---------------------------------------------------------------------------
// Mutations
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$do  = (string)($_POST['do'] ?? '');
	$id  = intv($_POST['id'] ?? 0);
	$loc = preg_replace('/[^a-z0-9_-]/', '', strtolower((string)($_POST['location'] ?? $location)));

	if ($do === 'delete' && $id > 0) {
		// Orphaned children would vanish from the menu without explanation, so
		// lift them to the top level instead of deleting them silently.
		mysql_update("UPDATE `znote_menu` SET `parent_id` = 0 WHERE `parent_id` = {$id};");
		mysql_delete("DELETE FROM `znote_menu` WHERE `id` = {$id} LIMIT 1;");
		acp_flash_success('Entry deleted. Any sub-entries were moved to the top level.');
		acp_redirect('menus', array('loc' => $loc));
	}

	if ($do === 'toggle' && $id > 0) {
		mysql_update("UPDATE `znote_menu` SET `active` = CASE WHEN `active` = 1 THEN 0 ELSE 1 END WHERE `id` = {$id} LIMIT 1;");
		acp_redirect('menus', array('loc' => $loc));
	}

	if ($do === 'move' && $id > 0) {
		$dir  = ((string)($_POST['dir'] ?? '') === 'up') ? -15 : 15;
		mysql_update("UPDATE `znote_menu` SET `sort_order` = `sort_order` + ({$dir}) WHERE `id` = {$id} LIMIT 1;");
		acp_redirect('menus', array('loc' => $loc));
	}

	if ($do === 'save') {
		$label      = substr(trim((string)($_POST['label'] ?? '')), 0, 64);
		$url        = substr(trim((string)($_POST['url'] ?? '')), 0, 255);
		$icon       = substr(trim((string)($_POST['icon'] ?? '')), 0, 48);
		$target     = ((string)($_POST['target'] ?? '') === '_blank') ? '_blank' : '';
		$visibility = (string)($_POST['visibility'] ?? 'all');
		$parent     = intv($_POST['parent_id'] ?? 0);
		$order      = intv($_POST['sort_order'] ?? 0);

		if (!isset(ACP_MENU_VISIBILITY[$visibility])) {
			$visibility = 'all';
		}
		// An entry cannot be its own parent, nor can it nest below a child.
		if ($parent === $id) {
			$parent = 0;
		}

		if ($label === '' || $url === '') {
			acp_flash_error('A label and a URL are both required.');
			acp_redirect('menus', array('loc' => $loc));
		}

		$fields = "`label` = '" . esc($label) . "',
			`url` = '" . esc($url) . "',
			`icon` = '" . esc($icon) . "',
			`target` = '" . esc($target) . "',
			`visibility` = '" . esc($visibility) . "',
			`parent_id` = {$parent},
			`sort_order` = {$order}";

		if ($id > 0) {
			mysql_update("UPDATE `znote_menu` SET {$fields} WHERE `id` = {$id} LIMIT 1;");
			acp_flash_success('Entry updated.');
		} else {
			mysql_insert("INSERT INTO `znote_menu` SET `location` = '" . esc($loc) . "', `active` = 1, {$fields};");
			acp_flash_success('Entry added.');
		}

		acp_redirect('menus', array('loc' => $loc));
	}

	acp_flash_error('Unknown action.');
	acp_redirect('menus', array('loc' => $location));
}

// ---------------------------------------------------------------------------
// Load
// ---------------------------------------------------------------------------
$entries = $hasTable ? mysql_select_multi("
	SELECT `id`, `parent_id`, `label`, `url`, `icon`, `target`, `visibility`, `sort_order`, `active`
	FROM `znote_menu`
	WHERE `location` = '" . esc($location) . "'
	ORDER BY `sort_order` ASC, `id` ASC;
") : false;
$entries = is_array($entries) ? $entries : array();

$editing = null;
if (($_GET['action'] ?? '') === 'edit') {
	$editId = intv($_GET['id'] ?? 0);
	foreach ($entries as $entry) {
		if ((int)$entry['id'] === $editId) {
			$editing = $entry;
			break;
		}
	}
}

// Top-level entries, for the parent dropdown.
$parents = array();
foreach ($entries as $entry) {
	if ((int)$entry['parent_id'] === 0 && (int)$entry['id'] !== (int)($editing['id'] ?? 0)) {
		$parents[(int)$entry['id']] = (string)$entry['label'];
	}
}
?>

<?php if (!$hasTable): ?>
	<div class="acp-flash acp-flash--error">
		<i class="fa fa-exclamation-triangle"></i>
		<span>
			The <code>znote_menu</code> table is missing. Run
			<code>SQL/migrations/2.0.0_menus.sql</code> against your database. Until then, themes
			keep using whatever links they hardcode.
		</span>
	</div>
<?php endif; ?>

<div class="acp-toolbar">
	<div class="acp-actions is-tight">
		<?php foreach ($locations as $slug => $label): ?>
			<a class="acp-btn <?= $slug === $location ? '' : 'acp-btn--ghost' ?> acp-btn--sm"
			   href="<?= h(acp_url('menus', array('loc' => $slug))) ?>">
				<?= h($label) ?>
			</a>
		<?php endforeach; ?>
	</div>
	<span class="is-muted"><?= count($entries) ?> entries in <code><?= h($location) ?></code></span>
</div>

<div class="acp-grid acp-grid--2">

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= $editing !== null ? 'Edit entry' : 'Add an entry' ?></h2>
			<p>to <?= h($locations[$location]) ?></p>
		</header>
		<div class="acp-card-body">
			<form method="post">
				<?= acp_csrf_field() ?>
				<input type="hidden" name="do" value="save">
				<input type="hidden" name="location" value="<?= h($location) ?>">
				<?php if ($editing !== null): ?>
					<input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
				<?php endif; ?>

				<div class="acp-row">
					<div class="acp-field">
						<label class="acp-label" for="label">Label</label>
						<input class="acp-input" id="label" name="label" required
							   value="<?= h((string)($editing['label'] ?? '')) ?>">
					</div>
					<div class="acp-field">
						<label class="acp-label" for="url">URL</label>
						<input class="acp-input" id="url" name="url" required
							   placeholder="highscores.php"
							   value="<?= h((string)($editing['url'] ?? '')) ?>">
					</div>
				</div>

				<div class="acp-row">
					<div class="acp-field">
						<label class="acp-label" for="icon">Icon</label>
						<input class="acp-input" id="icon" name="icon" placeholder="fa-users"
							   value="<?= h((string)($editing['icon'] ?? '')) ?>">
						<p class="acp-hint">Font Awesome class. Themes may ignore it.</p>
					</div>
					<div class="acp-field">
						<label class="acp-label" for="parent_id">Under</label>
						<select class="acp-select" id="parent_id" name="parent_id">
							<option value="0">Top level</option>
							<?php foreach ($parents as $pid => $plabel): ?>
								<option value="<?= $pid ?>" <?= (int)($editing['parent_id'] ?? 0) === $pid ? 'selected' : '' ?>>
									<?= h($plabel) ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="acp-row">
					<div class="acp-field">
						<label class="acp-label" for="visibility">Shown to</label>
						<select class="acp-select" id="visibility" name="visibility">
							<?php foreach (ACP_MENU_VISIBILITY as $value => $vlabel): ?>
								<option value="<?= h($value) ?>" <?= (string)($editing['visibility'] ?? 'all') === $value ? 'selected' : '' ?>>
									<?= h($vlabel) ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="acp-field">
						<label class="acp-label" for="target">Opens in</label>
						<select class="acp-select" id="target" name="target">
							<option value="">Same tab</option>
							<option value="_blank" <?= (string)($editing['target'] ?? '') === '_blank' ? 'selected' : '' ?>>New tab</option>
						</select>
					</div>
					<div class="acp-field">
						<label class="acp-label" for="sort_order">Order</label>
						<input class="acp-input" id="sort_order" name="sort_order" type="number"
							   value="<?= (int)($editing['sort_order'] ?? ((count($entries) + 1) * 10)) ?>">
					</div>
				</div>

				<div class="acp-actions">
					<button class="acp-btn acp-btn--green" type="submit">
						<i class="fa fa-check"></i> <?= $editing !== null ? 'Save entry' : 'Add entry' ?>
					</button>
					<?php if ($editing !== null): ?>
						<a class="acp-btn acp-btn--ghost" href="<?= h(acp_url('menus', array('loc' => $location))) ?>">Cancel</a>
					<?php endif; ?>
				</div>
			</form>
		</div>
	</section>

	<section class="acp-card">
		<header class="acp-card-head"><h2>How themes use this</h2></header>
		<div class="acp-card-body">
			<p>
				A theme declares the slots it renders in its <code>theme.json</code>, and asks for the
				entries. It keeps full control of the markup:
			</p>
			<pre class="acp-dump">"menus": { "main": "Top navigation" }

&lt;?php foreach (theme_menu_items('main') as $item): ?&gt;
  &lt;a href="&lt;?= $item['url'] ?&gt;"&gt;&lt;?= $item['label'] ?&gt;&lt;/a&gt;
&lt;?php endforeach; ?&gt;</pre>
			<p class="is-muted">
				Entries are already filtered for the visitor, so a link marked
				<em>Admins only</em> never reaches anyone else &mdash; it is not hidden with CSS,
				it is simply absent.
			</p>
			<p class="is-muted">
				A theme that hardcodes its menu keeps working and ignores this page.
			</p>
		</div>
	</section>
</div>

<section class="acp-card">
	<header class="acp-card-head">
		<h2><?= h($locations[$location]) ?></h2>
		<p>Lower order numbers come first</p>
	</header>
	<div class="acp-card-body is-flush">
		<?php if ($entries): ?>
			<div class="acp-table-wrap">
				<table class="acp-table">
					<thead>
						<tr>
							<th class="is-num">Order</th>
							<th>Label</th>
							<th>URL</th>
							<th>Shown to</th>
							<th class="is-num">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($entries as $entry):
							$id     = (int)$entry['id'];
							$child  = (int)$entry['parent_id'] > 0;
							$hidden = ((int)$entry['active'] === 0);
						?>
							<tr<?= $hidden ? ' style="opacity:.5;"' : '' ?>>
								<td class="is-num is-muted"><?= (int)$entry['sort_order'] ?></td>
								<td>
									<?= $child ? '<span class="is-muted">&mdash;&nbsp;</span>' : '' ?>
									<?php if ($entry['icon'] !== ''): ?>
										<i class="fa <?= h((string)$entry['icon']) ?> is-muted"></i>
									<?php endif; ?>
									<?= h((string)$entry['label']) ?>
									<?php if ($hidden): ?>
										<span class="acp-pill acp-pill--grey">hidden</span>
									<?php endif; ?>
								</td>
								<td class="is-muted"><code><?= h((string)$entry['url']) ?></code></td>
								<td>
									<span class="acp-pill acp-pill--<?= $entry['visibility'] === 'admin' ? 'red' : ($entry['visibility'] === 'all' ? 'grey' : 'blue') ?>">
										<?= h(ACP_MENU_VISIBILITY[$entry['visibility']] ?? $entry['visibility']) ?>
									</span>
								</td>
								<td class="is-num is-nowrap">
									<?php foreach (array('up' => 'fa-arrow-up', 'down' => 'fa-arrow-down') as $dir => $icon): ?>
										<form class="acp-inline-form" method="post">
											<?= acp_csrf_field() ?>
											<input type="hidden" name="do" value="move">
											<input type="hidden" name="id" value="<?= $id ?>">
											<input type="hidden" name="dir" value="<?= $dir ?>">
											<input type="hidden" name="location" value="<?= h($location) ?>">
											<button class="acp-btn acp-btn--ghost acp-btn--sm" type="submit" title="Move <?= $dir ?>">
												<i class="fa <?= $icon ?>"></i>
											</button>
										</form>
									<?php endforeach; ?>

									<form class="acp-inline-form" method="post">
										<?= acp_csrf_field() ?>
										<input type="hidden" name="do" value="toggle">
										<input type="hidden" name="id" value="<?= $id ?>">
										<input type="hidden" name="location" value="<?= h($location) ?>">
										<button class="acp-btn acp-btn--ghost acp-btn--sm" type="submit" title="<?= $hidden ? 'Show' : 'Hide' ?>">
											<i class="fa <?= $hidden ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
										</button>
									</form>

									<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('menus', array('loc' => $location, 'action' => 'edit', 'id' => $id))) ?>">
										<i class="fa fa-pencil"></i>
									</a>

									<form class="acp-inline-form" method="post" data-confirm="Delete this menu entry?">
										<?= acp_csrf_field() ?>
										<input type="hidden" name="do" value="delete">
										<input type="hidden" name="id" value="<?= $id ?>">
										<input type="hidden" name="location" value="<?= h($location) ?>">
										<button class="acp-btn acp-btn--red acp-btn--sm" type="submit"><i class="fa fa-trash"></i></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<?php acp_empty('No entries in this menu yet.', 'fa-bars'); ?>
		<?php endif; ?>
	</div>
</section>
