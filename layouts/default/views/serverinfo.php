<?php
/**
 * Server information.
 *
 * Prepared by serverinfo.php - see the docblock there for the variables.
 */
?>

<?php if ($stagesUpdated): ?>
	<p><strong>Logged in as admin, loading engine/XML/stages.xml file and updating cache.</strong></p>
<?php endif; ?>

<?php if ($stagesFailed): ?>
	<p><strong>Failed to load engine/XML/stages.xml file.</strong></p>
<?php endif; ?>

<?php if ($showStagesForm): ?>
	<form action="">
		<input type="submit" name="loadStages" value="Load stages.xml">
	</form>
<?php endif; ?>

<?php if ($showConfigForm): ?>
	<br>
	<form action="" method="POST">
		<label for="configData">Find your OT server folder, put the text inside config.lua into this text field:</label><br>
		<textarea name="configData" placeholder="Open config.lua and copy the content into this text area."></textarea><br>
		<input type="submit" name="loadConfig" value="Load config data">
	</form>
<?php endif; ?>

<h1>Server Information</h1>
<p>Here you will find all basic information about <b><?php echo $config['site_title']; ?></b></p>

<?php 
if (
	($stagesData && isset($stagesData['enabled']) && $stagesData['enabled']) 
	|| (isset($luaConfig['experienceStages']) && $luaConfig['experienceStages'] === true)
): 
	$stages = true; ?>
	<h2>Server rates</h2>
	<table class="table tbl-hover">
		<tbody>
			<tr class="yellow">
				<td>Minimum level</td>
				<td>Maximum level</td>
				<td>Multiplier</td>
			</tr>
			<?php foreach ($stagesData['stages'] as $stage): ?>
				<tr>
					<td><?php echo $stage['minlevel']; ?></td>
					<td><?php echo (isset($stage['maxlevel'])) ? $stage['maxlevel'] : "Unlimited"; ?></td>
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
					<td>Experience rate</td>
				<?php endif; ?>
				<td>Skills rate</td>
				<td>Magic rate</td>
				<td>Loot rate</td>
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

	<h2>Miscellaneous information</h2>
	<table class="table tbl-hover">
		<tbody>
			<tr class="yellow">
				<td colspan="2">Connection information</td>
			</tr>
			<tr>
				<td>Client</td>
				<td><?php echo ($config['client'] / 100); ?></td>
			</tr>
			<tr>
				<td>IP</td>
				<td><?php echo $_SERVER['SERVER_NAME']; ?></td>
			</tr>
			<tr>
				<td>Port</td>
				<td><?php echo $luaConfig['loginProtocolPort']; ?></td>
			</tr>
		</tbody>
	</table>

	<table class="table tbl-hover">
		<tbody>
			<tr class="yellow">
				<td colspan="2">PvP information</td>
			</tr>
			<tr>
				<td>World type</td>
				<td><?php echo $luaConfig['worldType']; ?></td>
			</tr>
			<tr>
				<td>Hotkey aimbot</td>
				<td><?php echo toYesNo($luaConfig['hotkeyAimbotEnabled']); ?></td>
			</tr>
			<tr>
				<td>Protection level</td>
				<td><?php echo $luaConfig['protectionLevel']; ?></td>
			</tr>
			<tr>
				<td>Kills to red skull</td>
				<td><?php echo $luaConfig['killsToRedSkull']; ?></td>
			</tr>
			<tr>
				<td>Kills to black skull</td>
				<td><?php echo $luaConfig['killsToBlackSkull']; ?></td>
			</tr>
			<tr>
				<td>Remove rune charges</td>
				<td><?php echo toYesNo($luaConfig['removeChargesFromRunes']); ?></td>
			</tr>
			<?php if (isset($luaConfig['timeToDecreaseFrags'])): ?>
				<tr>
					<td>Time to decrease frags</td><!-- Legacy servers might need to remove *1000 -->
					<td><?php echo toDuration($luaConfig['timeToDecreaseFrags']*1000); ?></td>
				</tr>
			<?php endif; ?>
			<tr>
				<td>Experience by killing players</td>
				<td><?php echo toYesNo($luaConfig['experienceByKillingPlayers']); ?></td>
			</tr>

			<?php if ($luaConfig['experienceByKillingPlayers']): ?>
				<tr>
					<td>Experience gain kill threshold:</td>
					<td><?php echo $luaConfig['expFromPlayersLevelRange']; ?>% of your level</td>
				</tr>
			<?php endif; ?>

			<tr>
				<td>White skull duration</td>
				<td><?php echo toDuration($luaConfig['whiteSkullTime']); ?></td>
			</tr>
			<tr>
				<td>Protection zone lock (non lethal attack)</td>
				<td><?php echo toDuration($luaConfig['pzLocked']); ?></td>
			</tr>
			<tr>
				<td>Stair jump exhaust</td>
				<td><?php echo toDuration($luaConfig['stairJumpExhaustion']); ?></td>
			</tr>
		</tbody>
	</table>

	<table class="table tbl-hover">
		<tbody>
			<tr class="yellow">
				<td colspan="2">Other information</td>
			</tr>
			<tr>
				<td>Free premium</td>
				<td><?php echo toYesNo($luaConfig['freePremium']); ?></td>
			</tr>
			<tr>
				<td>House rent period</td>
				<td><?php echo $luaConfig['houseRentPeriod']; ?></td>
			</tr>
			<tr>
				<td>House SQM price</td>
				<td><?php echo $luaConfig['housePriceEachSQM']; ?> gp</td>
			</tr>
			<tr>
				<td>AFK kickout</td>
				<td><?php echo toDuration($luaConfig['kickIdlePlayerAfterMinutes'] * 60 * 1000); ?></td>
			</tr>
			<tr>
				<td>One player online per account</td>
				<td><?php echo toYesNo($luaConfig['onePlayerOnlinePerAccount']); ?></td>
			</tr>
			<tr>
				<td>Max players online server limit</td>
				<td><?php echo ($luaConfig['maxPlayers'] > 0) ? $luaConfig['maxPlayers'] : 'Unlimited'; ?></td>
			</tr>
			<tr>
				<td>Allow outfit change</td>
				<td><?php echo toYesNo($luaConfig['allowChangeOutfit']); ?></td>
			</tr>
			<?php if (isset($luaConfig['staminaSystem'])): ?>
				<tr>
					<td>Stamina system</td>
					<td><?php echo toYesNo($luaConfig['staminaSystem']); ?></td>
				</tr>
			<?php endif; ?>
			<?php if (isset($luaConfig['premiumToCreateMarketOffer'])): ?>
				<tr>
					<td>Premium to add items to market</td>
					<td><?php echo toYesNo($luaConfig['premiumToCreateMarketOffer']); ?></td>
				</tr>
			<?php endif; ?>
			<?php if (isset($luaConfig['marketOfferDuration'])): ?>
				<tr>
					<td>Market offer duration</td>
					<td><?php echo toDuration($luaConfig['marketOfferDuration'] * 1000); ?></td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>
<?php else: ?>
	<p>The server administrator has yet to import server information to this page.</p>
<?php endif;
