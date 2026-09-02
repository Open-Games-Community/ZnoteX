<?php
/**
 * Title: Shop Manager
 * Icon: fa-shopping-cart
 * Group: Economy
 * Order: 10
 * Description: Add, preview, hide and remove database shop offers.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

$items = getItemList();

mysql_update("
	CREATE TABLE IF NOT EXISTS `znote_shop_offers` (
		`id` int NOT NULL AUTO_INCREMENT,
		`type` int NOT NULL,
		`itemid` int DEFAULT NULL,
		`count` int NOT NULL DEFAULT '1',
		`description` varchar(255) NOT NULL,
		`points` int NOT NULL DEFAULT '10',
		`active` tinyint NOT NULL DEFAULT '1',
		`sort_order` int NOT NULL DEFAULT '0',
		`created_by` int DEFAULT NULL,
		`created_at` int DEFAULT NULL,
		`updated_at` int DEFAULT NULL,
		PRIMARY KEY (`id`),
		KEY `active_sort` (`active`, `sort_order`, `id`)
	) ENGINE=InnoDB;
");

function acp_shop_offer_columns(): array {
	$columns = [];
	$rows = mysql_select_multi("SHOW COLUMNS FROM `znote_shop_offers`;");

	if (is_array($rows)) {
		foreach ($rows as $row) {
			if (!empty($row['Field'])) {
				$columns[(string)$row['Field']] = true;
			}
		}
	}

	return $columns;
}

function acp_shop_ensure_offer_schema(): void {
	$columns = acp_shop_offer_columns();

	if (empty($columns['active'])) {
		mysql_update("ALTER TABLE `znote_shop_offers` ADD `active` tinyint NOT NULL DEFAULT '1' AFTER `points`;");
		$columns['active'] = true;
	}
	if (empty($columns['sort_order'])) {
		mysql_update("ALTER TABLE `znote_shop_offers` ADD `sort_order` int NOT NULL DEFAULT '0' AFTER `active`;");
		$columns['sort_order'] = true;
	}
	if (empty($columns['updated_at'])) {
		mysql_update("ALTER TABLE `znote_shop_offers` ADD `updated_at` int DEFAULT NULL AFTER `created_at`;");
		$columns['updated_at'] = true;
	}

	$indexes = mysql_select_multi("SHOW INDEX FROM `znote_shop_offers` WHERE `Key_name` = 'active_sort';");
	if (empty($indexes) && !empty($columns['active']) && !empty($columns['sort_order'])) {
		mysql_update("ALTER TABLE `znote_shop_offers` ADD KEY `active_sort` (`active`, `sort_order`, `id`);");
	}
}

acp_shop_ensure_offer_schema();

function acp_shop_pack_outfit_pair(string $raw): int|false {
	if (!preg_match('/^\s*(\d+)\s*,\s*(\d+)\s*$/', $raw, $m)) {
		return false;
	}

	return ((int)$m[1] * 10000) + (int)$m[2];
}

function acp_shop_unpack_outfit_pair(int $packed): string {
	$male = (int)floor($packed / 10000);
	$female = (int)($packed % 10000);

	return $male . ',' . $female;
}

function acp_shop_offer_type_label(int $type): string {
	$types = [
		1 => 'Item',
		2 => 'Premium days',
		3 => 'Gender change',
		4 => 'Name change',
		5 => 'Outfit',
		6 => 'Mount',
		7 => 'Custom',
		8 => 'Custom',
	];

	return $types[$type] ?? 'Custom';
}

function acp_shop_item_image_url(int $itemId): string {
	global $config;

	$server = trim((string)($config['shop']['imageServer'] ?? ''), '/');
	$typeExt = trim((string)($config['shop']['imageType'] ?? 'gif'), '.');

	if ($server === '' || $itemId <= 0) {
		return '';
	}

	if (preg_match('~^https?://~i', $server)) {
		return $server . '/' . $itemId . '.' . $typeExt;
	}

	if (str_contains($server, '.')) {
		return 'http://' . $server . '/' . $itemId . '.' . $typeExt;
	}

	return acp_site($server . '/' . $itemId . '.' . $typeExt);
}

function acp_shop_outfit_server_url(): string {
	global $config;

	$server = trim((string)($config['show_outfits']['imageServer'] ?? ''));
	if ($server === '') {
		return '';
	}

	if (preg_match('~^(https?:)?//~i', $server) || substr($server, 0, 1) === '/') {
		return $server;
	}

	return acp_site($server);
}

function acp_shop_outfit_image_url(int $outfitId, int $addons, int $mountId = 0): string {
	$server = acp_shop_outfit_server_url();
	if ($server === '' || $outfitId <= 0) {
		return '';
	}

	$url = $server
		. '?id=' . $outfitId
		. '&addons=' . $addons
		. '&head=78&body=68&legs=58&feet=76&direction=2';

	if ($mountId > 0) {
		$url .= '&mount=' . $mountId;
	}

	return $url;
}

function acp_shop_offer_preview(array $offer, array $items): string {
	global $config;

	$type = intv($offer['type'] ?? 0);
	$itemId = intv($offer['itemid'] ?? 0);
	$count = intv($offer['count'] ?? 0);

	if ($type === 5 && !empty($config['show_outfits']['imageServer'])) {
		$male = (int)floor($itemId / 10000);
		$female = (int)($itemId % 10000);

		return '<img class="acp-shop-preview-img" src="' . h(acp_shop_outfit_image_url($male, $count)) . '" alt="">'
			. '<img class="acp-shop-preview-img" src="' . h(acp_shop_outfit_image_url($female, $count)) . '" alt="">';
	}

	if ($type === 6 && !empty($config['show_outfits']['imageServer'])) {
		return '<img class="acp-shop-preview-img" src="' . h(acp_shop_outfit_image_url(128, 3, $itemId)) . '" alt="">';
	}

	if (!empty($config['shop']['showImage']) && $itemId > 0) {
		return '<img class="acp-shop-preview-item" src="' . h(acp_shop_item_image_url($itemId)) . '" alt="">';
	}

	return '<span class="is-muted">' . h($items[$itemId] ?? '#' . $itemId) . '</span>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$shopAction = (string)($_POST['shop_offer_action'] ?? '');
	$now = time();

	if ($shopAction === 'add') {
		$type = intv($_POST['type'] ?? 0);
		$itemRaw = trim((string)($_POST['itemid'] ?? ''));
		$count = max(0, intv($_POST['count'] ?? 0));
		$points = max(1, intv($_POST['points'] ?? 0));
		$sortOrder = intv($_POST['sort_order'] ?? 0);
		$description = substr(trim((string)($_POST['description'] ?? '')), 0, 255);
		$itemId = false;

		if ($type === 5) {
			$itemId = acp_shop_pack_outfit_pair($itemRaw);
			if ($itemId === false) {
				acp_flash_error('For outfit offers, use the item field as maleId,femaleId.');
			}
		} elseif (preg_match('/^\d+$/', $itemRaw)) {
			$itemId = (int)$itemRaw;
		}

		if ($type <= 0 || $description === '' || $points <= 0 || $itemId === false) {
			acp_flash_error('Missing or invalid shop offer fields.');
		} else {
			$created = mysql_insert("
				INSERT INTO `znote_shop_offers`
					(`type`, `itemid`, `count`, `description`, `points`, `active`, `sort_order`, `created_by`, `created_at`, `updated_at`)
				VALUES
					({$type}, {$itemId}, {$count}, '" . esc($description) . "', {$points}, 1, {$sortOrder}, " . (int)$user_data['id'] . ", {$now}, {$now});
			");
			if ($created) {
				acp_flash_success('Shop offer added.');
			} else {
				acp_flash_error('Shop offer could not be added. Check the znote_shop_offers table columns.');
			}
			acp_redirect('shop');
		}
	}

	if ($shopAction === 'toggle') {
		$id = intv($_POST['id'] ?? 0);
		$updated = mysql_update("
			UPDATE `znote_shop_offers`
			SET `active` = CASE WHEN `active` = 1 THEN 0 ELSE 1 END,
				`updated_at` = {$now}
			WHERE `id` = {$id}
			LIMIT 1;
		");
		$updated ? acp_flash_success('Shop offer status updated.') : acp_flash_error('Shop offer status could not be updated.');
		acp_redirect('shop');
	}

	if ($shopAction === 'delete') {
		$id = intv($_POST['id'] ?? 0);
		$deleted = mysql_delete("DELETE FROM `znote_shop_offers` WHERE `id` = {$id} LIMIT 1;");
		$deleted ? acp_flash_success('Shop offer removed.') : acp_flash_error('Shop offer could not be removed.');
		acp_redirect('shop');
	}
}

$offers = mysql_select_multi("SELECT * FROM `znote_shop_offers` ORDER BY `active` DESC, `sort_order` ASC, `id` ASC;");
$offers = is_array($offers) ? $offers : [];
$activeOffers = 0;
$hiddenOffers = 0;
foreach ($offers as $offer) {
	!empty($offer['active']) ? $activeOffers++ : $hiddenOffers++;
}

$itemImageTemplate = acp_shop_item_image_url(999999);
if ($itemImageTemplate !== '') {
	$itemImageTemplate = str_replace('999999', '{id}', $itemImageTemplate);
}
?>

<div class="acp-stats">
	<?php
	acp_stat('DB offers', count($offers), 'fa-tags', null, 'blue');
	acp_stat('Active offers', $activeOffers, 'fa-check-circle', null, 'green');
	acp_stat('Hidden offers', $hiddenOffers, 'fa-eye-slash', null, 'amber');
	acp_stat('Shop orders', acp_count("SELECT COUNT(*) AS `c` FROM `znote_shop_orders`;"), 'fa-history', acp_url('shop_orders'), 'purple');
	?>
</div>

<!-- ------------------------------------------------------- Add offer -->
<section class="acp-card acp-shop-form-card">
	<header class="acp-card-head">
		<h2>Add shop offer</h2>
		<p>Saved in znote_shop_offers and shown on the public shop</p>
	</header>
	<div class="acp-card-body">
		<form method="post">
			<?= acp_csrf_field() ?>
			<input type="hidden" name="shop_offer_action" value="add">

			<div class="acp-row acp-shop-form-row">
				<div class="acp-field">
					<label class="acp-label" for="type">Type</label>
					<select class="acp-select" id="type" name="type">
						<option value="1">Item</option>
						<option value="2">Premium days</option>
						<option value="3">Gender change</option>
						<option value="4">Name change</option>
						<option value="5">Outfit (male,female)</option>
						<option value="6">Mount</option>
						<option value="8">Custom</option>
					</select>
				</div>
				<div class="acp-field">
					<label class="acp-label" for="itemid">Item ID</label>
					<div class="acp-shop-id-field">
						<input class="acp-input" id="itemid" name="itemid" placeholder="2160 or 128,136" required>
						<div class="acp-shop-live-preview"
						     id="shopOfferPreview"
						     data-item-template="<?= h($itemImageTemplate) ?>"
						     data-outfit-server="<?= h(acp_shop_outfit_server_url()) ?>">
							<span class="is-muted">Preview</span>
						</div>
					</div>
					<p class="acp-hint">For outfits, use maleId,femaleId.</p>
				</div>
				<div class="acp-field">
					<label class="acp-label" for="description">Description</label>
					<input class="acp-input" id="description" name="description" maxlength="255" placeholder="5 x Crystal coin" required>
				</div>
			</div>

			<div class="acp-row acp-shop-form-row">
				<div class="acp-field">
					<label class="acp-label" for="count">Count / days / addon</label>
					<input class="acp-input" id="count" name="count" type="number" min="0" value="1" required>
				</div>
				<div class="acp-field">
					<label class="acp-label" for="points">Points</label>
					<input class="acp-input" id="points" name="points" type="number" min="1" value="10" required>
				</div>
				<div class="acp-field">
					<label class="acp-label" for="sort_order">Sort order</label>
					<input class="acp-input" id="sort_order" name="sort_order" type="number" value="0">
				</div>
			</div>

			<div class="acp-actions">
				<button class="acp-btn acp-btn--green" type="submit"><i class="fa fa-plus"></i> Add offer</button>
			</div>
		</form>
	</div>
</section>

<!-- ------------------------------------------------------- Current offers -->
<section class="acp-card acp-shop-offers-card">
	<header class="acp-card-head">
		<h2>Database offers</h2>
		<p>Used directly by shop.php</p>
	</header>
	<div class="acp-card-body is-flush">
		<?php if ($offers): ?>
			<div class="acp-table-wrap">
				<table class="acp-table acp-shop-offers-table">
					<thead>
						<tr>
							<th>#</th>
							<th>Preview</th>
							<th>Offer</th>
							<th class="is-num">Points</th>
							<th>Status</th>
							<th class="is-num">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($offers as $offer): ?>
							<tr>
								<td class="is-muted"><?= intv($offer['id'] ?? 0) ?></td>
								<td><?= acp_shop_offer_preview($offer, $items) ?></td>
								<td>
									<strong><?= h($offer['description'] ?? '') ?></strong><br>
									<span class="is-muted">
										<?= h(acp_shop_offer_type_label(intv($offer['type'] ?? 0))) ?> -
										<?= intv($offer['type'] ?? 0) === 5 ? h(acp_shop_unpack_outfit_pair(intv($offer['itemid'] ?? 0))) : intv($offer['itemid'] ?? 0) ?>,
										count <?= intv($offer['count'] ?? 0) ?>
									</span>
								</td>
								<td class="is-num"><strong><?= intv($offer['points'] ?? 0) ?></strong></td>
								<td>
									<span class="acp-pill <?= !empty($offer['active']) ? 'acp-pill--green' : 'acp-pill--grey' ?>">
										<?= !empty($offer['active']) ? 'Active' : 'Hidden' ?>
									</span>
								</td>
								<td class="is-nowrap is-num acp-shop-offer-actions">
									<form class="acp-inline-form" method="post">
										<?= acp_csrf_field() ?>
										<input type="hidden" name="shop_offer_action" value="toggle">
										<input type="hidden" name="id" value="<?= intv($offer['id'] ?? 0) ?>">
										<button class="acp-btn acp-btn--ghost acp-btn--sm" type="submit">
											<?= !empty($offer['active']) ? 'Hide' : 'Show' ?>
										</button>
									</form>
									<form class="acp-inline-form" method="post" data-confirm="Remove this shop offer?">
										<?= acp_csrf_field() ?>
										<input type="hidden" name="shop_offer_action" value="delete">
										<input type="hidden" name="id" value="<?= intv($offer['id'] ?? 0) ?>">
										<button class="acp-btn acp-btn--red acp-btn--sm" type="submit" title="Remove offer" aria-label="Remove offer"><i class="fa fa-times"></i></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<?php acp_empty('No database shop offers yet.', 'fa-tags'); ?>
		<?php endif; ?>
	</div>
</section>
