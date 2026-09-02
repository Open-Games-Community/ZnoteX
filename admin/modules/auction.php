<?php
/**
 * Title: Character Auctions
 * Icon: fa-gavel
 * Group: Economy
 * Order: 20
 * Description: Ongoing, unclaimed and completed character sales.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

$auction          = $config['shop_auction'] ?? [];
$storageAccountId = (int)($auction['storage_account_id'] ?? 0);
$now              = time();

function acp_duration(int $seconds): string {
	if ($seconds <= 0) {
		return '0 seconds';
	}

	$units = [
		'day'    => 86400,
		'hour'   => 3600,
		'minute' => 60,
		'second' => 1,
	];

	$parts = [];
	foreach ($units as $name => $value) {
		$qty = intdiv($seconds, $value);
		if ($qty > 0) {
			$seconds -= $qty * $value;
			$parts[] = $qty . ' ' . ($qty === 1 ? $name : $name . 's');
		}
	}

	return implode(', ', $parts);
}

// ---------------------------------------------------------------------------
// Passive sweep: a bid period that ran out with a bidder on it is a sale.
// ---------------------------------------------------------------------------
$expired = mysql_select_multi("
	SELECT `id`
	FROM `znote_auction_player`
	WHERE `sold` = 0
	  AND `time_end` < {$now}
	  AND `bidder_account_id` > 0;
");

if (is_array($expired) && $expired) {
	$soldIds = array_map(static fn($a) => (int)$a['id'], $expired);
	mysql_update("
		UPDATE `znote_auction_player`
		SET `sold` = 1
		WHERE `id` IN (" . implode(',', $soldIds) . ");
	");
}

// ---------------------------------------------------------------------------
// The three lists
// ---------------------------------------------------------------------------
$characterFields = "
	`za`.`id` AS `zaid`,
	`za`.`price`,
	`za`.`bid`,
	`za`.`time_begin`,
	`za`.`time_end`,
	`p`.`id` AS `player_id`,
	`p`.`name`,
	`p`.`vocation`,
	`p`.`level`
";

$pending = mysql_select_multi("
	SELECT {$characterFields}
	FROM `znote_auction_player` `za`
	INNER JOIN `players` `p` ON `za`.`player_id` = `p`.`id`
	WHERE `p`.`account_id` = {$storageAccountId}
	  AND `za`.`claimed` = 0
	  AND `za`.`sold` = 1
	ORDER BY `za`.`time_end` DESC;
");

$ongoing = mysql_select_multi("
	SELECT {$characterFields}
	FROM `znote_auction_player` `za`
	INNER JOIN `players` `p` ON `za`.`player_id` = `p`.`id`
	WHERE `p`.`account_id` = {$storageAccountId}
	  AND `za`.`sold` = 0
	ORDER BY `za`.`time_end` DESC;
");

$completed = mysql_select_multi("
	SELECT {$characterFields}
	FROM `znote_auction_player` `za`
	INNER JOIN `players` `p` ON `za`.`player_id` = `p`.`id`
	WHERE `za`.`claimed` = 1
	ORDER BY `za`.`time_end` DESC;
");

$pending   = is_array($pending)   ? $pending   : [];
$ongoing   = is_array($ongoing)   ? $ongoing   : [];
$completed = is_array($completed) ? $completed : [];
?>

<div class="acp-stats">
	<?php
	acp_stat('Ongoing', count($ongoing), 'fa-gavel', null, 'blue');
	acp_stat('Sold, unclaimed', count($pending), 'fa-hourglass-half', null, 'amber');
	acp_stat('Completed', count($completed), 'fa-check-circle', null, 'green');
	?>
</div>

<?php if ($storageAccountId <= 0): ?>
	<div class="acp-flash acp-flash--error">
		<i class="fa fa-exclamation-triangle"></i>
		<span>
			<code>$config['shop_auction']['storage_account_id']</code> is not set, so listed
			characters cannot be parked anywhere. The auction system will not work until it is.
		</span>
	</div>
<?php endif; ?>

<!-- ------------------------------------------------------------- Ongoing -->
<section class="acp-card">
	<header class="acp-card-head">
		<h2>Ongoing auctions</h2>
		<p>Listed and still open for bids</p>
	</header>
	<div class="acp-card-body is-flush">
		<?php if ($ongoing): ?>
			<div class="acp-table-wrap">
				<table class="acp-table">
					<thead>
						<tr>
							<th class="is-num">Level</th>
							<th>Vocation</th>
							<th class="is-num">Price</th>
							<th class="is-num">Bid</th>
							<th>Listed</th>
							<th>Type</th>
							<th class="is-num">&nbsp;</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($ongoing as $c):
							$ended = $now > (int)$c['time_end'];
						?>
							<tr>
								<td class="is-num"><?= (int)$c['level'] ?></td>
								<td><?= h(vocation_id_to_name((int)$c['vocation'])) ?></td>
								<td class="is-num"><?= (int)$c['price'] ?></td>
								<td class="is-num"><?= (int)$c['bid'] ?></td>
								<td class="is-nowrap is-muted"><?= h(getClock((int)$c['time_begin'], true)) ?></td>
								<td>
									<?php if ($ended): ?>
										<span class="acp-pill acp-pill--blue">Instant buy</span>
									<?php else: ?>
										<span class="acp-pill acp-pill--amber">Bidding</span>
										<span class="is-muted"><?= h(acp_duration((int)$c['time_end'] - $now)) ?> left</span>
									<?php endif; ?>
								</td>
								<td class="is-num is-nowrap">
									<a class="acp-btn acp-btn--ghost acp-btn--sm"
									   href="<?= h(acp_site('auctionChar.php?action=view&zaid=' . (int)$c['zaid'])) ?>"
									   target="_blank" rel="noopener">
										<i class="fa fa-eye"></i> View
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<?php acp_empty('No characters are currently up for auction.', 'fa-gavel'); ?>
		<?php endif; ?>
	</div>
</section>

<?php
/**
 * Sold/completed lists share the same shape.
 *
 * @param array<int, array<string, mixed>> $rows
 */
