<?php
/**
 * Public staff directory.
 *
 * Prepared by team.php: $staffGroups (group_id => ['label', 'members']),
 * highest group first; $loadOutfits.
 */
?>
<h1><?= t('team.title') ?></h1>

<?php if ($staffGroups): ?>
	<?php foreach ($staffGroups as $group): ?>
		<h2><?= h($group['label']) ?></h2>
		<table>
			<tr class="yellow">
				<?php if ($loadOutfits): ?><td><?= t('common.image') ?></td><?php endif; ?>
				<td><?= t('team.col_name') ?></td>
			</tr>
			<?php foreach ($group['members'] as $member): ?>
				<tr>
					<?php if ($loadOutfits): ?>
						<td>
							<img src="<?= h($config['show_outfits']['imageServer']) ?>?id=<?= (int)$member['looktype'] ?>&addons=<?= (int)$member['lookaddons'] ?>&head=<?= (int)$member['lookhead'] ?>&body=<?= (int)$member['lookbody'] ?>&legs=<?= (int)$member['looklegs'] ?>&feet=<?= (int)$member['lookfeet'] ?>" alt="">
						</td>
					<?php endif; ?>
					<td><a href="characterprofile.php?name=<?= urlencode($member['name']) ?>"><?= h($member['name']) ?></a></td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php endforeach; ?>
<?php else: ?>
	<h2><?= t('team.none') ?></h2>
<?php endif; ?>
