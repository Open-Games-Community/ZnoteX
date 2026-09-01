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
function playerSkill(?array $skills, int $id): int {
	return ($skills && isset($skills[$id]['value'])) ? (int)$skills[$id]['value'] : 0;
}

if (isset($_POST['pid']) && intv($_POST['pid']) > 0) {

	$pid = intv($_POST['pid']);

	// Online check
	if ($config['ServerEngine'] !== 'TFS_10') {
		$isOnline = user_is_online($pid);
	} else {
		$isOnline = user_is_online_10($pid);
	}

	if (!$isOnline) {

		$level    = intv($_POST['level'] ?? 1);
		$vocation = intv($_POST['vocation'] ?? 0);

		// Base stats
		$playercnf = $config['player'];
		$statgain  = $config['vocations_gain'][$vocation] ?? ['hp'=>0,'mp'=>0,'cap'=>0];

		$levelsFromBase = $level - intv($playercnf['base']['level']);

		$newHp  = intv($playercnf['base']['health'] + ($statgain['hp']  * $levelsFromBase));
		$newMp  = intv($playercnf['base']['mana']   + ($statgain['mp']  * $levelsFromBase));
		$newCap = intv($playercnf['base']['cap']    + ($statgain['cap'] * $levelsFromBase));

		$fist   = intv($_POST['fist'] ?? 0);
		$club   = intv($_POST['club'] ?? 0);
		$sword  = intv($_POST['sword'] ?? 0);
		$axe    = intv($_POST['axe'] ?? 0);
		$dist   = intv($_POST['dist'] ?? 0);
		$shield = intv($_POST['shield'] ?? 0);
		$fish   = intv($_POST['fish'] ?? 0);
		$magic  = intv($_POST['magic'] ?? 0);

		// TFS < 10
		if ($config['ServerEngine'] !== 'TFS_10') {

			mysql_update("UPDATE player_skills SET value={$fist}   WHERE player_id={$pid} AND skillid=0 LIMIT 1;");
			mysql_update("UPDATE player_skills SET value={$club}   WHERE player_id={$pid} AND skillid=1 LIMIT 1;");
			mysql_update("UPDATE player_skills SET value={$sword}  WHERE player_id={$pid} AND skillid=2 LIMIT 1;");
			mysql_update("UPDATE player_skills SET value={$axe}    WHERE player_id={$pid} AND skillid=3 LIMIT 1;");
			mysql_update("UPDATE player_skills SET value={$dist}   WHERE player_id={$pid} AND skillid=4 LIMIT 1;");
			mysql_update("UPDATE player_skills SET value={$shield} WHERE player_id={$pid} AND skillid=5 LIMIT 1;");
			mysql_update("UPDATE player_skills SET value={$fish}   WHERE player_id={$pid} AND skillid=6 LIMIT 1;");

			mysql_update("
				UPDATE players
				SET maglevel={$magic},
					vocation={$vocation},
					level={$level},
					experience=" . level_to_experience($level) . ",
					health={$newHp},
					healthmax={$newHp},
					mana={$newMp},
					manamax={$newMp},
					cap={$newCap}
				WHERE id={$pid}
				LIMIT 1
			");

		// TFS 10+
		} else {

			mysql_update("
				UPDATE players
				SET vocation={$vocation},
					level={$level},
					experience=" . level_to_experience($level) . ",
					health={$newHp},
					healthmax={$newHp},
					mana={$newMp},
					manamax={$newMp},
					cap={$newCap},
					skill_fist={$fist},
					skill_club={$club},
					skill_sword={$sword},
					skill_axe={$axe},
					skill_dist={$dist},
					skill_shielding={$shield},
					skill_fishing={$fish},
					maglevel={$magic}
				WHERE id={$pid}
				LIMIT 1
			");
		}

		echo '<h1 style="color:green;">Player skills updated!</h1>';

	} else {
		echo '<p style="color:red;font-size:22px;">Player must be offline!</p>';
	}
}

$name = isset($_GET['name']) ? trim($_GET['name']) : false;

$skills = null;
$pid = 0;

if ($name && user_character_exist($name)) {

	$pid = user_character_id($name);

	if ($config['ServerEngine'] !== 'TFS_10') {

		$skills = mysql_select_multi("
			SELECT value FROM player_skills
			WHERE player_id={$pid}
			ORDER BY skillid ASC
			LIMIT 7
		");

		$player = mysql_select_single("
			SELECT maglevel, level, vocation
			FROM players
			WHERE id={$pid}
			LIMIT 1
		");

		$skills[] = ['value' => $player['maglevel']];
		$skills[] = ['value' => $player['level']];
		$skills[] = ['value' => $player['vocation']];

	} else {

		$p = mysql_select_single("
			SELECT skill_fist, skill_club, skill_sword, skill_axe,
				   skill_dist, skill_shielding, skill_fishing,
				   maglevel, level, vocation
			FROM players
			WHERE id={$pid}
			LIMIT 1
		");

		$skills = [
			['value'=>$p['skill_fist']],
			['value'=>$p['skill_club']],
			['value'=>$p['skill_sword']],
			['value'=>$p['skill_axe']],
			['value'=>$p['skill_dist']],
			['value'=>$p['skill_shielding']],
			['value'=>$p['skill_fishing']],
			['value'=>$p['maglevel']],
			['value'=>$p['level']],
			['value'=>$p['vocation']],
		];
	}	
}
?>

<form method="<?= $name ? 'post' : 'get' ?>">
	<input type="hidden" name="pid" value="<?= $pid ?>">
	<table class="table">
		<tr class="yellow">
			<td colspan="2" style="text-align:center;font-size:22px;">
				Player skills administration
			</td>
		</tr>

		<tr>
			<td>
				<input name="name" type="text" placeholder="Character name"
					<?= $name ? 'value="'.h($name).'" disabled' : '' ?>>

				<br><br>Vocation:<br>
				<select name="vocation" <?= !$name ? 'disabled' : '' ?>>
					<?php foreach ($config['vocations'] as $vid => $v): ?>
						<option value="<?= $vid ?>" <?= $vid === playerSkill($skills, 9) ? 'selected' : '' ?>>
							<?= h($v['name']) ?>
						</option>
					<?php endforeach; ?>
				</select>

				<br><br>Fist fighting:<br>
				<input name="fist" value="<?= playerSkill($skills,0) ?>" <?= !$name ? 'disabled' : '' ?>>

				<br><br>Club fighting:<br>
				<input name="club" value="<?= playerSkill($skills,1) ?>" <?= !$name ? 'disabled' : '' ?>>

				<br><br>Sword fighting:<br>
				<input name="sword" value="<?= playerSkill($skills,2) ?>" <?= !$name ? 'disabled' : '' ?>>

				<br><br>Axe fighting:<br>
				<input name="axe" value="<?= playerSkill($skills,3) ?>" <?= !$name ? 'disabled' : '' ?>>
			</td>

			<td>
				Dist fighting:<br>
				<input name="dist" value="<?= playerSkill($skills,4) ?>" <?= !$name ? 'disabled' : '' ?>>

				<br><br>Shield fighting:<br>
				<input name="shield" value="<?= playerSkill($skills,5) ?>" <?= !$name ? 'disabled' : '' ?>>

				<br><br>Fish fighting:<br>
				<input name="fish" value="<?= playerSkill($skills,6) ?>" <?= !$name ? 'disabled' : '' ?>>

				<br><br>Level:<br>
				<input name="level" value="<?= playerSkill($skills,8) ?>" <?= !$name ? 'disabled' : '' ?>>

				<br><br>Magic level:<br>
				<input name="magic" value="<?= playerSkill($skills,7) ?>" <?= !$name ? 'disabled' : '' ?>>
			</td>
		</tr>

		<tr>
			<td colspan="2">
				<?php if (!$name): ?>
					<input class="btn btn-primary" type="submit" value="Fetch character skills info">
				<?php else: ?>
					<input class="btn btn-success" type="submit" value="UPDATE SKILLS">
				<?php endif; ?>
			</td>
		</tr>
	</table>
	<a href="admin_skills.php">Reset fields / search new character</a>
</form>

<?php include 'layout/overall/footer.php'; ?>