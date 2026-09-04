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
	$itemServer = 'http://'.$config['shop']['imageServer'].'/';

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

	<h1 id="spells">Spells<?php if ($getVoc !== 'all') echo ' ('.$configVoc[$getVoc]['name'].')';?></h1>

	<form action="#spells" class="filter_spells">
		<label for="vocation">Filter vocation:</label>
		<select id="vocation" name="vocation">
			<option value="all">All</option>
			<?php foreach ($config['vocations'] as $id => $vocation): ?>
				<option value="<?php echo $id; ?>" <?php if ($getVoc === $id) echo "selected"; ?>><?php echo $vocation['name']; ?></option>
			<?php endforeach; ?>
		</select>
		<input type="submit" value="Search">
	</form>

	<h2>Spell types:</h2>
	<ul>
		<?php foreach ($types as $type): ?>
		<li><a href="#spell_<?php echo $type; ?>"><?php echo ucfirst($type); ?></a></li>
		<?php endforeach; ?>
	</ul>

	<h2 id="spell_instant">Instant Spells</h2>
	<a href="#spells">Jump to top</a>
	<table class="table tbl-hover">
		<tbody>
			<tr class="yellow">
				<td>Name</td>
				<td>Words</td>
				<td>Level</td>
				<td>Mana</td>
				<td>Vocations</td>
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

	<h2 id="spell_rune">Magical Runes</h2>
	<a href="#spells">Jump to top</a>
	<table class="table tbl-hover">
		<tbody>
			<tr class="yellow">
				<td>Name</td>
				<td>Level</td>
				<td>Magic Level</td>
				<td>Image</td>
				<td>Vocations</td>
			</tr>
			<?php foreach ($spells['rune'] as $spell): ?>
			<tr>
				<td><?php echo $spell['name']; ?></td>
				<td><?php echo $spell['lvl']; ?></td>
				<td><?php echo $spell['maglv']; ?></td>
				<td><img src="<?php echo $itemServer.$spell['id'].'.gif'; ?>" alt="Rune image"></td>
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

	<?php if (isset($spells['conjure'])): ?>
	<h2 id="spell_conjure">Conjure Spells</h2>
	<a href="#spells">Jump to top</a>
	<table class="table tbl-hover">
		<tbody>
			<tr class="yellow">
				<td>Name</td>
				<td>Words</td>
				<td>Level</td>
				<td>Mana</td>
				<td>Soul</td>
				<td>Charges</td>
				<td>Image</td>
				<td>Vocations</td>
			</tr>
			<?php foreach ($spells['conjure'] as $spell): ?>
			<tr>
				<td><?php echo $spell['name']; ?></td>
				<td><?php echo $spell['words']; ?></td>
				<td><?php echo $spell['lvl']; ?></td>
				<td><?php echo $spell['mana']; ?></td>
				<td><?php echo $spell['soul']; ?></td>
				<td><?php echo $spell['conjureCount']; ?></td>
				<td><img src="<?php echo $itemServer.$spell['conjureId'].'.gif'; ?>" alt="Rune image"></td>
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
	<a href="#spells">Jump to top</a>
	<?php endif; ?>
	<?php
} else {
	?>
	<h1>Spells</h1>
	<p>Spells have currently not been loaded into the website by the server admin.</p>
	<?php
}

/* Debug tests
foreach ($spells as $type => $spells) {
	data_dump($spells, false, "Type: $type");
}

// All spell attributes?
'group', 'words', 'lvl', 'level', 'maglv', 'magiclevel', 'charges', 'allowfaruse', 'blocktype', 'mana', 'soul', 'prem', 'aggressive', 'range', 'selftarget', 'needtarget', 'blockwalls', 'needweapon', 'exhaustion', 'groupcooldown', 'needlearn', 'casterTargetOrDirection', 'direction', 'params', 'playernameparam', 'conjureId', 'reagentId', 'conjureCount', 'vocations'
*/
