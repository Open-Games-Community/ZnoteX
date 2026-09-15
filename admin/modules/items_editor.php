<?php
/**
 * Title: Items
 * Icon: fa-shield
 * Group: Server Info
 * Order: 12
 * Description: Add or edit a single item without re-uploading the whole items.xml.
 * Hidden: true
 */


if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

const ITMED_TYPE = 'item';

/** Stored attribute map -> editable {key, value} rows. */
function itmed_attr_rows(array $attributes): array {
	$rows = array();
	foreach ($attributes as $key => $value) {
		$rows[] = array('key' => (string)$key, 'value' => is_scalar($value) ? (string)$value : '');
	}
	return $rows;
}

/** Posted {key, value} rows -> attribute map, blank/duplicate keys dropped. */
function itmed_attr_map_from_post(array $rowsRaw): array {
	$map = array();
	foreach ($rowsRaw as $row) {
		if (!is_array($row)) continue;
		$key = trim((string)($row['key'] ?? ''));
		if ($key === '') continue;
		$map[$key] = trim((string)($row['value'] ?? ''));
	}
	return $map;
}

$items = serverdata_load('items');
$items = is_array($items) ? $items : array();

if (!$items && !serverdata_override_table_exists()) {
	acp_flash_error(t_default('acp.itmed.no_data', 'Upload an items.xml on Server Info first - there is nothing published to edit yet.'));
	acp_redirect('serverinfo');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$adminName = (string)($GLOBALS['user_data']['name'] ?? '');
	$action    = (string)($_POST['itmed_action'] ?? '');

	if ($action === 'save') {
		$id = intv($_POST['id'] ?? 0);
		$name = trim((string)($_POST['name'] ?? ''));

		if ($id <= 0 || $name === '') {
			acp_flash_error(t_default('acp.itmed.missing_fields', 'ID and name are required.'));
			acp_redirect('items_editor');
		}

		$record = array(
			'id'         => (string)$id,
			'name'       => $name,
			'attributes' => itmed_attr_map_from_post((array)($_POST['attr'] ?? array())),
		);

		if (serverdata_override_set('items', ITMED_TYPE . ':' . $id, $record, $adminName)) {
			acp_log('serverdata.item_save', $name, ['type' => ITMED_TYPE, 'id' => $id]);
			acp_flash_success(t_default('acp.itmed.saved', 'Item #{id} saved.', ['id' => $id]));
		} else {
			acp_flash_error(t_default('acp.itmed.save_failed', 'Could not save - run the pending database migration first (Admin Panel > Migrations).'));
		}
		acp_redirect('items_editor');
	}

	if ($action === 'delete') {
		$id = intv($_POST['id'] ?? 0);
		if (serverdata_override_delete('items', ITMED_TYPE . ':' . $id, $adminName)) {
			acp_log('serverdata.item_delete', '#' . $id, ['type' => ITMED_TYPE, 'id' => $id]);
			acp_flash_success(t_default('acp.itmed.removed', 'Item #{id} removed.', ['id' => $id]));
		} else {
			acp_flash_error(t_default('acp.itmed.remove_failed', 'Could not remove that item.'));
		}
		acp_redirect('items_editor');
	}
}

$editId  = intv($_GET['edit'] ?? 0);
$editing = null;
if ($editId > 0 && isset($items[ITMED_TYPE][(string)$editId])) {
	$editing = $items[ITMED_TYPE][(string)$editId];
}

$attrRows = itmed_attr_rows($editing['attributes'] ?? array());

$attrColumns = array(
	'key'   => array('label' => t_default('acp.itmed.col_attr_key', 'Attribute'), 'type' => 'text'),
	'value' => array('label' => t_default('acp.itmed.col_attr_value', 'Value'), 'type' => 'text'),
);

$list = $items[ITMED_TYPE] ?? array();
ksort($list, SORT_NUMERIC);
?>

