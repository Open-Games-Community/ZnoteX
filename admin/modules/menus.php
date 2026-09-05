<?php
/**
 * Title: Menus
 * Icon: fa-bars
 * Group: Content
 * Order: 40
 * Description: Add, reorder and hide the links your theme shows.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

function acp_menu_visibility(): array {
	return array(
		'all'   => t('acp.menu.vis_all'),
		'guest' => t('acp.menu.vis_guest'),
		'user'  => t('acp.menu.vis_user'),
		'admin' => t('acp.menu.vis_admin'),
	);
}

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
	if (!isset($locations[$loc])) {
		$loc = $location;
	}

	if ($do === 'delete' && $id > 0) {
		$entry = mysql_select_single("SELECT `parent_id`, `label` FROM `znote_menu` WHERE `id` = {$id} LIMIT 1;");
		if (is_array($entry) && (int)$entry['parent_id'] === 0) {
			acp_flash_error(t('acp.menu.cat_no_delete'));
			acp_redirect('menus', array('loc' => $loc));
		}
		mysql_delete("DELETE FROM `znote_menu` WHERE `id` = {$id} LIMIT 1;");
		acp_log('menu.delete', is_array($entry) ? (string)$entry['label'] : ('#' . $id));
		acp_flash_success(t('acp.menu.deleted'));
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
		$existing   = $id > 0 ? mysql_select_single("
			SELECT `id`, `parent_id`, `location`
			FROM `znote_menu`
			WHERE `id` = {$id}
			LIMIT 1;
		") : false;
		$editingCategory = is_array($existing) && (int)$existing['parent_id'] === 0;

		if (!isset(acp_menu_visibility()[$visibility])) {
			$visibility = 'all';
		}
		// A top-level entry is a category: a heading that opens its children,
		// not a link of its own. Everything nested under one still needs a URL.
		$isCategory = ($parent === 0);

		if ($label === '') {
			acp_flash_error(t('acp.menu.label_required'));
			acp_redirect('menus', array('loc' => $loc));
		}
		if (!$isCategory && $url === '') {
			acp_flash_error(t('acp.menu.url_required'));
			acp_redirect('menus', array('loc' => $loc));
		}

		if ($id > 0 && !is_array($existing)) {
			acp_flash_error(t('acp.menu.entry_gone'));
			acp_redirect('menus', array('loc' => $loc));
		}

		if ($editingCategory) {
			$parent = 0;
		} else {
			$category = $parent > 0 ? mysql_select_single("
				SELECT `id`
				FROM `znote_menu`
				WHERE `id` = {$parent}
				AND `location` = '" . esc($loc) . "'
				AND `parent_id` = 0
				LIMIT 1;
			") : false;
			if (!is_array($category)) {
				acp_flash_error(t('acp.menu.choose_category'));
				acp_redirect('menus', array('loc' => $loc));
			}
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
			acp_log('menu.update', $label, ['url' => $url, 'location' => $loc]);
			acp_flash_success(t('acp.menu.updated'));
		} else {
			mysql_insert("INSERT INTO `znote_menu` SET `location` = '" . esc($loc) . "', `active` = 1, {$fields};");
			acp_log('menu.create', $label, ['url' => $url, 'location' => $loc]);
			acp_flash_success(t('acp.menu.added'));
		}

		acp_redirect('menus', array('loc' => $loc));
	}

	acp_flash_error(t('acp.menu.unknown_action'));
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
$editingCategory = is_array($editing) && (int)$editing['parent_id'] === 0;

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
			<?= t('acp.menu.table_missing', [
				'table' => '<code>znote_menu</code>',
				'file'  => '<code>SQL/migrations/2.0.0_menus.sql</code>',
			]) ?>
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
	<span class="is-muted"><?= t('acp.menu.entries_in', ['n' => count($entries), 'location' => '<code>' . h($location) . '</code>']) ?></span>
</div>

<div class="acp-grid acp-grid--2">

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= $editing !== null ? t('acp.menu.edit_entry') : t('acp.menu.add_entry') ?></h2>
			<p><?= t('acp.menu.to_location', ['location' => h($locations[$location])]) ?></p>
		</header>
		<div class="acp-card-body">
			<?php if ($editing === null && !$parents): ?>
				<div class="acp-flash acp-flash--error">
					<i class="fa fa-exclamation-triangle"></i>
					<span><?= t('acp.menu.no_category') ?></span>
				</div>
			<?php endif; ?>
			<form method="post">
				<?= acp_csrf_field() ?>
				<input type="hidden" name="do" value="save">
				<input type="hidden" name="location" value="<?= h($location) ?>">
				<?php if ($editing !== null): ?>
					<input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
				<?php endif; ?>

				<div class="acp-row">
					<div class="acp-field">
						<label class="acp-label" for="label"><?= t('acp.menu.label') ?></label>
						<input class="acp-input" id="label" name="label" required
							   value="<?= h((string)($editing['label'] ?? '')) ?>">
					</div>
					<div class="acp-field">
						<label class="acp-label" for="url"><?= t('acp.menu.url') ?></label>
						<input class="acp-input" id="url" name="url"
							   placeholder="highscores.php"
							   value="<?= h((string)($editing['url'] ?? '')) ?>">
						<p class="acp-hint"><?= t('acp.menu.url_hint') ?></p>
					</div>
				</div>

				<div class="acp-row">
					<div class="acp-field">
						<label class="acp-label" for="icon"><?= t('acp.menu.icon') ?></label>
						<input class="acp-input" id="icon" name="icon" placeholder="fa-users"
							   value="<?= h((string)($editing['icon'] ?? '')) ?>">
						<p class="acp-hint"><?= t('acp.menu.icon_hint') ?></p>
					</div>
					<div class="acp-field">
						<label class="acp-label" for="parent_id"><?= t('acp.menu.category') ?></label>
						<?php if ($editingCategory): ?>
							<input type="hidden" name="parent_id" value="0">
							<input class="acp-input" id="parent_id" value="<?= h(t('acp.menu.top_level')) ?>" disabled>
						<?php else: ?>
							<select class="acp-select" id="parent_id" name="parent_id" required>
								<option value="" disabled <?= (int)($editing['parent_id'] ?? 0) === 0 ? 'selected' : '' ?>><?= t('acp.menu.choose_category_opt') ?></option>
								<?php foreach ($parents as $pid => $plabel): ?>
									<option value="<?= $pid ?>" <?= (int)($editing['parent_id'] ?? 0) === $pid ? 'selected' : '' ?>>
										<?= h($plabel) ?>
									</option>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>
					</div>
				</div>

				<div class="acp-row">
					<div class="acp-field">
						<label class="acp-label" for="visibility"><?= t('acp.menu.shown_to') ?></label>
						<select class="acp-select" id="visibility" name="visibility">
							<?php foreach (acp_menu_visibility() as $value => $vlabel): ?>
								<option value="<?= h($value) ?>" <?= (string)($editing['visibility'] ?? 'all') === $value ? 'selected' : '' ?>>
									<?= h($vlabel) ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="acp-field">
						<label class="acp-label" for="target"><?= t('acp.menu.opens_in') ?></label>
						<select class="acp-select" id="target" name="target">
							<option value=""><?= t('acp.menu.same_tab') ?></option>
							<option value="_blank" <?= (string)($editing['target'] ?? '') === '_blank' ? 'selected' : '' ?>><?= t('acp.menu.new_tab') ?></option>
						</select>
					</div>
					<div class="acp-field">
						<label class="acp-label" for="sort_order"><?= t('acp.menu.order') ?></label>
						<input class="acp-input" id="sort_order" name="sort_order" type="number"
							   value="<?= (int)($editing['sort_order'] ?? ((count($entries) + 1) * 10)) ?>">
					</div>
				</div>

				<div class="acp-actions">
					<button class="acp-btn acp-btn--green" type="submit" <?= $editing === null && !$parents ? 'disabled' : '' ?>>
						<i class="fa fa-check"></i> <?= $editing !== null ? t('acp.menu.save_entry') : t('acp.menu.add_entry_btn') ?>
					</button>
					<?php if ($editing !== null): ?>
						<a class="acp-btn acp-btn--ghost" href="<?= h(acp_url('menus', array('loc' => $location))) ?>"><?= t('acp.menu.cancel') ?></a>
					<?php endif; ?>
				</div>
			</form>
		</div>
	</section>

	<section class="acp-card">
		<header class="acp-card-head"><h2><?= t('acp.menu.how_title') ?></h2></header>
		<div class="acp-card-body">
			<p>
				<?= t('acp.menu.how_text1', ['json' => '<code>theme.json</code>']) ?>
			</p>
			<pre class="acp-dump">"menus": { "main": "Top navigation" }

&lt;?php foreach (theme_menu_items('main') as $item): ?&gt;
  &lt;a href="&lt;?= $item['url'] ?&gt;"&gt;&lt;?= $item['label'] ?&gt;&lt;/a&gt;
&lt;?php endforeach; ?&gt;</pre>
			<p class="is-muted">
				<?= t('acp.menu.how_text2', ['admins_only' => '<em>' . t('acp.menu.admins_only') . '</em>']) ?>
			</p>
			<p class="is-muted">
				<?= t('acp.menu.how_text3') ?>
			</p>
		</div>
	</section>
</div>

<section class="acp-card">
	<header class="acp-card-head">
		<h2><?= h($locations[$location]) ?></h2>
		<p><?= t('acp.menu.lower_first') ?></p>
	</header>
	<div class="acp-card-body is-flush">
		<?php if ($entries): ?>
			<div class="acp-table-wrap">
				<table class="acp-table">
					<thead>
						<tr>
							<th class="is-num"><?= t('acp.menu.col_order') ?></th>
							<th><?= t('acp.menu.col_label') ?></th>
							<th><?= t('acp.menu.col_url') ?></th>
							<th><?= t('acp.menu.col_shown_to') ?></th>
							<th class="is-num"><?= t('acp.menu.col_actions') ?></th>
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
										<span class="acp-pill acp-pill--grey"><?= t('acp.menu.hidden_pill') ?></span>
									<?php endif; ?>
								</td>
								<td class="is-muted"><code><?= h((string)$entry['url']) ?></code></td>
								<td>
									<span class="acp-pill acp-pill--<?= $entry['visibility'] === 'admin' ? 'red' : ($entry['visibility'] === 'all' ? 'grey' : 'blue') ?>">
										<?= h(acp_menu_visibility()[$entry['visibility']] ?? $entry['visibility']) ?>
									</span>
								</td>
								<td class="is-num is-nowrap">
									<?php foreach (array('up' => ['fa-arrow-up', t('acp.menu.move_up')], 'down' => ['fa-arrow-down', t('acp.menu.move_down')]) as $dir => $meta): ?>
										<form class="acp-inline-form" method="post">
											<?= acp_csrf_field() ?>
											<input type="hidden" name="do" value="move">
											<input type="hidden" name="id" value="<?= $id ?>">
											<input type="hidden" name="dir" value="<?= $dir ?>">
											<input type="hidden" name="location" value="<?= h($location) ?>">
											<button class="acp-btn acp-btn--ghost acp-btn--sm" type="submit" title="<?= h($meta[1]) ?>">
												<i class="fa <?= $meta[0] ?>"></i>
											</button>
										</form>
									<?php endforeach; ?>

									<form class="acp-inline-form" method="post">
										<?= acp_csrf_field() ?>
										<input type="hidden" name="do" value="toggle">
										<input type="hidden" name="id" value="<?= $id ?>">
										<input type="hidden" name="location" value="<?= h($location) ?>">
										<button class="acp-btn acp-btn--ghost acp-btn--sm" type="submit" title="<?= h($hidden ? t('acp.menu.show') : t('acp.menu.hide')) ?>">
											<i class="fa <?= $hidden ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
										</button>
									</form>

									<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('menus', array('loc' => $location, 'action' => 'edit', 'id' => $id))) ?>">
										<i class="fa fa-pencil"></i>
									</a>

									<?php if ($child): ?>
										<form class="acp-inline-form" method="post" data-confirm="<?= h(t('acp.menu.confirm_delete')) ?>">
											<?= acp_csrf_field() ?>
											<input type="hidden" name="do" value="delete">
											<input type="hidden" name="id" value="<?= $id ?>">
											<input type="hidden" name="location" value="<?= h($location) ?>">
											<button class="acp-btn acp-btn--red acp-btn--sm" type="submit"><i class="fa fa-trash"></i></button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<?php acp_empty(t('acp.menu.empty'), 'fa-bars'); ?>
		<?php endif; ?>
	</div>
</section>
