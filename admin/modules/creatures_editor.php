<?php
/**
 * Title: Creatures
 * Icon: fa-paw
 * Group: Server Info
 * Order: 13
 * Description: Add or edit a single monster without re-uploading the whole monster set.
 * Hidden: true
 */


if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

$creatures = serverdata_load('creatures');
$creatures = is_array($creatures) ? $creatures : array();

if (!$creatures && !serverdata_override_table_exists()) {
	acp_flash_error(t_default('acp.crted.no_data', 'Upload your monster data on Server Info first - there is nothing published to edit yet.'));
	acp_redirect('serverinfo');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$adminName = (string)($GLOBALS['user_data']['name'] ?? '');
	$action    = (string)($_POST['crted_action'] ?? '');

	if ($action === 'save') {
		$originalName = trim((string)($_POST['original_name'] ?? ''));
		$name         = trim((string)($_POST['name'] ?? ''));

		if ($name === '') {
			acp_flash_error(t_default('acp.crted.missing_fields', 'A name is required.'));
			acp_redirect('creatures_editor');
		}

		$record = array(
			'health'     => max(0, intv($_POST['health'] ?? 0)),
			'experience' => max(0, intv($_POST['experience'] ?? 0)),
			'speed'      => max(0, intv($_POST['speed'] ?? 0)),
			'race'       => trim((string)($_POST['race'] ?? '')),
			'looktype'   => max(0, intv($_POST['looktype'] ?? 0)),
		);

		$ok = serverdata_override_set('creatures', $name, $record, $adminName);

		// Renaming: the old key must stop winning over the base cache too.
		if ($ok && $originalName !== '' && $originalName !== $name) {
			serverdata_override_delete('creatures', $originalName, $adminName);
		}

		if ($ok) {
			acp_log('serverdata.creature_save', $name);
			acp_flash_success(t_default('acp.crted.saved', '{name} saved.', ['name' => $name]));
		} else {
			acp_flash_error(t_default('acp.crted.save_failed', 'Could not save - run the pending database migration first (Admin Panel > Migrations).'));
		}
		acp_redirect('creatures_editor');
	}

	if ($action === 'delete') {
		$name = trim((string)($_POST['name'] ?? ''));
		if (serverdata_override_delete('creatures', $name, $adminName)) {
			acp_log('serverdata.creature_delete', $name);
			acp_flash_success(t_default('acp.crted.removed', '{name} removed.', ['name' => $name]));
		} else {
			acp_flash_error(t_default('acp.crted.remove_failed', 'Could not remove that creature.'));
		}
		acp_redirect('creatures_editor');
	}
}

$editName = trim((string)($_GET['edit'] ?? ''));
$editing  = null;
if ($editName !== '') {
	foreach ($creatures as $c) {
		if (($c['name'] ?? '') === $editName) {
			$editing = $c;
			break;
		}
	}
}

$list = $creatures;
usort($list, static function (array $a, array $b): int {
	return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
});
?>

