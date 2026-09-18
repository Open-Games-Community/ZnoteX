<?php
/**
 * Spell list.
 *
 * Prepared by spells.php - see the docblock there for the variables.
 */
?>
<?php

if ($spells) {
	// Preparing data
	$configVoc = $config['vocations'];
	$types = array_keys($spells);
	$itemImg = static function($id){ return function_exists('znote_item_image_url') ? htmlspecialchars(znote_item_image_url((int)$id, 'gif'), ENT_QUOTES) : ''; };

	// Filter spells by vocation
	$getVoc = (isset($_GET['vocation'])) ? getValue($_GET['vocation'] ?? null) : 'all';
	if ($getVoc !== 'all') {
		$getVoc = (int)$getVoc;
		foreach ($types as $type)
			foreach ($spells[$type] as $name => $spell)
				if (!empty($spell['vocations']))
					if (!in_array($getVoc, $spell['vocations']))
						unset($spells[$type][$name]);
	}

	// Render HTML
	?>

	<div class="znx-acct">

	<div class="znx-acct-head" id="spells"><?= t('spells.title') ?><?php if ($getVoc !== 'all') echo ' ('.$configVoc[$getVoc]['name'].')';?></div>

	<form action="#spells" class="znx-acct-toolbar filter_spells">
		<div class="znx-acct-toolbar__field">
		<label for="vocation"><?= t('spells.filter_voc') ?></label>
		<select id="vocation" name="vocation" class="znx-acct-select">
			<option value="all"><?= t('spells.all') ?></option>
			<?php foreach ($config['vocations'] as $id => $vocation): ?>
				<option value="<?php echo $id; ?>" <?php if ($getVoc === $id) echo "selected"; ?>><?php echo $vocation['name']; ?></option>
			<?php endforeach; ?>
		</select>
		</div>
		<div class="znx-acct-toolbar__submit"><button type="submit" class="znx-acct-btn"><?= t('common.search') ?></button></div>
	</form>

	<div class="znx-acct-head"><?= t('spells.types') ?></div>
	<div class="znx-pill-nav">
		<?php foreach ($types as $type): ?>
		<a href="#spell_<?php echo $type; ?>"><?php echo ucfirst($type); ?></a>
		<?php endforeach; ?>
	</div>

	<div class="znx-acct-head" id="spell_instant"><?= t('spells.instant') ?></div>
	<div class="znx-jump-top"><a href="#spells"><?= t('spells.jump_top') ?></a></div>
	<div class="znx-acct-table-wrap">
	<table class="znx-acct-table">
		<tbody>
			<tr class="yellow">
				<td><?= t('common.name') ?></td>
				<td><?= t('spells.words') ?></td>
				<td><?= t('common.level') ?></td>
				<td><?= t('spells.mana') ?></td>
				<td><?= t('spells.vocations') ?></td>
			</tr>
			<?php foreach ($spells['instant'] as $spell): ?>
			<tr>
				<td><?php echo $spell['name']; ?></td>
				<td><?php echo $spell['words']; ?></td>
				<td><?php echo $spell['lvl']; ?></td>
				<td><?php echo $spell['mana']; ?></td>
				<td><?php
				if (!empty($spell['vocations'])) {
					if ($getVoc !== 'all') {
						echo $configVoc[$getVoc]['name'];
					} else {
						$names = array();
						foreach ($spell['vocations'] as $id) {
							if (isset($configVoc[$id]))
								$names[] = $configVoc[$id]['name'];
						}
						echo implode(',<br>', $names);
					}
				}
				?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>

	<div class="znx-acct-head" id="spell_rune"><?= t('spells.runes') ?></div>
	<div class="znx-jump-top"><a href="#spells"><?= t('spells.jump_top') ?></a></div>
	<div class="znx-acct-table-wrap">
	<table class="znx-acct-table">
		<tbody>
			<tr class="yellow">
				<td><?= t('common.name') ?></td>
				<td><?= t('common.level') ?></td>
				<td><?= t('spells.magic_level') ?></td>
				<td><?= t('spells.image') ?></td>
				<td><?= t('spells.vocations') ?></td>
			</tr>
			<?php foreach ($spells['rune'] as $spell): ?>
			<tr>
				<td><?php echo $spell['name']; ?></td>
				<td><?php echo $spell['lvl']; ?></td>
				<td><?php echo $spell['maglv']; ?></td>
				<td><img src="<?php echo $itemImg($spell['id']); ?>" alt="<?= h(t('spells.rune_image')) ?>"></td>
				<td><?php
				if (!empty($spell['vocations'])) {
					if ($getVoc !== 'all') {
						echo $configVoc[$getVoc]['name'];
					} else {
						$names = array();
						foreach ($spell['vocations'] as $id) {
							if (isset($configVoc[$id]))
								$names[] = $configVoc[$id]['name'];
						}
						echo implode(',<br>', $names);
					}
				}
				?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>

	<?php if (isset($spells['conjure'])): ?>
	<div class="znx-acct-head" id="spell_conjure"><?= t('spells.conjure') ?></div>
	<div class="znx-jump-top"><a href="#spells"><?= t('spells.jump_top') ?></a></div>
	<div class="znx-acct-table-wrap">
	<table class="znx-acct-table">
		<tbody>
			<tr class="yellow">
				<td><?= t('common.name') ?></td>
				<td><?= t('spells.words') ?></td>
				<td><?= t('common.level') ?></td>
				<td><?= t('spells.mana') ?></td>
				<td><?= t('spells.soul') ?></td>
				<td><?= t('spells.charges') ?></td>
				<td><?= t('spells.image') ?></td>
				<td><?= t('spells.vocations') ?></td>
			</tr>
			<?php foreach ($spells['conjure'] as $spell): ?>
			<tr>
				<td><?php echo $spell['name']; ?></td>
				<td><?php echo $spell['words']; ?></td>
				<td><?php echo $spell['lvl']; ?></td>
				<td><?php echo $spell['mana']; ?></td>
				<td><?php echo $spell['soul']; ?></td>
				<td><?php echo $spell['conjureCount']; ?></td>
				<td><img src="<?php echo $itemImg($spell['conjureId']); ?>" alt="<?= h(t('spells.rune_image')) ?>"></td>
				<td><?php
				if (!empty($spell['vocations'])) {
					if ($getVoc !== 'all') {
						echo $configVoc[$getVoc]['name'];
					} else {
						$names = array();
						foreach ($spell['vocations'] as $id) {
							if (isset($configVoc[$id]))
								$names[] = $configVoc[$id]['name'];
						}
						echo implode(',<br>', $names);
					}
				}
				?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<div class="znx-jump-top"><a href="#spells"><?= t('spells.jump_top') ?></a></div>
	<?php endif; ?>

	</div><!-- .znx-acct -->
	<?php
} else {
	?>
	<div class="znx-acct">
	<div class="znx-acct-head"><?= t('spells.title') ?></div>
	<div class="znx-empty-box"><?= h(t('spells.not_loaded')) ?></div>
	</div>
	<?php
}

/* Debug tests
foreach ($spells as $type => $spells) {
	data_dump($spells, false, "Type: $type");
}

// All spell attributes?
'group', 'words', 'lvl', 'level', 'maglv', 'magiclevel', 'charges', 'allowfaruse', 'blocktype', 'mana', 'soul', 'prem', 'aggressive', 'range', 'selftarget', 'needtarget', 'blockwalls', 'needweapon', 'exhaustion', 'groupcooldown', 'needlearn', 'casterTargetOrDirection', 'direction', 'params', 'playernameparam', 'conjureId', 'reagentId', 'conjureCount', 'vocations'
*/
