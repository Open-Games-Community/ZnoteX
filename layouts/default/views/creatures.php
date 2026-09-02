<?php
/**
 * Creature library.
 *
 * Prepared by creatures.php: $creatures, $creatureRaces, $creatureSearch,
 * $creatureRace, $creatureError. Nothing is read from disk here.
 */
?>
<h1>Creatures</h1>

<?php if ($creatureError !== ''): ?>

	<p><?= htmlspecialchars($creatureError, ENT_QUOTES, 'UTF-8') ?></p>

<?php else: ?>

	<form action="" method="get">
		<input type="text" name="search" placeholder="Creature name"
			   value="<?= htmlspecialchars($creatureSearch, ENT_QUOTES, 'UTF-8') ?>">
		<select name="race">
			<option value="">All races</option>
			<?php foreach ($creatureRaces as $race): ?>
				<option value="<?= htmlspecialchars($race, ENT_QUOTES, 'UTF-8') ?>"
					<?= $race === $creatureRace ? 'selected' : '' ?>>
					<?= htmlspecialchars(ucfirst($race), ENT_QUOTES, 'UTF-8') ?>
				</option>
			<?php endforeach; ?>
		</select>
		<input type="submit" value="Search" class="btn btn-info">
		<?php if ($creatureSearch !== '' || $creatureRace !== ''): ?>
			<a href="creatures.php" class="btn">Clear</a>
		<?php endif; ?>
	</form>

	<p class="txt"><?= count($creatures) ?> creature<?= count($creatures) === 1 ? '' : 's' ?>.</p>

	<?php if ($creatures): ?>
		<table class="table table-striped table-hover">
			<tr class="yellow">
				<td>Name</td>
				<td>Race</td>
				<td>Health</td>
				<td>Experience</td>
				<td>Speed</td>
			</tr>
			<?php foreach ($creatures as $creature): ?>
				<tr>
					<td><?= htmlspecialchars($creature['name'], ENT_QUOTES, 'UTF-8') ?></td>
					<td><?= htmlspecialchars(ucfirst($creature['race']), ENT_QUOTES, 'UTF-8') ?></td>
					<td><?= number_format($creature['health']) ?></td>
					<td><?= number_format($creature['experience']) ?></td>
					<td><?= number_format($creature['speed']) ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php else: ?>
		<p>No creature matches.</p>
	<?php endif; ?>

<?php endif; ?>