<section class="acp-card">
	<header class="acp-card-head">
		<h2><?= $editing ? h(t_default('acp.crted.edit_title', 'Edit {name}', ['name' => $editName])) : h(t_default('acp.crted.add_title', 'Add a creature')) ?></h2>
		<p><?= h(t_default('acp.crted.sub', 'Saved separately from the uploaded monster files - a later re-upload will not overwrite this.')) ?></p>
	</header>
	<div class="acp-card-body">
		<form method="post">
			<?= acp_csrf_field() ?>
			<input type="hidden" name="crted_action" value="save">
			<input type="hidden" name="original_name" value="<?= h($editName) ?>">

			<div class="acp-row">
				<div class="acp-field">
					<label class="acp-label" for="crted_name"><?= t_default('acp.crted.field_name', 'Name') ?></label>
					<input class="acp-input" type="text" id="crted_name" name="name" value="<?= h((string)($editing['name'] ?? '')) ?>" required>
				</div>
				<div class="acp-field">
					<label class="acp-label" for="crted_race"><?= t_default('acp.crted.field_race', 'Race') ?></label>
					<input class="acp-input" type="text" id="crted_race" name="race" value="<?= h((string)($editing['race'] ?? '')) ?>" placeholder="blood, venom, undead...">
				</div>
			</div>

			<div class="acp-row">
				<div class="acp-field">
					<label class="acp-label" for="crted_health"><?= t_default('acp.crted.field_health', 'Health') ?></label>
					<input class="acp-input" type="number" min="0" id="crted_health" name="health" value="<?= (int)($editing['health'] ?? 0) ?>">
				</div>
				<div class="acp-field">
					<label class="acp-label" for="crted_experience"><?= t_default('acp.crted.field_experience', 'Experience') ?></label>
					<input class="acp-input" type="number" min="0" id="crted_experience" name="experience" value="<?= (int)($editing['experience'] ?? 0) ?>">
				</div>
				<div class="acp-field">
					<label class="acp-label" for="crted_speed"><?= t_default('acp.crted.field_speed', 'Speed') ?></label>
					<input class="acp-input" type="number" min="0" id="crted_speed" name="speed" value="<?= (int)($editing['speed'] ?? 0) ?>">
				</div>
				<div class="acp-field">
					<label class="acp-label" for="crted_looktype"><?= t_default('acp.crted.field_looktype', 'Looktype') ?></label>
					<input class="acp-input" type="number" min="0" id="crted_looktype" name="looktype" value="<?= (int)($editing['looktype'] ?? 0) ?>">
				</div>
			</div>

			<div class="acp-actions">
				<button class="acp-btn acp-btn--green" type="submit"><i class="fa fa-check"></i> <?= $editing ? t_default('acp.crted.save_btn', 'Save') : t_default('acp.crted.add_btn', 'Add creature') ?></button>
				<?php if ($editing): ?>
					<a class="acp-btn acp-btn--ghost" href="<?= h(acp_url('creatures_editor')) ?>"><?= t_default('acp.crted.cancel', 'Cancel edit') ?></a>
				<?php endif; ?>
			</div>
		</form>
	</div>
</section>

<section class="acp-card">
	<header class="acp-card-head">
		<h2><?= t_default('acp.crted.list_title', 'Published creatures') ?></h2>
	</header>
	<div class="acp-card-body is-flush">
		<?php if ($list): ?>
			<?php if (count($list) > 8): ?>
				<div class="acp-toolbar">
					<div style="display:flex;gap:8px;flex:1 1 320px;max-width:460px;">
						<input class="acp-input" type="search" data-acp-search-input="crtedTable"
							   placeholder="<?= h(t_default('acp.crted.search_placeholder', 'Search creatures...')) ?>">
					</div>
					<span class="is-muted" data-acp-search-count="crtedTable"></span>
				</div>
			<?php endif; ?>
			<div class="acp-table-wrap">
				<table class="acp-table" data-sortable id="crtedTable">
					<thead>
						<tr>
							<th><?= t_default('acp.crted.col_name', 'Name') ?></th>
							<th class="is-num"><?= t_default('acp.crted.col_health', 'Health') ?></th>
							<th class="is-num"><?= t_default('acp.crted.col_experience', 'Experience') ?></th>
							<th><?= t_default('acp.crted.col_race', 'Race') ?></th>
							<th class="is-num"><?= t_default('acp.crted.col_actions', 'Actions') ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($list as $c): ?>
							<?php $cName = (string)($c['name'] ?? ''); ?>
							<tr data-acp-search="<?= h(strtolower($cName)) ?>">
								<td><?= h($cName) ?></td>
								<td class="is-num"><?= (int)($c['health'] ?? 0) ?></td>
								<td class="is-num"><?= (int)($c['experience'] ?? 0) ?></td>
								<td class="is-muted"><?= h((string)($c['race'] ?? '')) ?></td>
								<td class="is-nowrap is-num">
									<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('creatures_editor', array('edit' => $cName))) ?>"><i class="fa fa-pencil"></i></a>
									<form class="acp-inline-form" method="post" data-confirm="<?= h(t_default('acp.crted.confirm_remove', 'Remove this creature?')) ?>">
										<?= acp_csrf_field() ?>
										<input type="hidden" name="crted_action" value="delete">
										<input type="hidden" name="name" value="<?= h($cName) ?>">
										<button class="acp-btn acp-btn--red acp-btn--sm" type="submit"><i class="fa fa-times"></i></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<?php acp_empty(t_default('acp.crted.empty', 'No creatures published yet.'), 'fa-paw'); ?>
		<?php endif; ?>
	</div>
</section>

<p class="acp-hint"><a href="<?= h(acp_url('serverinfo')) ?>"><i class="fa fa-arrow-left"></i> <?= t_default('acp.crted.back', 'Back to Server Info') ?></a></p>
