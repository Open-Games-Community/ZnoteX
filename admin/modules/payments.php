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
				if (($field['type'] ?? '') === 'secret' && trim((string)($_POST['pay'][$key] ?? '')) === '') {
					continue;
				}
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
		acp_flash_error(t('acp.pay.save_failed', ['n' => $failed, 'table' => '<code>znote_config</code>']));
	} else {
		acp_flash_success(t('acp.pay.save_success', ['n' => $saved]));
	}

	acp_redirect('payments');
}

$schema   = acp_payments_schema();
$hasTable = znote_table_exists('znote_config');

if (function_exists('payment_gateway_ensure_schema')) {
	payment_gateway_ensure_schema();
}

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
$modernPayments = znote_table_exists('znote_payment_transactions')
	? mysql_select_multi("
		SELECT `provider`, `reference`, `provider_reference`, `account_id`, `price`, `currency`, `points`, `status`, `credited`, `test_mode`, `created_at`, `credited_at`
		FROM `znote_payment_transactions`
		ORDER BY `id` DESC
		LIMIT 10;
	")
	: false;
?>

<?php if (!$hasTable): ?>
	<div class="acp-flash acp-flash--error">
		<i class="fa fa-exclamation-triangle"></i>
		<span><?= t('acp.pay.table_missing_short', ['table' => '<code>znote_config</code>']) ?></span>
	</div>
<?php endif; ?>

<div class="acp-flash acp-flash--info">
	<i class="fa fa-info-circle"></i>
	<span>
		<?= t('acp.pay.stored_note', [
			'configphp'   => '<code>config.php</code>',
			'znoteconfig' => '<code>znote_config</code>',
			'configphp2'  => '<code>config.php</code>',
		]) ?>
	</span>
</div>

<section class="acp-card">
	<header class="acp-card-head">
		<h2><i class="fa fa-link"></i> Webhook URLs</h2>
		<p>Configure these URLs in Stripe and Mercado Pago. Return pages do not credit points.</p>
	</header>
	<div class="acp-card-body">
		<div class="acp-field">
			<label class="acp-label">Stripe</label>
			<input class="acp-input" type="text" readonly value="<?= h(function_exists('payment_gateway_webhook_url') ? payment_gateway_webhook_url('stripe') : '') ?>">
		</div>
		<div class="acp-field">
			<label class="acp-label">Mercado Pago</label>
			<input class="acp-input" type="text" readonly value="<?= h(function_exists('payment_gateway_webhook_url') ? payment_gateway_webhook_url('mercadopago') : '') ?>">
		</div>
	</div>
</section>

<form method="post">
	<?= acp_csrf_field() ?>

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><i class="fa fa-shopping-cart"></i> <?= t('acp.pay.tiers_title') ?></h2>
			<p><?= t('acp.pay.tiers_sub') ?></p>
		</header>
		<div class="acp-card-body">
			<div class="acp-table-wrap">
				<table class="acp-table" id="tierTable">
					<tr class="yellow">
						<td><?= t('acp.pay.col_price') ?><?= $currency !== '' ? ' (' . h($currency) . ')' : '' ?></td>
						<td><?= t('acp.pay.col_points') ?></td>
						<td><?= t('acp.pay.col_bonus') ?></td>
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
				<button type="button" class="acp-btn acp-btn--ghost" onclick="addTier()"><i class="fa fa-plus"></i> <?= t('acp.pay.add_package') ?></button>
			</div>
			<p class="acp-hint"><?= t('acp.pay.tiers_hint') ?></p>
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
							$isSecret = ($field['type'] ?? '') === 'secret';
						?>
						<div class="acp-field">
							<label class="acp-label" for="pay_<?= h($key) ?>">
								<?= h($field['label']) ?>
								<?php if ($stored === null): ?>
									<span class="acp-pill acp-pill--grey" title="<?= h(t('acp.pay.following_title')) ?>"><?= t('acp.pay.file_pill') ?></span>
								<?php endif; ?>
							</label>

							<?php if ($field['type'] === 'bool'): ?>
								<label style="display:flex;align-items:center;gap:8px;font-weight:400;">
									<input type="checkbox" id="pay_<?= h($key) ?>" name="pay[<?= h($key) ?>]" value="1"
										   <?= ($current !== '' && $current !== '0') ? 'checked' : '' ?>>
									<span class="is-muted"><?= t('acp.pay.enabled') ?></span>
								</label>
							<?php else: ?>
								<input class="acp-input" id="pay_<?= h($key) ?>" name="pay[<?= h($key) ?>]"
									   type="<?= $field['type'] === 'int' ? 'number' : ($field['type'] === 'secret' ? 'password' : 'text') ?>"
									   value="<?= $isSecret ? '' : h($current) ?>"
									   <?= $isSecret ? 'autocomplete="new-password" placeholder="' . h($current !== '' ? 'Configured - leave blank to keep' : '') . '"' : '' ?>>
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
			<i class="fa fa-check"></i> <?= t('acp.pay.save_btn') ?>
		</button>
	</div>
</form>

<section class="acp-card">
	<header class="acp-card-head">
		<h2><i class="fa fa-history"></i> Recent Stripe / Mercado Pago Transactions</h2>
		<p>Credited means the webhook has passed signature and provider verification.</p>
	</header>
	<div class="acp-card-body">
		<?php if (is_array($modernPayments) && $modernPayments): ?>
			<div class="acp-table-wrap">
				<table class="acp-table">
					<thead><tr><th>Provider</th><th>Account</th><th>Amount</th><th class="is-num">Points</th><th>Status</th><th>Created</th><th>Credited</th></tr></thead>
					<tbody>
						<?php foreach ($modernPayments as $row): ?>
							<tr>
								<td><?= h(ucfirst((string)$row['provider'])) ?><?= !empty($row['test_mode']) ? ' <span class="acp-pill acp-pill--grey">test</span>' : '' ?></td>
								<td class="is-num"><?= (int)$row['account_id'] ?></td>
								<td><?= h(number_format((float)$row['price'], 2, '.', '')) ?> <?= h($row['currency']) ?></td>
								<td class="is-num"><?= (int)$row['points'] ?></td>
								<td><?= h($row['status']) ?><?= !empty($row['credited']) ? ' <span class="acp-pill acp-pill--green">credited</span>' : '' ?></td>
								<td><?= !empty($row['created_at']) ? date('Y-m-d H:i', (int)$row['created_at']) : '-' ?></td>
								<td><?= !empty($row['credited_at']) ? date('Y-m-d H:i', (int)$row['credited_at']) : '-' ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<?php acp_empty('No Stripe or Mercado Pago transaction yet.', 'fa-credit-card'); ?>
		<?php endif; ?>
	</div>
</section>

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
