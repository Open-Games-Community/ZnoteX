<?php
/**
 * Title: Coupons
 * Icon: fa-ticket
 * Group: Economy
 * Order: 60
 * Description: Create and track redeemable shop codes.
 */

/*
 * A plugin's admin module is written exactly like a built-in one: the docblock
 * above IS the menu entry, and every acp_* helper is available. The only
 * difference is where it lives - admin/index.php finds it through the plugin
 * registry, and its key is namespaced (shop_coupons__coupons) so a plugin can
 * never shadow a core module by picking the same filename.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

$module = 'shop_coupons__coupons';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	if (isset($_POST['delete'])) {
		$id = intv($_POST['delete']);
		mysql_delete("DELETE FROM `znote_coupons` WHERE `id` = {$id} LIMIT 1;");
		mysql_delete("DELETE FROM `znote_coupon_uses` WHERE `coupon_id` = {$id};");
		acp_flash_success('Coupon deleted.');
		acp_redirect($module);
	}

	$code  = shop_coupons_normalise((string)($_POST['code'] ?? ''));
	$kind  = ($_POST['kind'] ?? 'points') === 'percent' ? 'percent' : 'points';
	$value = intv($_POST['value'] ?? 0);
	$max   = max(0, intv($_POST['uses_max'] ?? 1));
	$days  = max(0, intv($_POST['days'] ?? 0));

	if ($code === '') {
		acp_flash_error('A code needs at least one letter or digit.');
	} elseif ($value <= 0) {
		acp_flash_error('The value has to be greater than zero.');
	} elseif ($kind === 'percent' && $value > 100) {
		acp_flash_error('A discount cannot exceed 100%.');
	} else {
		$expires = $days > 0 ? time() + ($days * 86400) : 0;

		$created = mysql_insert("
			INSERT INTO `znote_coupons` (`code`, `kind`, `value`, `uses_max`, `uses_done`, `expires_at`, `created_at`)
			VALUES ('" . esc($code) . "', '" . esc($kind) . "', {$value}, {$max}, 0, {$expires}, " . time() . ");
		");

		// The unique key on `code` is what rejects a duplicate, so there is no
		// window between checking and inserting.
		if ($created === false) {
			acp_flash_error('That code already exists.');
		} else {
			acp_flash_success('Coupon ' . $code . ' created.');
		}
	}

	acp_redirect($module);
}

$coupons = mysql_select_multi("
	SELECT `id`, `code`, `kind`, `value`, `uses_max`, `uses_done`, `expires_at`, `created_at`
	FROM `znote_coupons`
	ORDER BY `id` DESC
	LIMIT 200;
");
$coupons = is_array($coupons) ? $coupons : array();

$now = time();
?>

<div class="acp-grid">
	<?php
	acp_stat('Coupons', count($coupons), 'fa-ticket', null, 'blue');
	acp_stat('Redemptions', acp_count("SELECT COUNT(*) AS `c` FROM `znote_coupon_uses`;"), 'fa-check', null, 'green');
	acp_stat('Discounts waiting', acp_count("SELECT COUNT(*) AS `c` FROM `znote_coupon_discounts` WHERE `spent_at` = 0;"), 'fa-percent', null, 'orange');
	?>
</div>

<?php acp_card_open('New coupon', 'Players redeem it at ' . znote_plugin_url('shop_coupons', 'redeem')); ?>
	<form method="post">
		<?= acp_csrf_field() ?>
		<div class="acp-row">
			<div class="acp-field">
				<label class="acp-label" for="code">Code</label>
				<input class="acp-input" id="code" name="code" maxlength="32" placeholder="SUMMER2026" style="text-transform:uppercase;" required>
			</div>
			<div class="acp-field">
				<label class="acp-label" for="kind">Type</label>
				<select class="acp-input" id="kind" name="kind">
					<option value="points">Credit shop points</option>
					<option value="percent">Percent off next purchase</option>
				</select>
			</div>
			<div class="acp-field">
				<label class="acp-label" for="value">Value</label>
				<input class="acp-input" id="value" name="value" type="number" min="1" value="50">
				<span class="acp-hint">Points credited, or the percentage taken off.</span>
			</div>
		</div>
		<div class="acp-row">
			<div class="acp-field">
				<label class="acp-label" for="uses_max">Total uses</label>
				<input class="acp-input" id="uses_max" name="uses_max" type="number" min="0" value="1">
				<span class="acp-hint">0 for unlimited. Each account can still only use it once.</span>
			</div>
			<div class="acp-field">
				<label class="acp-label" for="days">Valid for</label>
				<input class="acp-input" id="days" name="days" type="number" min="0" value="0">
				<span class="acp-hint">Days. 0 never expires.</span>
			</div>
		</div>
		<div class="acp-actions">
			<button class="acp-btn" type="submit"><i class="fa fa-plus"></i> Create coupon</button>
		</div>
	</form>
<?php acp_card_close(); ?>

<?php acp_card_open('Coupons', count($coupons) . ' most recent'); ?>
	<?php if (!$coupons): ?>
		<?php acp_empty('No coupons yet. Create one above.', 'fa-ticket'); ?>
	<?php else: ?>
		<table class="acp-table">
			<thead>
				<tr>
					<th>Code</th>
					<th>Gives</th>
					<th>Used</th>
					<th>Expires</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($coupons as $coupon):
				$expires = (int)$coupon['expires_at'];
				$max     = (int)$coupon['uses_max'];
				$done    = (int)$coupon['uses_done'];
				$dead    = ($expires > 0 && $expires < $now) || ($max > 0 && $done >= $max);
			?>
				<tr<?= $dead ? ' style="opacity:.55;"' : '' ?>>
					<td><code><?= h($coupon['code']) ?></code></td>
					<td>
						<?= $coupon['kind'] === 'percent'
							? (int)$coupon['value'] . '% off'
							: (int)$coupon['value'] . ' points' ?>
					</td>
					<td><?= $done ?> / <?= $max > 0 ? $max : '&infin;' ?></td>
					<td><?= $expires > 0 ? h(date('Y-m-d', $expires)) : 'never' ?></td>
					<td style="text-align:right;">
						<form method="post" onsubmit="return confirm('Delete <?= h($coupon['code']) ?>?');" style="display:inline;">
							<?= acp_csrf_field() ?>
							<input type="hidden" name="delete" value="<?= (int)$coupon['id'] ?>">
							<button class="acp-btn acp-btn--red acp-btn--sm" type="submit"><i class="fa fa-trash"></i></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
<?php acp_card_close(); ?>
