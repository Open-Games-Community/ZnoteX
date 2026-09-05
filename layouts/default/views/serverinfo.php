<?php
/**
 * Server information.
 *
 * Prepared by serverinfo.php - see the docblock there for the variables.
 */
?>

<h1><?= t('srv.title') ?></h1>
<p><?= t('srv.intro') ?> <b><?php echo $config['site_title']; ?></b></p>

<?php minimap_render(); ?>

<?php 
if (
	($stagesData && isset($stagesData['enabled']) && $stagesData['enabled']) 
	|| (isset($luaConfig['experienceStages']) && $luaConfig['experienceStages'] === true)
): 
	$stages = true; ?>
	<h2><?= t('srv.rates') ?></h2>
	<table class="table tbl-hover">
		<tbody>
			<tr class="yellow">
				<td><?= t('srv.min_level') ?></td>
				<td><?= t('srv.max_level') ?></td>
				<td><?= t('srv.multiplier') ?></td>
			</tr>
			<?php foreach ($stagesData['stages'] as $stage): ?>
				<tr>
					<td><?php echo $stage['minlevel']; ?></td>
					<td><?php echo (isset($stage['maxlevel'])) ? $stage['maxlevel'] : t('common.unlimited'); ?></td>
					<td><?php echo $stage['multiplier']; ?>x</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php if ($luaConfig): ?>
	<table class="table tbl-hover">
		<tbody>
			<tr class="yellow">
				<?php if (!$stages): ?>
					<td><?= t('srv.exp_rate') ?></td>
				<?php endif; ?>
				<td><?= t('srv.skill_rate') ?></td>
				<td><?= t('srv.magic_rate') ?></td>
				<td><?= t('srv.loot_rate') ?></td>
			</tr>
			<tr>
				<?php if (!$stages): ?>
					<td><?php echo $luaConfig['rateExp']; ?></td>
				<?php endif; ?>
				<td><?php echo $luaConfig['rateSkill']; ?></td>
				<td><?php echo $luaConfig['rateMagic']; ?></td>
				<td><?php echo $luaConfig['rateLoot']; ?></td>
			</tr>
		</tbody>
	</table>

	<h2><?= t('srv.misc') ?></h2>
	<table class="table tbl-hover">
		<tbody>
			<tr class="yellow">
				<td colspan="2"><?= t('srv.connection') ?></td>
			</tr>
			<tr>
				<td><?= t('srv.client') ?></td>
				<td><?php echo ($config['client'] / 100); ?></td>
			</tr>
			<tr>
				<td>IP</td>
				<td><?php echo $_SERVER['SERVER_NAME']; ?></td>
			</tr>
			<tr>
				<td><?= t('srv.port') ?></td>
				<td><?php echo $luaConfig['loginProtocolPort']; ?></td>
			</tr>
		</tbody>
	</table>

	<table class="table tbl-hover">
		<tbody>
			<tr class="yellow">
				<td colspan="2"><?= t('srv.pvp') ?></td>
			</tr>
			<tr>
				<td><?= t('srv.world_type') ?></td>
				<td><?php echo $luaConfig['worldType']; ?></td>
			</tr>
			<tr>
				<td><?= t('srv.hotkey_aimbot') ?></td>
				<td><?php echo toYesNo($luaConfig['hotkeyAimbotEnabled']); ?></td>
			</tr>
			<tr>
				<td><?= t('srv.protection_level') ?></td>
				<td><?php echo $luaConfig['protectionLevel']; ?></td>
			</tr>
			<tr>
				<td><?= t('srv.red_skull') ?></td>
				<td><?php echo $luaConfig['killsToRedSkull']; ?></td>
			</tr>
			<tr>
				<td><?= t('srv.black_skull') ?></td>
				<td><?php echo $luaConfig['killsToBlackSkull']; ?></td>
			</tr>
			<tr>
				<td><?= t('srv.rune_charges') ?></td>
				<td><?php echo toYesNo($luaConfig['removeChargesFromRunes']); ?></td>
			</tr>
			<?php if (isset($luaConfig['timeToDecreaseFrags'])): ?>
				<tr>
					<td><?= t('srv.frag_decrease') ?></td><!-- Legacy servers might need to remove *1000 -->
					<td><?php echo toDuration($luaConfig['timeToDecreaseFrags']*1000); ?></td>
				</tr>
			<?php endif; ?>
			<tr>
				<td><?= t('srv.exp_by_pk') ?></td>
				<td><?php echo toYesNo($luaConfig['experienceByKillingPlayers']); ?></td>
			</tr>

			<?php if ($luaConfig['experienceByKillingPlayers']): ?>
				<tr>
					<td><?= t('srv.exp_threshold') ?></td>
					<td><?php echo $luaConfig['expFromPlayersLevelRange']; ?>% of your level</td>
				</tr>
			<?php endif; ?>

			<tr>
				<td><?= t('srv.white_skull') ?></td>
				<td><?php echo toDuration($luaConfig['whiteSkullTime']); ?></td>
			</tr>
			<tr>
				<td><?= t('srv.pz_lock') ?></td>
				<td><?php echo toDuration($luaConfig['pzLocked']); ?></td>
			</tr>
			<tr>
				<td><?= t('srv.stair_jump') ?></td>
				<td><?php echo toDuration($luaConfig['stairJumpExhaustion']); ?></td>
			</tr>
		</tbody>
	</table>

	<table class="table tbl-hover">
		<tbody>
			<tr class="yellow">
				<td colspan="2"><?= t('srv.other') ?></td>
			</tr>
			<tr>
				<td><?= t('srv.free_premium') ?></td>
				<td><?php echo toYesNo($luaConfig['freePremium']); ?></td>
			</tr>
			<tr>
				<td><?= t('srv.house_rent') ?></td>
				<td><?php echo $luaConfig['houseRentPeriod']; ?></td>
			</tr>
			<tr>
				<td><?= t('srv.house_price') ?></td>
				<td><?php echo $luaConfig['housePriceEachSQM']; ?> gp</td>
			</tr>
			<tr>
				<td><?= t('srv.afk_kick') ?></td>
				<td><?php echo toDuration($luaConfig['kickIdlePlayerAfterMinutes'] * 60 * 1000); ?></td>
			</tr>
			<tr>
				<td><?= t('srv.one_per_account') ?></td>
				<td><?php echo toYesNo($luaConfig['onePlayerOnlinePerAccount']); ?></td>
			</tr>
			<tr>
				<td><?= t('srv.max_players') ?></td>
				<td><?php echo ($luaConfig['maxPlayers'] > 0) ? $luaConfig['maxPlayers'] : t('common.unlimited'); ?></td>
			</tr>
			<tr>
				<td><?= t('srv.outfit_change') ?></td>
				<td><?php echo toYesNo($luaConfig['allowChangeOutfit']); ?></td>
			</tr>
			<?php if (isset($luaConfig['staminaSystem'])): ?>
				<tr>
					<td><?= t('srv.stamina') ?></td>
					<td><?php echo toYesNo($luaConfig['staminaSystem']); ?></td>
				</tr>
			<?php endif; ?>
			<?php if (isset($luaConfig['premiumToCreateMarketOffer'])): ?>
				<tr>
					<td><?= t('srv.market_premium') ?></td>
					<td><?php echo toYesNo($luaConfig['premiumToCreateMarketOffer']); ?></td>
				</tr>
			<?php endif; ?>
			<?php if (isset($luaConfig['marketOfferDuration'])): ?>
				<tr>
					<td><?= t('srv.market_duration') ?></td>
					<td><?php echo toDuration($luaConfig['marketOfferDuration'] * 1000); ?></td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>
<?php else: ?>
	<p><?= h(t('srv.not_imported')) ?></p>
<?php endif;