function acp_auction_table(array $rows, string $title, string $subtitle, string $emptyText): void {
	?>
	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= h($title) ?></h2>
			<p><?= h($subtitle) ?></p>
		</header>
		<div class="acp-card-body is-flush">
			<?php if (!$rows): ?>
				<?php acp_empty($emptyText, 'fa-archive'); ?>
			<?php else: ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<thead>
							<tr>
								<th>Character</th>
								<th class="is-num">Level</th>
								<th>Vocation</th>
								<th class="is-num">Price</th>
								<th class="is-num">Bid</th>
								<th>Listed</th>
								<th>Ended</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($rows as $c): ?>
								<tr>
									<td class="is-nowrap">
										<a href="<?= h(acp_site('characterprofile.php?name=' . urlencode((string)$c['name']))) ?>" target="_blank" rel="noopener">
											<?= h((string)$c['name']) ?>
										</a>
									</td>
									<td class="is-num"><?= (int)$c['level'] ?></td>
									<td><?= h(vocation_id_to_name((int)$c['vocation'])) ?></td>
									<td class="is-num"><?= (int)$c['price'] ?></td>
									<td class="is-num"><strong><?= (int)$c['bid'] ?></strong></td>
									<td class="is-nowrap is-muted"><?= h(getClock((int)$c['time_begin'], true)) ?></td>
									<td class="is-nowrap is-muted"><?= h(getClock((int)$c['time_end'], true)) ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

acp_auction_table(
	$pending,
	'Sold, waiting to be claimed',
	'The buyer has paid but has not pulled the character onto their account yet',
	'Nothing is waiting to be claimed.'
);

acp_auction_table(
	$completed,
	'Completed auctions',
	'Claimed and closed',
	'No auction has completed yet.'
);
?>

<section class="acp-card">
	<details>
		<summary class="acp-card-head" style="cursor:pointer;">
			<h2>How this works &amp; current configuration</h2>
			<p>From <code>$config['shop_auction']</code></p>
		</summary>
		<div class="acp-card-body">
			<p>
				Players sell, buy and bid on characters for shop points. It deepens the shop
				economy and gives players who cannot afford points a way to earn them by levelling
				characters to sell &mdash; which also pulls trade away from risky third-party
				account sellers, since buying officially supports the server. Prices settle on
				their own against each community's own inflation, with no admin intervention.
			</p>
			<?php data_dump($auction, false, "config.php: shop_auction"); ?>
		</div>
	</details>
</section>
