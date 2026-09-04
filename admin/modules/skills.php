<?php
/**
 * Title: Character Skills
 * Icon: fa-bolt
 * Group: Players
 * Order: 30
 * Description: Read and rewrite a character's level, vocation and skills.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

function acp_skill_value(?array $skills, int $index): int {
	return (is_array($skills) && isset($skills[$index]['value'])) ? (int)$skills[$index]['value'] : 0;
}

$isTfs10 = ($config['ServerEngine'] === 'TFS_10');

// ---------------------------------------------------------------------------
// Save
// ---------------------------------------------------------------------------
if (isset($_POST['pid']) && intv($_POST['pid']) > 0) {

	$pid        = intv($_POST['pid']);
	$backToName = trim((string)($_POST['name'] ?? ''));

	$isOnline = $isTfs10 ? user_is_online_10($pid) : user_is_online($pid);

	if ($isOnline) {
		acp_flash_error('The character must be offline before its skills can be rewritten.');
	} else {
		$level    = intv($_POST['level'] ?? 1);
		$vocation = intv($_POST['vocation'] ?? 0);

		// Base stats, derived exactly like character creation does.
		$playercnf = $config['player'];
		$statgain  = $config['vocations_gain'][$vocation] ?? ['hp' => 0, 'mp' => 0, 'cap' => 0];

		$levelsFromBase = $level - intv($playercnf['base']['level']);

		$newHp  = intv($playercnf['base']['health'] + ($statgain['hp']  * $levelsFromBase));
		$newMp  = intv($playercnf['base']['mana']   + ($statgain['mp']  * $levelsFromBase));
		$newCap = intv($playercnf['base']['cap']    + ($statgain['cap'] * $levelsFromBase));

		$fist   = intv($_POST['fist']   ?? 0);
		$club   = intv($_POST['club']   ?? 0);
		$sword  = intv($_POST['sword']  ?? 0);
		$axe    = intv($_POST['axe']    ?? 0);
		$dist   = intv($_POST['dist']   ?? 0);
		$shield = intv($_POST['shield'] ?? 0);
		$fish   = intv($_POST['fish']   ?? 0);
		$magic  = intv($_POST['magic']  ?? 0);

		if (!$isTfs10) {
			// TFS 0.x keeps skills in their own table.
			mysql_update("UPDATE `player_skills` SET `value`={$fist}   WHERE `player_id`={$pid} AND `skillid`=0 LIMIT 1;");
			mysql_update("UPDATE `player_skills` SET `value`={$club}   WHERE `player_id`={$pid} AND `skillid`=1 LIMIT 1;");
			mysql_update("UPDATE `player_skills` SET `value`={$sword}  WHERE `player_id`={$pid} AND `skillid`=2 LIMIT 1;");
			mysql_update("UPDATE `player_skills` SET `value`={$axe}    WHERE `player_id`={$pid} AND `skillid`=3 LIMIT 1;");
			mysql_update("UPDATE `player_skills` SET `value`={$dist}   WHERE `player_id`={$pid} AND `skillid`=4 LIMIT 1;");
			mysql_update("UPDATE `player_skills` SET `value`={$shield} WHERE `player_id`={$pid} AND `skillid`=5 LIMIT 1;");
			mysql_update("UPDATE `player_skills` SET `value`={$fish}   WHERE `player_id`={$pid} AND `skillid`=6 LIMIT 1;");

			mysql_update("
				UPDATE `players`
				SET `maglevel`={$magic},
					`vocation`={$vocation},
					`level`={$level},
					`experience`=" . level_to_experience($level) . ",
					`health`={$newHp},
					`healthmax`={$newHp},
					`mana`={$newMp},
					`manamax`={$newMp},
					`cap`={$newCap}
				WHERE `id`={$pid}
				LIMIT 1;
			");
		} else {
			mysql_update("
				UPDATE `players`
				SET `vocation`={$vocation},
					`level`={$level},
					`experience`=" . level_to_experience($level) . ",
					`health`={$newHp},
					`healthmax`={$newHp},
					`mana`={$newMp},
					`manamax`={$newMp},
					`cap`={$newCap},
					`skill_fist`={$fist},
					`skill_club`={$club},
					`skill_sword`={$sword},
					`skill_axe`={$axe},
					`skill_dist`={$dist},
					`skill_shielding`={$shield},
					`skill_fishing`={$fish},
					`maglevel`={$magic}
				WHERE `id`={$pid}
				LIMIT 1;
			");
		}

		acp_flash_success('Skills updated.');
	}

	acp_redirect('skills', $backToName !== '' ? ['name' => $backToName] : []);
}

// ---------------------------------------------------------------------------
// Load
// ---------------------------------------------------------------------------
$name   = isset($_GET['name']) ? trim((string)$_GET['name']) : '';
$skills = null;
$pid    = 0;

if ($name !== '') {
	if (!user_character_exist($name)) {
		acp_flash_error('Character <strong>' . h($name) . '</strong> does not exist.');
		acp_redirect('skills');
	}

	$pid = (int)user_character_id($name);

	if (!$isTfs10) {
		$rows = mysql_select_multi("
			SELECT `value` FROM `player_skills`
			WHERE `player_id`={$pid}
			ORDER BY `skillid` ASC
			LIMIT 7;
		");

		// mysql_select_multi() returns false when the character has no skill
		// rows yet - appending to that was a PHP 8.1 deprecation.
		$skills = is_array($rows) ? $rows : [];

		$player = mysql_select_single("
			SELECT `maglevel`, `level`, `vocation`
			FROM `players`
			WHERE `id`={$pid}
			LIMIT 1;
		");
		if (!is_array($player)) {
			$player = ['maglevel' => 0, 'level' => 0, 'vocation' => 0];
		}

		// Pad missing skill rows so indexes 0-6 always exist.
		while (count($skills) < 7) {
			$skills[] = ['value' => 0];
		}

		$skills[] = ['value' => $player['maglevel']];
		$skills[] = ['value' => $player['level']];
		$skills[] = ['value' => $player['vocation']];
	} else {
		$p = mysql_select_single("
			SELECT `skill_fist`, `skill_club`, `skill_sword`, `skill_axe`,
				   `skill_dist`, `skill_shielding`, `skill_fishing`,
				   `maglevel`, `level`, `vocation`
			FROM `players`
			WHERE `id`={$pid}
			LIMIT 1;
		");
		if (!is_array($p)) {
			$p = [];
		}

		$skills = [
			['value' => $p['skill_fist']       ?? 0],
			['value' => $p['skill_club']       ?? 0],
			['value' => $p['skill_sword']      ?? 0],
			['value' => $p['skill_axe']        ?? 0],
			['value' => $p['skill_dist']       ?? 0],
			['value' => $p['skill_shielding']  ?? 0],
			['value' => $p['skill_fishing']    ?? 0],
			['value' => $p['maglevel']         ?? 0],
			['value' => $p['level']            ?? 0],
			['value' => $p['vocation']         ?? 0],
		];
	}
}

$loaded = ($name !== '');
?>

<?php if (!$loaded): ?>

	<section class="acp-card" style="max-width:520px;">
		<header class="acp-card-head">
			<h2>Look up a character</h2>
			<p>Its current values load into the editor</p>
		</header>
		<div class="acp-card-body">
			<form method="get">
				<input type="hidden" name="p" value="skills">
				<div class="acp-field">
					<label class="acp-label" for="name">Character name</label>
					<input class="acp-input" id="name" name="name" placeholder="Character name" autofocus required>
				</div>
				<div class="acp-actions">
					<button class="acp-btn" type="submit"><i class="fa fa-search"></i> Fetch skills</button>
				</div>
			</form>
		</div>
	</section>

<?php else: ?>

	<div class="acp-toolbar">
		<div>
			<strong><?= h($name) ?></strong>
			<span class="acp-pill acp-pill--grey">#<?= (int)$pid ?></span>
			<a href="<?= h(acp_site('characterprofile.php?name=' . urlencode($name))) ?>" target="_blank" rel="noopener">View profile</a>
		</div>
		<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('skills')) ?>">
			<i class="fa fa-refresh"></i> Search another character
		</a>
	</div>

	<form method="post" data-confirm="Overwrite this character's level and skills?">
		<?= acp_csrf_field() ?>
		<input type="hidden" name="pid" value="<?= (int)$pid ?>">
		<input type="hidden" name="name" value="<?= h($name) ?>">

		<div class="acp-grid acp-grid--2">
			<section class="acp-card">
				<header class="acp-card-head"><h2>Character</h2></header>
				<div class="acp-card-body">
					<div class="acp-field">
						<label class="acp-label" for="vocation">Vocation</label>
						<select class="acp-select" id="vocation" name="vocation">
							<?php foreach (($config['vocations'] ?? []) as $vid => $v): ?>
								<option value="<?= (int)$vid ?>" <?= (int)$vid === acp_skill_value($skills, 9) ? 'selected' : '' ?>>
									<?= h($v['name']) ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="acp-row">
						<div class="acp-field">
							<label class="acp-label" for="level">Level</label>
							<input class="acp-input" id="level" name="level" type="number" min="1" value="<?= acp_skill_value($skills, 8) ?>">
						</div>
						<div class="acp-field">
							<label class="acp-label" for="magic">Magic level</label>
							<input class="acp-input" id="magic" name="magic" type="number" min="0" value="<?= acp_skill_value($skills, 7) ?>">
						</div>
					</div>
					<p class="acp-hint">
						Health, mana and capacity are recalculated from the level and vocation
						using <code>$config['player']['base']</code> and <code>$config['vocations_gain']</code>.
					</p>
				</div>
			</section>

			<section class="acp-card">
				<header class="acp-card-head"><h2>Skills</h2></header>
				<div class="acp-card-body">
					<div class="acp-row">
						<div class="acp-field">
							<label class="acp-label" for="fist">Fist fighting</label>
							<input class="acp-input" id="fist" name="fist" type="number" min="0" value="<?= acp_skill_value($skills, 0) ?>">
						</div>
						<div class="acp-field">
							<label class="acp-label" for="club">Club fighting</label>
							<input class="acp-input" id="club" name="club" type="number" min="0" value="<?= acp_skill_value($skills, 1) ?>">
						</div>
						<div class="acp-field">
							<label class="acp-label" for="sword">Sword fighting</label>
							<input class="acp-input" id="sword" name="sword" type="number" min="0" value="<?= acp_skill_value($skills, 2) ?>">
						</div>
					</div>
					<div class="acp-row">
						<div class="acp-field">
							<label class="acp-label" for="axe">Axe fighting</label>
							<input class="acp-input" id="axe" name="axe" type="number" min="0" value="<?= acp_skill_value($skills, 3) ?>">
						</div>
						<div class="acp-field">
							<label class="acp-label" for="dist">Distance fighting</label>
							<input class="acp-input" id="dist" name="dist" type="number" min="0" value="<?= acp_skill_value($skills, 4) ?>">
						</div>
						<div class="acp-field">
							<label class="acp-label" for="shield">Shielding</label>
							<input class="acp-input" id="shield" name="shield" type="number" min="0" value="<?= acp_skill_value($skills, 5) ?>">
						</div>
					</div>
					<div class="acp-field" style="max-width:200px;">
						<label class="acp-label" for="fish">Fishing</label>
						<input class="acp-input" id="fish" name="fish" type="number" min="0" value="<?= acp_skill_value($skills, 6) ?>">
					</div>
				</div>
			</section>
		</div>

		<div class="acp-actions">
			<button class="acp-btn acp-btn--green" type="submit"><i class="fa fa-save"></i> Save character</button>
			<span class="acp-hint">The character has to be offline, otherwise the server overwrites this on logout.</span>
		</div>
	</form>

<?php endif; ?>
