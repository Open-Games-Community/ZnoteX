<?php require_once 'engine/init.php';

if (empty($config['queststatus_enabled'])) {
	header('Location: index.php');
	exit();
}

protect_page();
theme_open();

$characters = user_character_list($session_user_id);
$selected = isset($_GET['name']) ? htmlspecialchars($_GET['name']) : false;

$user_id = false;
foreach ($characters as $char) {
	if ($selected === false) { $selected = $char['name']; }
	if ($char['name'] === $selected) { $user_id = (int)$char['id']; }
}

$quests = array(
	'Bearslayer' => 1050,
	'Sword Quest' => 1337,
	'Postman Quest' => array(1338, 3),
);

$completed = '<font color="green">[' . t('quest.completed') . ']</font>';
$notstarted = '';

function queststatus_progress($min, $max) {
	$percent = $max > 0 ? round(($min / $max) * 100) : 0;
	return '<font color="orange">[' . $percent . '%]</font>';
}
?>
<?php if ($characters !== false && count($characters) > 0): ?>
<form method="get" action="queststatus.php" style="margin-bottom:10px;">
	<select name="name" onchange="this.form.submit();">
		<?php foreach ($characters as $char): ?>
			<option value="<?= htmlspecialchars($char['name']) ?>"<?= $char['name'] === $selected ? ' selected' : '' ?>><?= htmlspecialchars($char['name']) ?></option>
		<?php endforeach; ?>
	</select>
	<noscript><input type="submit" value="Go"></noscript>
</form>
<table id="questTable">
	<tr class="yellow">
		<td><?= t('quest.name') ?></td>
		<td><?= t('quest.status') ?></td>
	</tr>
	<?php foreach ($quests as $key => $quest):
		if (!is_array($quest)) {
			$query = db()->fetchOne("SELECT `value` FROM `player_storage` WHERE `key` = ? AND `player_id` = ? AND `value` = 1 LIMIT 1;", [(int)$quest, (int)$user_id]);
			$quest = ($query !== false) ? $completed : $notstarted;
		} else {
			$query = db()->fetchOne("SELECT `value` FROM `player_storage` WHERE `key` = ? AND `player_id` = ? AND `value` > 0 LIMIT 1;", [(int)$quest[0], (int)$user_id]);
			if (!$query) $quest = $notstarted;
			elseif ($query['value'] >= $quest[1]) $quest = $completed;
			else $quest = queststatus_progress($query['value'], $quest[1]);
		}
		?>
		<tr>
			<td><?= $key ?></td>
			<td><?= $quest ?></td>
		</tr>
	<?php endforeach; ?>
</table>
<?php else: ?>
<p><?= t('quest.no_characters') ?></p>
<?php endif; ?>
<?php
theme_close();
