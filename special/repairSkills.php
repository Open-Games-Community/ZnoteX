<?php require_once 'engine/init.php';

/*	PLAYER SKILLS REPAIR SCRIPT IF YOU SOMEHOW DELETE PLAYER SKILLS
	---------------------------------------------------------------
		Place in root web directory, login to admin account,
		and enter site.com/repairSkills.php (with big S).
*/

protect_page();
admin_only($user_data);

$Splayers = 0;
$Salready = 0;
$Sfixed = 0;

$players = db()->fetchAll("SELECT `id` FROM `players`;");
if ($players !== false) {
	$Splayers = count($players);
	foreach ($players as $char) {

		// Check if player have skills
		$skills = db()->fetchOne("SELECT `value` FROM `player_skills` WHERE `player_id` = ? AND `skillid` = 2 LIMIT 1;", [$char['id']]);

		// If he dont have any skills
		if ($skills === false) {
			$Sfixed++;

			// Loop through every skill id and give him default skills.
			$rows = array();
			$params = array();
			for ($i = 0; $i < 7; $i++) {
				$rows[] = '(?, ?, 10, 0)';
				$params[] = $char['id'];
				$params[] = $i;
			}

			db()->execute(
				"INSERT INTO `player_skills` (`player_id`, `skillid`, `value`, `count`) VALUES " . implode(', ', $rows) . ";",
				$params
			);
		} else $Salready++;
	}
	?>
	<h1>Script run status:</h1>
	<p>Players detected: <?php echo $Splayers; ?></p>
	<p>Players already fixed: <?php echo $Salready; ?></p>
	<p><b>Repaired player accounts: <?php echo $Sfixed; ?></b></p>
	<?php
} else {
	?>
	<h1>No players detected.</h1>
	<p>Something went wrong.</p>
	<?php
}
?>

<h1>Script run completed.</h1>
