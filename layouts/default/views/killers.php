<?php
/**
 * Kill statistics.
 *
 * Prepared by killers.php:
 *   $killersMode  'modern' (TFS 1.x / 0.2 / OTHire), 'legacy' (TFS 0.3), or 'unsupported'
 *   $killers      biggest murderers        [modern]
 *   $victims      biggest victims          [modern]
 *   $latests      20 most recent kills     [modern]
 *   $deaths       30 most recent deaths    [legacy]
 *
 * Each of the four is false when there is nothing to show.
 */
?>

<?php if ($killersMode === 'modern'): ?>

	<h1>Biggest Murders</h1>
	<?php if ($killers): ?>
		<table id="killersTable" class="table table-striped">
			<tr class="yellow">
				<th>Name</th>
				<th>Kills</th>
			</tr>
			<?php foreach ($killers as $killer): ?>
				<tr>
					<td width="70%">
						<a href="characterprofile.php?name=<?= urlencode($killer['killed_by']) ?>"><?= htmlspecialchars($killer['killed_by'], ENT_QUOTES, 'UTF-8') ?></a>
					</td>
					<td width="30%"><?= (int)$killer['kills'] ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php else: ?>
		No player kills exist.
	<?php endif; ?>

	<h1>Biggest Victims</h1>
	<?php if ($victims): ?>
		<table id="victimsTable" class="table table-striped">
			<tr class="yellow">
				<th>Name</th>
				<th>Deaths</th>
			</tr>
			<?php foreach ($victims as $victim): ?>
				<tr>
					<td width="70%">
						<a href="characterprofile.php?name=<?= urlencode($victim['name']) ?>"><?= htmlspecialchars($victim['name'], ENT_QUOTES, 'UTF-8') ?></a>
					</td>
					<td width="30%"><?= (int)$victim['Deaths'] ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php else: ?>
		No player kills exist.
	<?php endif; ?>

	<h1>Latest kills</h1>
	<?php if ($latests): ?>
		<table id="killersTable" class="table table-striped">
			<tr class="yellow">
				<th>Killer</th>
				<th>Time</th>
				<th>Victim</th>
			</tr>
			<?php foreach ($latests as $last): ?>
				<tr>
					<td width="35%">
						<a href="characterprofile.php?name=<?= urlencode($last['killed_by']) ?>"><?= htmlspecialchars($last['killed_by'], ENT_QUOTES, 'UTF-8') ?></a>
					</td>
					<td width="30%"><?= htmlspecialchars(getClock($last['time'], true), ENT_QUOTES, 'UTF-8') ?></td>
					<td width="35%">
						<a href="characterprofile.php?name=<?= urlencode($last['victim']) ?>"><?= htmlspecialchars($last['victim'], ENT_QUOTES, 'UTF-8') ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php else: ?>
		No player kills exist.
	<?php endif; ?>

<?php elseif ($killersMode === 'legacy'): ?>

	<?php if ($deaths): ?>
		<h1>Latest Killers</h1>
		<table id="deathsTable" class="table table-striped">
			<tr class="yellow">
				<th>Killer</th>
				<th>Time</th>
				<th>Victim</th>
			</tr>
			<?php foreach ($deaths as $death): ?>
				<tr>
					<td>
						<a href="characterprofile.php?name=<?= urlencode($death['killed_by']) ?>"><?= htmlspecialchars($death['killed_by'], ENT_QUOTES, 'UTF-8') ?></a>
					</td>
					<td><?= htmlspecialchars(getClock($death['time'], true), ENT_QUOTES, 'UTF-8') ?></td>
					<td>
						At level <?= (int)$death['level'] ?>:
						<a href="characterprofile.php?name=<?= urlencode($death['victim']) ?>"><?= htmlspecialchars($death['victim'], ENT_QUOTES, 'UTF-8') ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php else: ?>
		No player deaths exist.
	<?php endif; ?>

<?php endif; ?>
