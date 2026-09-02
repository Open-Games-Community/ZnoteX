<?php
/**
 * Title: Shop Pending / History
 * Icon: fa-history
 * Group: Economy
 * Order: 15
 * Description: Pending shop deliveries and completed purchase history.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

$items = getItemList();

$pending = mysql_select_multi("SELECT * FROM `znote_shop_orders` ORDER BY `id` DESC;");
$history = mysql_select_multi("SELECT * FROM `znote_shop_logs` ORDER BY `id` DESC;");

$pending = is_array($pending) ? $pending : [];
$history = is_array($history) ? $history : [];

$orderTypes = [
	1 => 'Item',
	2 => 'Premium days',
	3 => 'Gender change',
	4 => 'Name change',
	5 => 'Outfit',
	6 => 'Mount',
	7 => 'Custom',
	8 => 'Custom',
];

$accountIds = [];
foreach ([$pending, $history] as $set) {
	foreach ($set as $order) {
		$id = intv($order['account_id'] ?? 0);
		if ($id > 0) {
			$accountIds[$id] = true;
		}
	}
}

$accountNames = [];
if ($accountIds) {
	$isOthire = ($config['ServerEngine'] === 'OTHIRE');
	$nameColumn = $isOthire ? '`id`' : '`name`';

	$rows = mysql_select_multi("
		SELECT `id`, {$nameColumn} AS `account_name`
		FROM `accounts`
		WHERE `id` IN (" . implode(',', array_map('intval', array_keys($accountIds))) . ");
	");

	if (is_array($rows)) {
		foreach ($rows as $row) {
			$accountNames[(int)$row['id']] = (string)$row['account_name'];
		}
	}
}

function acp_shop_orders_account(int $id, array $names): string {
	return isset($names[$id])
		? h($names[$id]) . ' <span class="is-muted">#' . $id . '</span>'
		: '<span class="is-muted">deleted account #' . $id . '</span>';
}

$historyPoints = 0;
foreach ($history as $order) {
	$historyPoints += intv($order['points'] ?? 0);
}
?>

<div class="acp-stats">
	<?php
	acp_stat('Pending delivery', count($pending), 'fa-hourglass-half', null, 'amber');
	acp_stat('Completed orders', count($history), 'fa-check-circle', null, 'green');
	acp_stat('Points spent', $historyPoints, 'fa-diamond', null, 'purple');
	acp_stat('Manage offers', 'Open', 'fa-tags', acp_url('shop'), 'blue');
	?>
</div>

<section class="acp-card">
	<header class="acp-card-head">
		<h2>Pending orders</h2>
		<p>Bought but not yet received in game</p>
	</header>
	<div class="acp-card-body is-flush">
		<?php if ($pending): ?>
			<div class="acp-table-wrap">
				<table class="acp-table">
					<thead>
						<tr>
							<th>#</th>
							<th>Account</th>
							<th>Type</th>
							<th>Item</th>
							<th class="is-num">Count</th>
							<th>Ordered</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($pending as $order):
							$itemId = intv($order['itemid'] ?? 0);
						?>
							<tr>
								<td class="is-muted"><?= intv($order['id'] ?? 0) ?></td>
								<td class="is-nowrap"><?= acp_shop_orders_account(intv($order['account_id'] ?? 0), $accountNames) ?></td>
								<td><span class="acp-pill acp-pill--blue"><?= h($orderTypes[intv($order['type'] ?? 0)] ?? 'Unknown') ?></span></td>
								<td>
									<?php if ($itemId > 0): ?>
										<?= h($items[$itemId] ?? 'Unknown item') ?>
										<span class="is-muted">(<?= $itemId ?>)</span>
									<?php else: ?>
										<span class="is-muted">&mdash;</span>
									<?php endif; ?>
								</td>
								<td class="is-num"><?= intv($order['count'] ?? 0) ?></td>
								<td class="is-nowrap is-muted"><?= h(getClock(intv($order['time'] ?? 0), true)) ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<?php acp_empty('Nothing is waiting to be delivered.', 'fa-check-circle'); ?>
		<?php endif; ?>
	</div>
</section>

<section class="acp-card">
	<header class="acp-card-head">
		<h2>Order history</h2>
		<p>Every transaction that went through the shop</p>
	</header>
	<div class="acp-card-body is-flush">
		<?php if ($history): ?>
			<div class="acp-table-wrap">
				<table class="acp-table">
					<thead>
						<tr>
							<th>#</th>
							<th>Account</th>
							<th>Type</th>
							<th>Item</th>
							<th class="is-num">Count</th>
							<th class="is-num">Points</th>
							<th>Date</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($history as $order):
							$itemId = intv($order['itemid'] ?? 0);
						?>
							<tr>
								<td class="is-muted"><?= intv($order['id'] ?? 0) ?></td>
								<td class="is-nowrap"><?= acp_shop_orders_account(intv($order['account_id'] ?? 0), $accountNames) ?></td>
								<td><span class="acp-pill acp-pill--grey"><?= h($orderTypes[intv($order['type'] ?? 0)] ?? 'Unknown') ?></span></td>
								<td>
									<?php if ($itemId > 0): ?>
										<?= h($items[$itemId] ?? 'Unknown item') ?>
										<span class="is-muted">(<?= $itemId ?>)</span>
									<?php else: ?>
										<span class="is-muted">&mdash;</span>
									<?php endif; ?>
								</td>
								<td class="is-num"><?= intv($order['count'] ?? 0) ?></td>
								<td class="is-num"><strong><?= intv($order['points'] ?? 0) ?></strong></td>
								<td class="is-nowrap is-muted"><?= h(getClock(intv($order['time'] ?? 0), true, false)) ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<?php acp_empty('No shop purchases have been made yet.', 'fa-shopping-cart'); ?>
		<?php endif; ?>
	</div>
</section>
