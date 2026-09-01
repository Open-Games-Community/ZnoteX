<?php
require_once 'engine/init.php';
include 'layout/overall/header.php';

protect_page();
admin_only($user_data);

function h(string $s): string {
	return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function intv($v): int {
	return is_numeric($v) ? (int)$v : 0;
}

$items = getItemList();

$orders = mysql_select_multi("
	SELECT *
	FROM `znote_shop_orders`
	ORDER BY `id` DESC
");

$orderTypesPending = [
	1 => 'Item',
	2 => 'Premium Days',
	3 => 'Gender Change',
	4 => 'Name Change',
	5 => 'Outfits',
	6 => 'Mounts'
];
?>

<h1>Shop Logs</h1>

<h2>Pending Orders</h2>
<p>These are pending orders, like items bought but not received or used yet.</p>

<table>
	<thead>
	<tr class="yellow">
		<th>Id</th>
		<th>Account</th>
		<th>Type</th>
		<th>Item</th>
		<th>Count</th>
		<th>Date</th>
	</tr>
	</thead>
	<tbody>
	<?php if (is_array($orders) && !empty($orders)): ?>
		<?php foreach ($orders as $order):
			$id       = intv($order['id'] ?? 0);
			$accId    = intv($order['account_id'] ?? 0);
			$type     = intv($order['type'] ?? 0);
			$itemId   = intv($order['itemid'] ?? 0);
			$count    = intv($order['count'] ?? 0);
			$time     = intv($order['time'] ?? 0);

			$itemName = $items[$itemId] ?? '';
		?>
		<tr>
			<td><?= $id ?></td>
			<td><?= h(user_account_id_from_name($accId)) ?></td>
			<td><?= h($orderTypesPending[$type] ?? 'Unknown') ?></td>
			<td>
				<?= '(' . $itemId . ')' ?>
				<?= h($itemName) ?>
			</td>
			<td><?= $count ?></td>
			<td><?= h(date('Y/m/d H:i', $time)) ?></td>
		</tr>
		<?php endforeach; ?>
	<?php else: ?>
		<tr>
			<td colspan="6" style="text-align:center;">No pending orders.</td>
		</tr>
	<?php endif; ?>
	</tbody>
</table>

<?php
$orders = mysql_select_multi("
	SELECT *
	FROM `znote_shop_logs`
	ORDER BY `id` DESC
");

$orderTypesHistory = [
	1 => 'Item',
	2 => 'Premium Days',
	3 => 'Gender Change',
	4 => 'Name Change',
	5 => 'Outfit',
	6 => 'Mount',
	7 => 'Custom'
];
?>

<h2>Order History</h2>
<p>This list contains all transactions bought in the shop.</p>

<table>
	<thead>
	<tr class="yellow">
		<th>Id</th>
		<th>Account</th>
		<th>Type</th>
		<th>Item</th>
		<th>Count</th>
		<th>Points</th>
		<th>Date</th>
	</tr>
	</thead>
	<tbody>
	<?php if (is_array($orders) && !empty($orders)): ?>
		<?php foreach ($orders as $order):
			$id       = intv($order['id'] ?? 0);
			$accId    = intv($order['account_id'] ?? 0);
			$type     = intv($order['type'] ?? 0);
			$itemId   = intv($order['itemid'] ?? 0);
			$count    = intv($order['count'] ?? 0);
			$points   = intv($order['points'] ?? 0);
			$time     = intv($order['time'] ?? 0);

			$itemName = $items[$itemId] ?? '';
		?>
		<tr>
			<td><?= $id ?></td>
			<td><?= $accId ?></td>
			<td><?= h($orderTypesHistory[$type] ?? 'Unknown') ?></td>
			<td>
				<?= '(' . $itemId . ')' ?>
				<?= h($itemName) ?>
			</td>
			<td><?= $count ?></td>
			<td><?= $points ?></td>
			<td><?= h(getClock($time, true, false)) ?></td>
		</tr>
		<?php endforeach; ?>
	<?php else: ?>
		<tr>
			<td colspan="7" style="text-align:center;">No order history.</td>
		</tr>
	<?php endif; ?>
	</tbody>
</table>

<?php include 'layout/overall/footer.php'; ?>