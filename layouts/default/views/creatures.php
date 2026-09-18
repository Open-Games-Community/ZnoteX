<?php
/**
 * Creature library.
 *
 * Prepared by creatures.php: $creatures, $creatureRaces, $creatureSearch,
 * $creatureRace, $creatureError. Nothing is read from disk here.
 */
?>
<div class="znx-acct">

<div class="znx-acct-head"><?= t('creatures.title') ?></div>

<?php if ($creatureError !== ''): ?>

	<div class="znx-empty-box"><?= htmlspecialchars($creatureError, ENT_QUOTES, 'UTF-8') ?></div>

<?php else: ?>

	<form action="" method="get" class="znx-acct-toolbar">
		<div class="znx-acct-toolbar__field">
		<input type="text" name="search" placeholder="<?= t('creatures.placeholder') ?>" class="znx-acct-select"
			   value="<?= htmlspecialchars($creatureSearch, ENT_QUOTES, 'UTF-8') ?>">
		</div>
		<div class="znx-acct-toolbar__field">
		<select name="race" class="znx-acct-select">
			<option value=""><?= t('creatures.all_races') ?></option>
			<?php foreach ($creatureRaces as $race): ?>
				<option value="<?= htmlspecialchars($race, ENT_QUOTES, 'UTF-8') ?>"
					<?= $race === $creatureRace ? 'selected' : '' ?>>
					<?= htmlspecialchars(ucfirst($race), ENT_QUOTES, 'UTF-8') ?>
				</option>
			<?php endforeach; ?>
		</select>
		</div>
		<div class="znx-acct-toolbar__submit"><button type="submit" class="znx-acct-btn"><?= t('common.search') ?></button></div>
		<?php if ($creatureSearch !== '' || $creatureRace !== ''): ?>
			<div class="znx-acct-toolbar__submit"><a href="creatures.php" class="znx-acct-edit-btn"><?= t('common.clear') ?></a></div>
		<?php endif; ?>
	</form>

	<div class="znx-acct-info"><?= count($creatures) ?> creature<?= count($creatures) === 1 ? '' : 's' ?>.</div>

	<?php if ($creatures): ?>
		<div class="znx-acct-table-wrap">
		<table class="znx-acct-table">
			<tr class="yellow">
				<td>Name</td>
				<td>Race</td>
				<td>Health</td>
				<td><?= t('creatures.experience') ?></td>
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
		</div>
	<?php else: ?>
		<div class="znx-empty-box"><?= t('creatures.no_match') ?></div>
	<?php endif; ?>

<?php endif; ?>

</div><!-- .znx-acct -->
