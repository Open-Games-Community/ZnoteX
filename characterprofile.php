<?php require_once 'engine/init.php'; theme_open();

if ($config['log_ip']) {
	znote_visitor_insert_detailed_data(4);
}

if (isset($_GET['name']) === true && empty($_GET['name']) === false) {
	$name = getValue($_GET['name'] ?? null);
	$user_id = user_character_exist($name);

	if ($user_id !== false) {
		$loadOutfits = $config['show_outfits']['characterprofile'];

		if (!$loadOutfits) {
			$profile_data = user_character_data($user_id, 'account_id', 'name', 'level', 'group_id', 'vocation', 'health', 'healthmax', 'experience', 'mana', 'manamax', 'sex', 'lastlogin', 'town_id');
		} else { // Load outfits
			if ($config['client'] < 780) {
				$profile_data = user_character_data($user_id, 'account_id', 'name', 'level', 'group_id', 'vocation', 'health', 'healthmax', 'experience', 'mana', 'manamax', 'sex', 'lastlogin', 'town_id', 'lookbody', 'lookfeet', 'lookhead', 'looklegs', 'looktype');
			} else {
				$profile_data = user_character_data($user_id, 'account_id', 'name', 'level', 'group_id', 'vocation', 'health', 'healthmax', 'experience', 'mana', 'manamax', 'sex', 'lastlogin', 'town_id', 'lookbody', 'lookfeet', 'lookhead', 'looklegs', 'looktype', 'lookaddons');
			}
		}
		$profile_data['online'] = user_is_online_10($user_id);

		if ($config['Ach']) {
			$user_id = (int) $user_id;
			$achievementPoints = db()->fetchOne("SELECT SUM(`value`) AS `sum` FROM `player_storage` WHERE `key` LIKE '30___' AND `player_id` = ? LIMIT 1", [$user_id]);
		}

		$profile_znote_data = user_znote_character_data($user_id, 'created', 'hide_char', 'comment');
		$guild_exist = false;
		if (get_character_guild_rank($user_id) > 0) {
			$guild_exist = true;
			$guild = get_player_guild_data($user_id);
			$guild_name = get_guild_name($guild['guild_id']);
		}

		$position = '';
		if ($profile_data['group_id'] > 1) {
			$position_data = db()->fetchOne("
				SELECT `a`.`type`
				FROM `players` AS `p`
				INNER JOIN `accounts` AS `a`
					ON `p`.`account_id` = `a`.`id`
				WHERE
					`a`.`type` > 1
					AND `p`.`id` = ?
			", [$user_id]);
			$position_type = ($position_data !== false) ? $position_data['type'] : null;
			$position = (isset($config['ingame_positions'][$position_type])) ? $config['ingame_positions'][$position_type] : 'Unknown';
		}

		$deletion_time = db()->fetchOne("SELECT `time` FROM `znote_deleted_characters` WHERE `character_name` = ? AND `done` = '0' LIMIT 1;", [$name]);
		$houses = db()->fetchAll("
			SELECT `id`, `owner`, `name`, `town_id` AS `town_id`
			FROM `houses`
			WHERE `owner` = ?;
		", [$user_id]);
		?>

		<!-- PROFILE MARKUP HERE-->
		<?php
						// Item image server
						$imageServer = $config['shop']['imageServer'];
						$imageType = $config['shop']['imageType'];
						$PEQ = db()->fetchAll("
							SELECT
								`player_id`,
								`pid`,
								`itemtype`,
								`count`
							FROM `player_items`
							WHERE `player_id` = ?
							AND `pid` < '11'
						", [$user_id]);

						$soulStamina = " `soul`, `stamina`,";
						if ($config['client'] < 780) {
							$soulStamina = " 0 AS `soul`, 0 AS `stamina`,";
						}

						$player_query = "
							SELECT
								`health`, `healthmax`,
								`mana`, `manamax`,
								`cap`,
								`experience`, `level`,
								{$soulStamina}
								`maglevel`,
								`skill_fist`,
								`skill_club`,
								`skill_sword`,
								`skill_axe`,
								`skill_dist`,
								`skill_shielding`,
								`skill_fishing`
							FROM `players`
							WHERE `id` = ?
							LIMIT 1;
						";
						$playerstats = db()->fetchOne($player_query, [$user_id]);

						$player_experience_raw = (int)$playerstats['experience'];
						$profile_flag = '';
						$flags = $config['country_flags'];
						if ($flags['enabled'] && $flags['characterprofile']) {
							$flag_account = user_znote_account_data($profile_data['account_id'], 'flag');
							if ($flag_account !== false && strlen($flag_account['flag']) > 0) {
								$profile_flag = (string)$flag_account['flag'];
							}
						}
						$profile_houses = array();
						if ($houses !== false) {
							foreach ($houses as $h) {
								$profile_houses[] = $h['name'] . ', ' . ($config['towns'][$h['town_id']] ?? $h['town_id']);
							}
						}
						$profile_account = user_data((int)$profile_data['account_id'], 'premium_ends_at');
						$premium_until = ($profile_account !== false) ? (int)($profile_account['premium_ends_at'] ?? 0) : 0;
						$account_status = ((bool)($config['freePremium'] ?? false) || $premium_until > time()) ? 'VIP active' : 'VIP inactive';
						$profile_lookaddons = (int)($profile_data['lookaddons'] ?? 0);
						$current_outfit_src = $loadOutfits
							? $config['show_outfits']['imageServer'] . '?id=' . (int)$profile_data['looktype'] . '&addons=' . $profile_lookaddons . '&head=' . (int)$profile_data['lookhead'] . '&body=' . (int)$profile_data['lookbody'] . '&legs=' . (int)$profile_data['looklegs'] . '&feet=' . (int)$profile_data['lookfeet']
							: '';
						$health_percent = ((int)$playerstats['healthmax'] > 0) ? min(100, max(0, round(((int)$playerstats['health'] / (int)$playerstats['healthmax']) * 100, 2))) : 100;
						$mana_percent = ((int)$playerstats['manamax'] > 0) ? min(100, max(0, round(((int)$playerstats['mana'] / (int)$playerstats['manamax']) * 100, 2))) : 100;
						$experience_raw = $player_experience_raw;
						$level_raw = (int)$playerstats['level'];
						$level_start_exp = (int)level_to_experience($level_raw);
						$level_next_exp = (int)level_to_experience($level_raw + 1);
						$level_exp_span = max(1, $level_next_exp - $level_start_exp);
						$level_exp_done = min($level_exp_span, max(0, $experience_raw - $level_start_exp));
						$level_percent = round(($level_exp_done / $level_exp_span) * 100, 2);
						$level_needed = max(0, $level_next_exp - $experience_raw);
						$detail_skill_rows = array(
							array('label' => t('common.level'), 'value' => $playerstats['level']),
							array('label' => 'Magic Level', 'value' => $playerstats['maglevel']),
							array('label' => t('skill.fist'), 'value' => $playerstats['skill_fist']),
							array('label' => t('skill.club'), 'value' => $playerstats['skill_club']),
							array('label' => t('skill.sword'), 'value' => $playerstats['skill_sword']),
							array('label' => t('skill.axe'), 'value' => $playerstats['skill_axe']),
							array('label' => t('skill.distance'), 'value' => $playerstats['skill_dist']),
							array('label' => t('skill.shielding'), 'value' => $playerstats['skill_shielding']),
							array('label' => t('skill.fishing'), 'value' => $playerstats['skill_fishing']),
						);
						?>
						<div id="characterProfileTable" class="cp-profile-shell">
							<div class="cp-profile-rebuild">
								<?php if ($deletion_time !== false): ?>
									<div class="cp-alert"><?= t('char.flagged_delete2') ?> <?php echo htmlspecialchars((string)$deletion_time['time'], ENT_QUOTES, 'UTF-8'); ?>.</div>
								<?php endif; ?>

								<div class="cp-profile-top">
									<section class="cp-panel cp-info-panel">
										<header class="cp-panel-head">
											<h2>Character Information</h2>
										</header>
										<div class="cp-info-list">
											<div class="cp-info-row">
												<span>Name</span>
												<strong><?php echo htmlspecialchars($profile_data['name'], ENT_QUOTES, 'UTF-8'); ?> <em class="cp-status cp-status--<?php echo $profile_data['online'] ? 'online' : 'offline'; ?>"><?php echo $profile_data['online'] ? 'ON' : 'OFF'; ?></em></strong>
											</div>
											<?php if ($position !== ''): ?>
												<div class="cp-info-row">
													<span><?= t('char.position') ?></span>
													<strong><?php echo htmlspecialchars($position, ENT_QUOTES, 'UTF-8'); ?></strong>
												</div>
											<?php endif; ?>
											<?php if ($profile_flag !== ''): ?>
												<div class="cp-info-row">
													<span>Country</span>
													<strong><?php echo strtoupper(htmlspecialchars($profile_flag, ENT_QUOTES, 'UTF-8')); ?> <img class="cp-flag" src="<?php echo htmlspecialchars($flags['server'] . '/' . $profile_flag . '.png', ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($profile_flag, ENT_QUOTES, 'UTF-8'); ?>"></strong>
												</div>
											<?php endif; ?>
											<div class="cp-info-row">
												<span>Sex</span>
												<strong><?php echo ($profile_data['sex'] == 1) ? 'male' : 'female'; ?></strong>
											</div>
											<div class="cp-info-row">
												<span><?= t('common.vocation') ?></span>
												<strong><?php echo htmlspecialchars(vocation_id_to_name($profile_data['vocation']), ENT_QUOTES, 'UTF-8'); ?></strong>
											</div>
											<div class="cp-info-row">
												<span><?= t('common.level') ?></span>
												<strong><?php echo (int)$profile_data['level']; ?></strong>
											</div>
											<?php if ($config['Ach']): ?>
												<div class="cp-info-row">
													<span><?= t('char.ach_points') ?></span>
													<strong><?php echo (int)($achievementPoints['sum'] ?? 0); ?></strong>
												</div>
											<?php endif; ?>
											<div class="cp-info-row">
												<span>Residence</span>
												<strong><?php echo htmlspecialchars($config['towns'][$profile_data['town_id']] ?? (string)$profile_data['town_id'], ENT_QUOTES, 'UTF-8'); ?></strong>
											</div>
											<?php if (!empty($profile_houses)): ?>
												<div class="cp-info-row">
													<span>House</span>
													<strong><?php echo htmlspecialchars(implode(', ', $profile_houses), ENT_QUOTES, 'UTF-8'); ?></strong>
												</div>
											<?php endif; ?>
											<?php if ($guild_exist): ?>
												<div class="cp-info-row">
													<span>Guild</span>
													<strong><?php echo htmlspecialchars($guild['rank_name'], ENT_QUOTES, 'UTF-8'); ?> of <a href="guilds.php?name=<?php echo urlencode($guild_name); ?>"><?php echo htmlspecialchars($guild_name, ENT_QUOTES, 'UTF-8'); ?></a></strong>
												</div>
											<?php endif; ?>
											<div class="cp-info-row">
												<span><?= t('char.last_login') ?></span>
												<strong><?php echo ($profile_data['lastlogin'] != 0) ? htmlspecialchars(getClock($profile_data['lastlogin'], true, true), ENT_QUOTES, 'UTF-8') : 'Never.'; ?></strong>
											</div>
											<div class="cp-info-row">
												<span>Created</span>
												<strong><?php echo htmlspecialchars(getClock($profile_znote_data['created'], true), ENT_QUOTES, 'UTF-8'); ?></strong>
											</div>
											<div class="cp-info-row">
												<span>Account Status</span>
												<strong><?php echo htmlspecialchars($account_status, ENT_QUOTES, 'UTF-8'); ?></strong>
											</div>
										</div>
									</section>

									<?php if ($config['EQ_shower']['equipment']): ?>
										<section class="cp-panel cp-equipment-panel">
											<header class="cp-panel-head">
												<h2>Equipment</h2>
											</header>
											<div class="cp-equipment-stage">
												<?php if ($loadOutfits && $current_outfit_src !== ''): ?>
													<div class="cp-current-outfit">
														<img src="<?php echo htmlspecialchars($current_outfit_src, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($profile_data['name'], ENT_QUOTES, 'UTF-8'); ?>">
													</div>
												<?php endif; ?>
												<div id="cp_equipment_board">
													<img class="cp-equipment-bg" src="/engine/img/outfit.png" alt="">
													<?php if ($PEQ !== false && !empty($PEQ)): foreach($PEQ as $item): ?>
														<div class="itm itm-<?php echo (int)$item['pid']; ?>">
															<img class="cp-slot-empty" src="/engine/img/empty.png" alt="">
															<img class="cp-slot-item" src="<?php echo htmlspecialchars(znote_item_image_url((int)$item["itemtype"]), ENT_QUOTES, "UTF-8"); ?>" alt="">
														</div>
													<?php endforeach; endif; ?>
												</div>
												<div class="cp-equipment-meta">
													<span>Soul <strong><?php echo (int)$playerstats['soul']; ?></strong></span>
													<span>Cap <strong><?php echo number_format((int)$playerstats['cap'],0,'',','); ?></strong></span>
												</div>
											</div>
										</section>
									<?php endif; ?>
								</div>

								<section class="cp-panel cp-details-panel">
									<header class="cp-panel-head">
										<h2>Character Details</h2>
									</header>
									<div class="cp-bars">
										<div class="cp-bar-block">
											<div class="cp-bar-label"><span>Health</span><strong><?php echo number_format((int)$playerstats['health'],0,'',','); ?> / <?php echo number_format((int)$playerstats['healthmax'],0,'',','); ?></strong></div>
											<div class="cp-bar-track"><i class="cp-bar-fill cp-bar-fill--health" style="width: <?php echo $health_percent; ?>%;"></i></div>
										</div>
										<div class="cp-bar-block">
											<div class="cp-bar-label"><span>Mana</span><strong><?php echo number_format((int)$playerstats['mana'],0,'',','); ?> / <?php echo number_format((int)$playerstats['manamax'],0,'',','); ?></strong></div>
											<div class="cp-bar-track"><i class="cp-bar-fill cp-bar-fill--mana" style="width: <?php echo $mana_percent; ?>%;"></i></div>
										</div>
										<div class="cp-bar-block">
											<div class="cp-bar-label"><span><?= t('skill.experience') ?> - <?= t('common.level') ?> <?php echo (int)$playerstats['level']; ?></span><strong><?php echo $level_percent; ?>% to <?php echo (int)$playerstats['level'] + 1; ?></strong></div>
											<div class="cp-bar-track"><i class="cp-bar-fill cp-bar-fill--experience" style="width: <?php echo $level_percent; ?>%;"></i></div>
											<div class="cp-exp-note">Need <strong><?php echo number_format($level_needed,0,'',','); ?></strong> experience to reach level <?php echo (int)$playerstats['level'] + 1; ?></div>
										</div>
									</div>
									<?php if ($config['EQ_shower']['skills']): ?>
										<div class="cp-skill-strip">
											<?php foreach ($detail_skill_rows as $skill_row): ?>
												<div class="cp-skill-tile">
													<span><?php echo htmlspecialchars((string)$skill_row['label'], ENT_QUOTES, 'UTF-8'); ?></span>
													<strong><?php echo htmlspecialchars((string)$skill_row['value'], ENT_QUOTES, 'UTF-8'); ?></strong>
												</div>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
								</section>
							</div>

							<!-- Inventory style positioning -->
							<style type="text/css">
								#characterProfileTable {
									background: transparent !important;
									border: 0 !important;
									box-shadow: none !important;
									overflow: visible !important;
									display: block;
									width: 100%;
									max-width: 100%;
									margin: 0 0 16px;
								}
								.cp-profile-rebuild {
									--cp-border: var(--s-border, rgba(255, 255, 255, .16));
									--cp-border-strong: var(--s-border2, rgba(255, 255, 255, .28));
									--cp-text: var(--s-text, currentColor);
									--cp-muted: var(--s-muted, currentColor);
									--cp-accent: var(--s-gold, #f0c028);
									--cp-panel-bg: var(--profile-panel-bg, transparent);
									color: var(--cp-text);
								}
								.cp-profile-top {
									display: grid;
									grid-template-columns: minmax(280px, 1fr) minmax(300px, 1fr);
									gap: 16px;
									align-items: stretch;
								}
								.cp-panel {
									position: relative;
									min-width: 0;
									border: 1px solid var(--cp-border);
									border-radius: 10px;
									background: var(--cp-panel-bg);
									overflow: hidden;
								}
								.cp-panel-head {
									display: flex;
									align-items: center;
									min-height: 46px;
									padding: 0 16px;
									border-bottom: 1px solid var(--cp-border);
									background: transparent;
								}
								.cp-panel-head h2 {
									margin: 0;
									color: inherit;
									font-size: 15px;
									font-weight: 800;
									letter-spacing: 0;
								}
								.cp-info-list {
									padding: 12px 16px 16px;
								}
								.cp-info-row {
									display: grid;
									grid-template-columns: minmax(120px, 42%) 1fr;
									gap: 14px;
									align-items: center;
									min-height: 39px;
									border-bottom: 1px solid var(--cp-border);
								}
								.cp-info-row:last-child {
									border-bottom: 0;
								}
								.cp-info-row > span {
									color: var(--cp-muted);
									font-weight: 800;
								}
								.cp-info-row > strong {
									display: flex;
									align-items: center;
									gap: 6px;
									min-width: 0;
									color: inherit;
									font-weight: 700;
								}
								.cp-flag {
									display: inline-block;
									width: auto;
									height: 11px;
									max-width: none;
								}
								.cp-status {
									color: #ff3b3b;
									font-size: 9px;
									font-style: normal;
									font-weight: 900;
								}
								.cp-status--online {
									color: #35d27f;
								}
								.cp-alert {
									margin-bottom: 12px;
									padding: 10px 14px;
									border: 1px solid var(--cp-border-strong);
									border-left: 3px solid #ff3b3b;
									border-radius: 8px;
								}
								.cp-equipment-stage {
									min-height: 390px;
									padding: 12px 16px 28px;
									display: flex;
									flex-direction: column;
									align-items: center;
									justify-content: center;
								}
								.cp-current-outfit {
									width: 100%;
									height: 88px;
									display: flex;
									align-items: center;
									justify-content: center;
									margin-bottom: 24px;
									position: relative;
									z-index: 2;
								}
								.cp-current-outfit img {
									width: 96px;
									height: 96px;
									max-width: 96px;
									max-height: 96px;
									object-fit: contain;
									object-position: center;
									transform: translateX(-22px);
									filter: drop-shadow(0 8px 10px rgba(0, 0, 0, .42));
								}
								#cp_equipment_board {
									--cp-equipped-slot-width: 50px;
									--cp-equipped-slot-height: 51px;
									--cp-equipped-item-size: 48px;
									position: relative;
									width: min(168px, 100%);
									aspect-ratio: 3 / 4;
								}
								#cp_equipment_board .cp-equipment-bg {
									width: 100%;
									height: 100%;
									object-fit: contain;
									max-width: none;
								}
								#cp_equipment_board .itm {
									position: absolute;
									width: var(--cp-equipped-slot-width);
									height: var(--cp-equipped-slot-height);
									display: flex;
									align-items: center;
									justify-content: center;
									transform: translate(-50%, -50%);
								}
								#cp_equipment_board .itm img {
									position: absolute !important;
									object-fit: contain;
								}
								#cp_equipment_board .cp-slot-empty {
									left: 50%;
									top: 50%;
									z-index: 1;
									width: var(--cp-equipped-slot-width);
									height: var(--cp-equipped-slot-height);
									max-width: var(--cp-equipped-slot-width);
									max-height: var(--cp-equipped-slot-height);
									transform: translate(-50%, -50%);
								}
								#cp_equipment_board .cp-slot-item {
									left: 50%;
									top: 50%;
									z-index: 2;
									width: var(--cp-equipped-item-size);
									height: var(--cp-equipped-item-size);
									max-width: var(--cp-equipped-item-size);
									max-height: var(--cp-equipped-item-size);
									transform: translate(-50%, -50%);
									transition: transform .16s ease, filter .16s ease;
								}
								#cp_equipment_board .itm:hover .cp-slot-item {
									transform: translate(-50%, calc(-50% - 4px));
									filter: drop-shadow(0 7px 9px rgba(0, 0, 0, .5));
								}
								#cp_equipment_board .itm-1 { left: 50%; top: 18.04%; }
								#cp_equipment_board .itm-2 { left: 20.17%; top: 18.04%; }
								#cp_equipment_board .itm-3 { left: 79.67%; top: 18.04%; }
								#cp_equipment_board .itm-4 { left: 50%; top: 40.02%; }
								#cp_equipment_board .itm-5 { left: 79.67%; top: 40.02%; }
								#cp_equipment_board .itm-6 { left: 20.17%; top: 40.02%; }
								#cp_equipment_board .itm-7 { left: 50%; top: 61.56%; }
								#cp_equipment_board .itm-8 { left: 50%; top: 83.25%; }
								#cp_equipment_board .itm-9 { left: 20.17%; top: 61.56%; }
								#cp_equipment_board .itm-10 { left: 79.67%; top: 61.56%; }
								.cp-equipment-meta {
									display: flex;
									justify-content: center;
									gap: 28px;
									margin-top: 24px;
									color: var(--cp-muted);
									font-weight: 700;
								}
								.cp-equipment-meta strong {
									color: inherit;
								}
								.cp-details-panel {
									margin-top: 10px;
								}
								.cp-bars {
									padding: 14px 16px 4px;
								}
								.cp-bar-block {
									margin-bottom: 14px;
								}
								.cp-bar-label {
									display: flex;
									justify-content: space-between;
									gap: 16px;
									margin-bottom: 7px;
									font-weight: 800;
								}
								.cp-bar-label strong {
									color: inherit;
								}
								.cp-exp-note strong {
									color: var(--cp-accent);
								}
								.cp-bar-track {
									height: 12px;
									border: 1px solid var(--cp-border);
									border-radius: 999px;
									background: var(--cp-track-bg, transparent);
									overflow: hidden;
								}
								.cp-bar-fill {
									display: block;
									height: 100%;
									border-radius: inherit;
								}
								.cp-bar-fill--health { background: #bd2b2b; }
								.cp-bar-fill--mana { background: #2f84c9; }
								.cp-bar-fill--experience { background: var(--cp-accent); }
								.cp-exp-note {
									margin-top: 6px;
									color: var(--cp-muted);
									font-size: 12px;
								}
								.cp-skill-strip {
									display: grid;
									grid-template-columns: repeat(auto-fit, minmax(72px, 1fr));
									gap: 8px;
									padding: 0 16px 16px;
								}
								.cp-skill-tile {
									min-height: 84px;
									padding: 10px 8px;
									border: 1px solid var(--cp-border);
									border-top: 2px solid var(--cp-border-strong);
									border-radius: 8px;
									background: transparent;
									text-align: center;
									overflow: hidden;
								}
								.cp-skill-tile span {
									display: block;
									min-height: 28px;
									color: var(--cp-muted);
									font-size: 10px;
									font-weight: 800;
									line-height: 1.25;
									text-transform: uppercase;
								}
								.cp-skill-tile strong {
									display: block;
									margin-top: 4px;
									color: inherit;
									font-size: 18px;
									font-weight: 900;
								}
								@media (max-width: 980px) {
									.cp-profile-top {
										grid-template-columns: 1fr;
									}
								}
								@media (max-width: 520px) {
									.cp-info-row {
										grid-template-columns: 1fr;
										gap: 2px;
										padding: 8px 0;
									}
									.cp-bar-label {
										flex-direction: column;
										gap: 2px;
									}
									.cp-equipment-stage {
										padding-left: 10px;
										padding-right: 10px;
									}
								}

							</style>
						</div>
		<!-- End profile -->

		<!-- Player Comment -->
		<?php if (!empty($profile_znote_data['comment'])): ?>
			<table class="comment">
				<thead>
					<tr class="yellow">
						<td><font class="profile_font" name="profile_font_comment"><?= t('char.comment') ?></font></td>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php echo preg_replace('/\v+|\\\r\\\n/','<br/>',$profile_znote_data['comment']); ?></td>
					</tr>
				</tbody>
			</table>
		<?php endif; ?>

		<!-- Achievements start -->
		<?php if ($config['Ach']):
			$achievements = db()->fetchAll("
				SELECT `player_id`, `value`, `key`
				FROM `player_storage`
				WHERE `player_id` = ?
				AND `key` LIKE '30___';
			", [$user_id]);
			$c_achs = $config['achievements'];
			$toggle = array(
				'show' => '<a href="#show">Show</a>',
				'hide' => '<a href="#hide">Hide</a>'
			);
			if ($achievements !== false): ?>
				<h3><?= t('char.achievements') ?> <label id="ac_label_hide" for="ac_toggle_hide"><?php echo $toggle['show']; ?></label></h3>
				<!-- <div id="accordion">
					<h3><?= t('char.toggle_ach') ?></h3>
					<div>

					</div>
				</div><br> -->
				<input type="checkbox" id="ac_toggle_hide" name="ac_toggle_hide">
				<table class="achievements">
					<thead>
						<tr>
							<th>Name</th>
							<th><?= t('common.description') ?></th>
							<th>Points</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach($achievements as $a): ?>
							<tr>
								<td><?php echo $c_achs[$a['key']][0]; ?></td>
								<td><?php echo $c_achs[$a['key']][1]; ?></td>
								<td><?php echo $a['value']; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<style type="text/css">
					table.achievements,
					#ac_toggle_hide {
						display: none;
					}
					#ac_toggle_hide:checked + table.achievements {
						display: table;
					}
				</style>
				<script type="text/javascript">
					document.getElementById("ac_label_hide").addEventListener("click", function(event){
						event.preventDefault();
						if (document.getElementById("ac_label_hide").innerHTML == "<?php echo str_replace('"', '\"', $toggle['show']); ?>") {
							document.getElementById("ac_label_hide").innerHTML = "<?php echo str_replace('"', '\"', $toggle['hide']); ?>";
							document.getElementById("ac_toggle_hide").checked = true;
						} else {
							document.getElementById("ac_label_hide").innerHTML = "<?php echo str_replace('"', '\"', $toggle['show']); ?>";
							document.getElementById("ac_toggle_hide").checked = false;
						}
					});
				</script>
			<?php endif; ?>
		<?php endif; ?>

		<!-- DEATH LIST -->
		<table class="deathlist">
			<thead>
				<tr class="yellow">
					<th colspan="2"><?= t('char.death_list') ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$deaths = db()->fetchAll("
					SELECT
						`player_id`,
						`time`,
						`level`,
						`killed_by`,
						`is_player`,
						`mostdamage_by`,
						`mostdamage_is_player`,
						`unjustified`,
						`mostdamage_unjustified`
					FROM `player_deaths`
					WHERE `player_id` = ?
					ORDER BY `time` DESC
					LIMIT 10;
				", [$user_id]);

				if ($deaths) {
					foreach ($deaths as $d) {
						$lasthit = ($d['is_player'])
						? "<a href='characterprofile.php?name=".$d['killed_by']."'>".$d['killed_by']."</a>"
						: $d['killed_by'];

						?>
						<tr>
							<td><?php echo getClock($d['time'], true, true); ?></td>
							<td>
								<?php
								echo t('char.killed_at', ['level' => $d['level'], 'killer' => $lasthit]);
								if ($d['unjustified']) {
									echo " <font color='red' style='font-style: italic;'>(unjustified)</font>";
								}
								$mostdmg = ($d['mostdamage_by'] !== $d['killed_by']) ? true : false;
								if ($mostdmg) {
									$mostdmg = ($d['mostdamage_is_player'])
									? "<a href='characterprofile.php?name=".$d['mostdamage_by']."'>".$d['mostdamage_by']."</a>"
									: $d['mostdamage_by'];

									echo "<br>and by $mostdmg.";

									if ($d['mostdamage_unjustified']) {
										echo " <font color='red' style='font-style: italic;'>(unjustified)</font>";
									}
								} else {
									echo " <b>(soloed)</b>";
								}
								?>
							</td>
						</tr>
						<?php
					}
				} else {
					?>
					<tr>
						<td colspan="2"><?= t('char.never_died') ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>

		<!-- QUEST PROGRESSION -->
		<?php
		$totalquests = 0;
		$completedquests = 0;
		$firstrun = 1;

		if ($config['EnableQuests'] == true) {
			$sqlquests = db()->fetchAll("
				SELECT `player_id`, `key`, `value`
				FROM player_storage
				WHERE `player_id` = ?
			", [$user_id]);
			if (isset($config['quests']) && !empty($config['quests'])) {
				foreach ($config['quests'] as $cquest) {
					$totalquests = $totalquests + 1;
					if ($sqlquests !== false) {
                        foreach ($sqlquests as $dbquest) {
    						if ($cquest[0] == $dbquest['key'] && $cquest[1] == $dbquest['value']) {
    							$completedquests = $completedquests + 1;
    						}
    					}
                    }
					if ($cquest[3] == 1) {
						if ($completedquests != 0) {
							if ($firstrun == 1): ?>
								<b> Quest progression </b>
								<table id="characterprofileQuest" class="table table-striped table-hover">
									<thead>
										<tr class="yellow">
											<th>Quest:</th>
											<th>progression:</th>
										</tr>
									</thead>
									<tbody>
								<?php
								$firstrun = 0;
							endif;
							$completed = $completedquests / $totalquests * 100;
							?>
							<tr>
								<td><?php echo $cquest[2]; ?></td>
								<td id="progress">
									<span id="percent"><?php echo round($completed); ?>%</span>
									<div id="bar" style="width: '.$completed.'%"></div>
								</td>
							</tr>
							<?php
						}
						$completedquests = 0;
						$totalquests = 0;
					}
				}
			}
		}

		if ($firstrun == 0): ?>
			</tbody></table>
		<?php endif; ?>
		<!-- END QUEST PROGRESSION -->

		<!-- CHARACTER LIST -->
		<?php
		// Load other visible characters
		$otherChars = db()->fetchAll("
			SELECT
				`p`.`id`,
				`p`.`name`,
				`p`.`level`,
				`p`.`vocation`,
				`p`.`lastlogin`,
				CASE WHEN `l`.`player_id` IS NULL THEN 0 else 1 END as `online`
			FROM `players` as `o`
			JOIN `players` as `p`
				ON `o`.`account_id` = `p`.`account_id`
			LEFT JOIN `znote_players` as `z`
				ON `p`.`id` = `z`.`player_id`
			LEFT JOIN `znote_players` as `z2`
				ON `o`.`id` = `z2`.`player_id`
			LEFT JOIN `players_online` as `l` ON `p`.`id` = `l`.`player_id`
			WHERE `o`.`id` = ?
			AND `p`.`id` != `o`.`id`
			AND `z`.`hide_char` = 0
			AND `z2`.`hide_char` = 0
			ORDER BY `p`.`experience` DESC;
		", [$user_id]);

		// Render table if there are any characters to show
		if ($otherChars !== false) {
			?>
			<li>
				<b><?= t('char.other_chars') ?></b><br>
				<table id="characterprofileTable" class="table table-striped table-hover">
					<tr class="yellow">
						<th>Name:</th>
						<th>Level:</th>
						<th><?= t('common.vocation_label') ?></th>
						<th><?= t('char.last_login_label') ?></th>
						<th>Status:</th>
					</tr>
					<?php
					// Add character rows
					foreach ($otherChars as $char):
						?>
						<tr>
							<td><a href="characterprofile.php?name=<?php echo $char['name']; ?>"><?php echo $char['name']; ?></a></td>
							<td><?php echo (int)$char['level']; ?></td>
							<td><?php echo vocation_id_to_name($char['vocation']); ?></td>
							<td><?php echo ($char['lastlogin'] != 0) ? getClock($char['lastlogin'], true, true) : 'Never.'; ?></td>
							<td><?php echo ($char['online']) ? 'online' : 'offline'; ?></td>
						</tr>
						<?php
					endforeach;
					?>
				</table>
			</li>
			<?php
		}
		?>
		<!-- END CHARACTER LIST -->

		<p class="address"><?= t('char.address') ?> <a href="<?php echo ($config['htwrite']) ? "//" . $_SERVER['HTTP_HOST']."/" . $profile_data['name'] : "//" . $_SERVER['HTTP_HOST'] . "/characterprofile.php?name=" . $profile_data['name']; ?>"><?php echo ($config['htwrite']) ? $_SERVER['HTTP_HOST']."/". $profile_data['name'] : $_SERVER['HTTP_HOST']."/characterprofile.php?name=". $profile_data['name']; ?></a></p>

		<?php
	} else {
		echo htmlentities(strip_tags($name, ENT_QUOTES)) . ' does not exist.';
	}
} else {
	header('Location: index.php');
}
theme_close(); ?>
