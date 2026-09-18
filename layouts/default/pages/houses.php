<?php
/**
 * Houses list.
 *
 * Loaded directly by the root houses.php (theme_file('pages/houses.php')),
 * not through page.php - so this file opens and closes the theme itself.
 */
$h = static function ($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

theme_open();

if ($config['log_ip'])
	znote_visitor_insert_detailed_data(3);

$querystring_id = &$_GET['id'];
$townid = ($querystring_id) ? (int)$_GET['id'] : $config['houseConfig']['HouseListDefaultTown'];
$towns = $config['towns'];

$order = &$_GET['order'];
$type = &$_GET['type'];
?>
<div class="znx-acct">

<div class="znx-acct-head"><?= t('common.town') ?></div>

<form action="" method="get" class="znx-acct-toolbar">
	<div class="znx-acct-toolbar__field">
	<select name="id" class="znx-acct-select">
		<?php foreach ($towns as $id => $name): ?>
			<option value="<?= $h($id) ?>" <?= $townid == $id ? 'selected' : '' ?>><?= $h($name) ?></option>
		<?php endforeach; ?>
	</select>
	</div>

	<?php
	$order_allowed = array('id', 'name', 'size', 'beds', 'rent', 'owner');
	$order_labels = array('id' => 'ID', 'name' => t('common.name'), 'size' => t('house.size'), 'beds' => t('house.beds'), 'rent' => t('house.rent'), 'owner' => t('house.owner'));
	?>
	<div class="znx-acct-toolbar__field">
	<select name="order" class="znx-acct-select">
		<?php foreach ($order_allowed as $o): ?>
			<option value="<?= $h($o) ?>" <?= $o == $order ? 'selected' : '' ?>><?= $h($order_labels[$o]) ?></option>
		<?php endforeach; ?>
	</select>
	</div>

	<?php $type_allowed = array('desc', 'asc'); ?>
	<div class="znx-acct-toolbar__field">
	<select name="type" class="znx-acct-select">
		<?php foreach ($type_allowed as $t): ?>
			<option value="<?= $h($t) ?>" <?= $t == $type ? 'selected' : '' ?>><?= $t == 'desc' ? t('houses.descending') : t('houses.ascending') ?></option>
		<?php endforeach; ?>
	</select>
	</div>

	<div class="znx-acct-toolbar__submit"><button type="submit" class="znx-acct-btn"><?= t('houses.fetch') ?></button></div>
</form>

<?php
if (!in_array($order, $order_allowed))
	$order = 'id';

if (!in_array($type, $type_allowed))
	$type = 'desc';

$cache = new Cache('engine/cache/houses/houses-' . $order . '-' . $type);
$houses = array();

if ($cache->hasExpired()) {

	$houses = db()->fetchAll("
		SELECT
			`id`, `owner`, `paid`, `warnings`, `name`, `rent`, `town_id`,
			`size`, `beds`, " . houseSelect(array('bid','bid_end','last_bid','highest_bidder')) . "
		FROM `houses`
		ORDER BY {$order} {$type};
	");

	if ($houses !== false) {
		$playerlist = array();

		foreach ($houses as $houseRow)
			if ($houseRow['owner'] > 0)
				$playerlist[] = (int)$houseRow['owner'];

		if (!empty($playerlist)) {
			$placeholders = implode(',', array_fill(0, count($playerlist), '?'));
			$tmpPlayers = db()->fetchAll("SELECT `id`, `name` FROM players WHERE `id` IN ($placeholders);", $playerlist);

			$tmpById = array();
			foreach ($tmpPlayers as $p)
				$tmpById[$p['id']] = $p['name'];

			for ($i = 0; $i < count($houses); $i++)
				if ($houses[$i]['owner'] > 0)
					$houses[$i]['ownername'] = $tmpById[$houses[$i]['owner']];
		}

		$cache->setContent($houses);
		$cache->save();
	}
} else
	$houses = $cache->load();

if ($houses !== false || !empty($houses)):
	?>
	<div class="znx-acct-table-wrap">
	<table id="housetable" class="znx-acct-table">
		<tr class="yellow">
			<th><?= t('common.name') ?></th>
			<th><?= t('house.size') ?></th>
			<th><?= t('house.beds') ?></th>
			<th><?= t('house.rent') ?></th>
			<th><?= t('house.owner') ?></th>
			<th><?= t('common.town') ?></th>
		</tr>
		<?php foreach ($houses as $house): ?>
			<?php if ($house['town_id'] == $townid): ?>
				<?php $town_name = $towns[$house['town_id']] ?? null; ?>
				<tr>
					<td><a href="house.php?id=<?= (int) $house['id'] ?>"><?= $h($house['name']) ?></a></td>
					<td><?= (int) $house['size'] ?></td>
					<td><?= (int) $house['beds'] ?></td>
					<td><?= (int) $house['rent'] ?></td>
					<?php if ($house['owner'] != 0): ?>
						<td><a href="characterprofile.php?name=<?= $h($house['ownername']) ?>" target="_blank"><?= $h($house['ownername']) ?></a></td>
					<?php else: ?>
						<td><?= ($house['highest_bidder'] == 0) ? $h(t('common.none')) : '<b>' . $h(t_default('houses.selling', 'Selling')) . '</b>' ?></td>
					<?php endif; ?>
					<td><?= $town_name ? $h($town_name) : $h(t_default('houses.unknown_town', 'Specify town id') . ' ' . $house['town_id'] . ' ' . t_default('houses.unknown_town_suffix', 'name in config.php first.')) ?></td>
				</tr>
			<?php endif; ?>
		<?php endforeach; ?>
	</table>
	</div>
	<?php
else:
	?>
	<div class="znx-acct-head"><?= t('houses.fetch_failed') ?></div>
	<div class="znx-empty-box"><?= t('houses.empty') ?></div>
	<?php
endif; ?>

</div><!-- .znx-acct -->
<?php
theme_close();