<section class="acp-card">
	<header class="acp-card-head">
		<h2><?= $editing ? h(t_default('acp.itmed.edit_title', 'Edit item #{id}', ['id' => $editId])) : h(t_default('acp.itmed.add_title', 'Add an item')) ?></h2>
		<p><?= h(t_default('acp.itmed.sub', 'Saved separately from items.xml - a later re-upload will not overwrite this.')) ?></p>
	</header>
	<div class="acp-card-body">
		<form method="post">
			<?= acp_csrf_field() ?>
			<input type="hidden" name="itmed_action" value="save">

			<div class="acp-row">
				<div class="acp-field">
					<label class="acp-label" for="itmed_id"><?= t_default('acp.itmed.field_id', 'Item ID') ?></label>
					<input class="acp-input" type="number" min="1" id="itmed_id" name="id" value="<?= $editing ? (int)$editId : '' ?>" <?= $editing ? 'readonly' : '' ?> required>
				</div>
				<div class="acp-field">
					<label class="acp-label" for="itmed_name"><?= t_default('acp.itmed.field_name', 'Name') ?></label>
					<input class="acp-input" type="text" id="itmed_name" name="name" value="<?= h((string)($editing['name'] ?? '')) ?>" required>
				</div>
			</div>

			<div class="acp-field">
				<label class="acp-label"><?= t_default('acp.itmed.field_attrs', 'Attributes') ?></label>
				<table class="acp-table acp-table--editable" data-acp-table="itmedAttrs">
					<thead>
						<tr>
							<?php foreach ($attrColumns as $colDef): ?>
								<th><?= h($colDef['label']) ?></th>
							<?php endforeach; ?>
							<th></th>
						</tr>
					</thead>
					<tbody data-acp-table-body>
						<?php foreach ($attrRows as $i => $row): ?>
							<tr>
								<?php foreach ($attrColumns as $colKey => $colDef): ?>
									<td><?= acp_table_cell_input($colDef, 'attr[' . (int)$i . '][' . $colKey . ']', (string)($row[$colKey] ?? '')) ?></td>
								<?php endforeach; ?>
								<td><button type="button" class="acp-btn acp-btn--sm acp-btn--red" data-acp-table-remove>&times;</button></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<button type="button" class="acp-btn acp-btn--sm" data-acp-table-add="itmedAttrs">
					<i class="fa fa-plus"></i> <?= t_default('acp.itmed.add_attr', 'Add attribute') ?>
				</button>
				<template data-acp-table-template="itmedAttrs">
					<tr>
						<?php foreach ($attrColumns as $colKey => $colDef): ?>
							<td><?= acp_table_cell_input($colDef, 'attr[__ROWIDX__][' . $colKey . ']', '') ?></td>
						<?php endforeach; ?>
						<td><button type="button" class="acp-btn acp-btn--sm acp-btn--red" data-acp-table-remove>&times;</button></td>
					</tr>
				</template>
			</div>

			<div class="acp-actions">
				<button class="acp-btn acp-btn--green" type="submit"><i class="fa fa-check"></i> <?= $editing ? t_default('acp.itmed.save_btn', 'Save') : t_default('acp.itmed.add_btn', 'Add item') ?></button>
				<?php if ($editing): ?>
					<a class="acp-btn acp-btn--ghost" href="<?= h(acp_url('items_editor')) ?>"><?= t_default('acp.itmed.cancel', 'Cancel edit') ?></a>
				<?php endif; ?>
			</div>
		</form>
	</div>
</section>

<section class="acp-card">
	<header class="acp-card-head">
		<h2><?= t_default('acp.itmed.list_title', 'Published items') ?></h2>
	</header>
	<div class="acp-card-body is-flush">
		<?php if ($list): ?>
			<?php if (count($list) > 8): ?>
				<div class="acp-toolbar">
					<div style="display:flex;gap:8px;flex:1 1 320px;max-width:460px;">
						<input class="acp-input" type="search" data-acp-search-input="itmedTable"
							   placeholder="<?= h(t_default('acp.itmed.search_placeholder', 'Search items...')) ?>">
					</div>
					<span class="is-muted" data-acp-search-count="itmedTable"></span>
				</div>
			<?php endif; ?>
			<div class="acp-table-wrap">
				<table class="acp-table" data-sortable id="itmedTable">
					<thead>
						<tr>
							<th>ID</th>
							<th><?= t_default('acp.itmed.col_name', 'Name') ?></th>
							<th class="is-num"><?= t_default('acp.itmed.col_actions', 'Actions') ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($list as $id => $rec): ?>
							<tr data-acp-search="<?= h(strtolower($id . ' ' . (string)($rec['name'] ?? ''))) ?>">
								<td class="is-muted"><?= h((string)$id) ?></td>
								<td><?= h((string)($rec['name'] ?? '')) ?></td>
								<td class="is-nowrap is-num">
									<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('items_editor', array('edit' => $id))) ?>"><i class="fa fa-pencil"></i></a>
									<form class="acp-inline-form" method="post" data-confirm="<?= h(t_default('acp.itmed.confirm_remove', 'Remove this item?')) ?>">
										<?= acp_csrf_field() ?>
										<input type="hidden" name="itmed_action" value="delete">
										<input type="hidden" name="id" value="<?= h((string)$id) ?>">
										<button class="acp-btn acp-btn--red acp-btn--sm" type="submit"><i class="fa fa-times"></i></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<?php acp_empty(t_default('acp.itmed.empty', 'No items published yet.'), 'fa-shield'); ?>
		<?php endif; ?>
	</div>
</section>

<p class="acp-hint"><a href="<?= h(acp_url('serverinfo')) ?>"><i class="fa fa-arrow-left"></i> <?= t_default('acp.itmed.back', 'Back to Server Info') ?></a></p>
