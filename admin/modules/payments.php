<?php
/**
 * Title: Payments
 * Icon: fa-credit-card
 * Group: Economy
 * Order: 15
 * Description: Gateways, credentials and the point packages players can buy.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

function acp_payments_schema(): array {
	return require __DIR__ . '/_partials/payments_schema.php';
}

function acp_payment_cast(string $type, $raw): string {
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

	$saved = 0;
	$failed = 0;

	if (isset($_POST['pay'])) {
		foreach (acp_payments_schema() as $group) {
			foreach ($group['fields'] as $key => $field) {
				$value = acp_payment_cast($field['type'], $_POST['pay'][$key] ?? '');
				setting_set('config:' . $key, $value) ? $saved++ : $failed++;
			}
		}
	}

	// Price tiers arrive as parallel arrays and are stored as one JSON row,
	// because the whole list is a single config value.
	if (isset($_POST['tier_price']) && is_array($_POST['tier_price'])) {
		$tiers  = array();
		$points = isset($_POST['tier_points']) && is_array($_POST['tier_points']) ? $_POST['tier_points'] : array();

		foreach ($_POST['tier_price'] as $i => $price) {
			$price = intv($price);
			$give  = intv($points[$i] ?? 0);
			if ($price > 0 && $give > 0) {
				$tiers[$price] = $give;
			}
		}

		ksort($tiers, SORT_NUMERIC);
		setting_set('config:paypal_prices', json_encode($tiers, JSON_FORCE_OBJECT)) ? $saved++ : $failed++;
	}

	if ($failed > 0) {
		acp_flash_error($failed . ' value(s) could not be saved. Is the <code>znote_config</code> table present?');
	} else {
		acp_flash_success($saved . ' payment settings saved.');
	}

	acp_redirect('payments');
}

$schema   = acp_payments_schema();
$hasTable = znote_table_exists('znote_config');

$storedTiers = setting('config:paypal_prices', null);
$tiers = null;
if ($storedTiers !== null) {
	$decoded = json_decode($storedTiers, true);
	if (is_array($decoded)) {
		$tiers = $decoded;
	}
}
if ($tiers === null) {
	$tiers = is_array($config['paypal_prices'] ?? null) ? $config['paypal_prices'] : array();
}
ksort($tiers, SORT_NUMERIC);

$perCurrency = (int)znote_config_path($config, 'paypal.points_per_currency', 0);
$currency    = (string)znote_config_path($config, 'paypal.currency', '');
?>

<?php if (!$hasTable): ?>
	<div class="acp-flash acp-flash--error">
		<i class="fa fa-exclamation-triangle"></i>
		<span>The <code>znote_config</code> table is missing, so nothing can be saved.</span>
	</div>
<?php endif; ?>

<div class="acp-flash acp-flash--info">
	<i class="fa fa-info-circle"></i>
	<span>
		Stored in the database and applied over <code>config.php</code>, which stays the fallback.
		Your file is never rewritten. Secrets are held in plain text in <code>znote_config</code>, same
		as they were in <code>config.php</code> &mdash; keep database backups private.
	</span>
</div>

<form method="post">
	<?= acp_csrf_field() ?>

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><i class="fa fa-shopping-cart"></i> Point packages</h2>
			<p>What a player gets for each amount. Used by the Buy Points page.</p>
		</header>
		<div class="acp-card-body">
			<div class="acp-table-wrap">
				<table class="acp-table" id="tierTable">
					<tr class="yellow">
						<td>Price<?= $currency !== '' ? ' (' . h($currency) . ')' : '' ?></td>
						<td>Points</td>
						<td>Bonus</td>
						<td></td>
					</tr>
					<?php $rows = $tiers ?: array('' => ''); ?>
					<?php foreach ($rows as $price => $points): ?>
						<?php
							$bonus = null;
							if ($perCurrency > 0 && (int)$price > 0 && (int)$points > 0) {
								$bonus = round(((int)$points / ((int)$price * $perCurrency) - 1) * 100);
							}
						?>
						<tr>
							<td><input class="acp-input" type="number" min="0" name="tier_price[]" value="<?= h($price) ?>"></td>
							<td><input class="acp-input" type="number" min="0" name="tier_points[]" value="<?= h($points) ?>"></td>
							<td class="is-muted"><?= $bonus === null ? '&mdash;' : ($bonus > 0 ? '+' : '') . (int)$bonus . '%' ?></td>
							<td><button type="button" class="acp-btn acp-btn--ghost" onclick="this.closest('tr').remove()"><i class="fa fa-times"></i></button></td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
			<div class="acp-actions">
				<button type="button" class="acp-btn acp-btn--ghost" onclick="addTier()"><i class="fa fa-plus"></i> Add package</button>
			</div>
			<p class="acp-hint">A row with an empty or zero price is dropped when you save. Bonus is worked out from "Points per unit of currency" below.</p>
		</div>
	</section>

	<div class="acp-grid acp-grid--2">
		<?php foreach ($schema as $groupName => $group): ?>
			<section class="acp-card">
				<header class="acp-card-head">
					<h2><i class="fa <?= h($group['icon']) ?>"></i> <?= h($groupName) ?></h2>
					<?php if (!empty($group['help'])): ?><p><?= h($group['help']) ?></p><?php endif; ?>
				</header>
				<div class="acp-card-body">
					<?php foreach ($group['fields'] as $key => $field): ?>
						<?php
							$stored   = setting('config:' . $key, null);
							$fromFile = znote_config_path($config, $key, '');
							if (is_bool($fromFile)) {
								$fromFile = $fromFile ? '1' : '0';
							} elseif (is_array($fromFile)) {
								$fromFile = '';
							}
							$current = ($stored !== null) ? $stored : (string)$fromFile;
						?>
						<div class="acp-field">
							<label class="acp-label" for="pay_<?= h($key) ?>">
								<?= h($field['label']) ?>
								<?php if ($stored === null): ?>
									<span class="acp-pill acp-pill--grey" title="Currently following config.php">file</span>
								<?php endif; ?>
							</label>

							<?php if ($field['type'] === 'bool'): ?>
								<label style="display:flex;align-items:center;gap:8px;font-weight:400;">
									<input type="checkbox" id="pay_<?= h($key) ?>" name="pay[<?= h($key) ?>]" value="1"
										   <?= ($current !== '' && $current !== '0') ? 'checked' : '' ?>>
									<span class="is-muted">Enabled</span>
								</label>
							<?php else: ?>
								<input class="acp-input" id="pay_<?= h($key) ?>" name="pay[<?= h($key) ?>]"
									   type="<?= $field['type'] === 'int' ? 'number' : ($field['type'] === 'secret' ? 'password' : 'text') ?>"
									   value="<?= h($current) ?>"
									   <?= $field['type'] === 'secret' ? 'autocomplete="new-password"' : '' ?>>
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
		<button class="acp-btn acp-btn--green" type="submit" <?= $hasTable ? '' : 'disabled' ?>>
			<i class="fa fa-check"></i> Save payment settings
		</button>
	</div>
</form>

<script>
function addTier() {
	var table = document.getElementById('tierTable');
	var row = table.insertRow(-1);
	row.innerHTML = '<td><input class="acp-input" type="number" min="0" name="tier_price[]" value=""></td>'
		+ '<td><input class="acp-input" type="number" min="0" name="tier_points[]" value=""></td>'
		+ '<td class="is-muted">&mdash;</td>'
		+ '<td><button type="button" class="acp-btn acp-btn--ghost" onclick="this.closest(\'tr\').remove()"><i class="fa fa-times"></i></button></td>';
}
</script>
