<?php
/**
 * Equipable items browser.
 *
 * Prepared by items.php: $itemsEnabled and $items. Nothing is loaded here.
 */
?>
<?php if (!$itemsEnabled): ?>

	<?= t('items.disabled') ?>

<?php else: ?>

<?php
if ($items) {
	// Preparing data
	$types = array_keys($items);
	$itemServer = 'http://'.$config['shop']['imageServer'].'/';

	//slotType values and names
	if(isset($_GET['slot'])) {
		switch($_GET['slot']) {
			case 'helmet':
				$slottype = 'head';
				$slottype_name = t('items.helmets');
				break;
			case 'sword':
				$slottype = 'sword';
				$slottype_name = t('items.swords');
				break;
			case 'distance':
				$slottype = 'distance';
				$slottype_name = t('items.distance');
				break;
			case 'wand':
				$slottype = 'wand';
				$slottype_name = t('items.wands');
				break;
			case 'armor':
				$slottype = 'body';
				$slottype_name = t('items.armors');
				break;
			case 'club':
				$slottype = 'club';
				$slottype_name = t('items.clubs');
				break;
			case 'ammunition':
				$slottype = 'ammunition';
				$slottype_name = t('items.ammunition');
				break;
			case 'book':
				$slottype = 'shield';
				$slottype_name = t('items.spellbooks');
				break;
			case 'legs':
				$slottype = 'legs';
				$slottype_name = t('items.legs');
				break;
			case 'axe':
				$slottype = 'axe';
				$slottype_name = t('items.axes');
				break;
			case 'necklace':
				$slottype = 'necklace';
				$slottype_name = t('items.necklaces');
				break;
			case 'feet':
				$slottype = 'feet';
				$slottype_name = t('items.boots');
				break;
			case 'shield':
				$slottype = 'shield';
				$slottype_name = t('items.shields');
				break;
			case 'backpack':
				$slottype = 'backpack';
				$slottype_name = t('items.backpacks');
				break;
			case 'ring':
				$slottype = 'ring';
				$slottype_name = t('items.rings');
				break;
			default:
				$slottype_name = 'null';
				break;
		}
	}

	// Render HTML
	if(isset($_GET['slot']) && ($slottype_name == 'null')) header("Location:items.php");
	?>

		<h1 id="items"><?= t('items.title') ?><?php if (isset($_GET['slot'])) echo ' ('.$slottype_name.')';?></h1>
	<?php if(empty($_GET['slot'])) { ?>
	<table>
		<tbody>
			<tr>
				<td style="text-align:center;"><a href="?slot=helmet"><?= t('items.helmets') ?><br><img src="<?php echo $itemServer.'2471.gif'; ?>" /></a></td>
				<td style="text-align:center;"><a href="?slot=sword"><?= t('items.swords') ?><br><img src="<?php echo $itemServer.'8931.gif'; ?>" /></a></td>
				<td style="text-align:center;"><a href="?slot=shield"><?= t('items.shields') ?><br><img src="<?php echo $itemServer.'2523.gif'; ?>" /></a></td>
				<td style="text-align:center;"><a href="?slot=necklace"><?= t('items.amulets') ?><br><img src="<?php echo $itemServer.'2173.gif'; ?>" /></a></td>
			</tr>
			<tr>
				<td style="text-align:center;"><a href="?slot=armor"><?= t('items.armors') ?><br><img src="<?php echo $itemServer.'2466.gif'; ?>" /></a></td>
				<td style="text-align:center;"><a href="?slot=club"><?= t('items.clubs') ?><br><img src="<?php echo $itemServer.'2444.gif'; ?>" /></a></td>
				<td style="text-align:center;"><a href="?slot=wand"><?= t('items.wands') ?><br><img src="<?php echo $itemServer.'2190.gif'; ?>" /></a></td>
				<td style="text-align:center;"><a href="?slot=ammunition"><?= t('items.ammunition') ?><br><img src="<?php echo $itemServer.'6529.gif'; ?>" /></a></td>
			</tr>
			<tr>
				<td style="text-align:center;"><a href="?slot=legs"><?= t('items.legs') ?><br><img src="<?php echo $itemServer.'2470.gif'; ?>" /></a></td>
				<td style="text-align:center;"><a href="?slot=axe"><?= t('items.axes') ?><br><img src="<?php echo $itemServer.'8925.gif'; ?>" /></a></td>
				<td style="text-align:center;"><a href="?slot=ring"><?= t('items.rings') ?><br><img src="<?php echo $itemServer.'6093.gif'; ?>" /></a></td>
			</tr>
			<tr>
				<td style="text-align:center;"><a href="?slot=feet"><?= t('items.boots') ?><br><img src="<?php echo $itemServer.'2646.gif'; ?>" /></a></td>
				<td style="text-align:center;"><a href="?slot=distance"><?= t('items.distance') ?><br><img src="<?php echo $itemServer.'5803.gif'; ?>" /></a></td>
				<td style="text-align:center;"><a href="?slot=backpack"><?= t('items.backpacks') ?><br><img src="<?php echo $itemServer.'9774.gif'; ?>" /></a></td>
			</tr>
		</tbody>
	</table>
	<?php } else {  ?>
	<table>
		<tbody>
			<tr>
				<td></td>
				<td><?= t('common.name') ?></td>
				<td><?= t('items.attributes') ?></td>
			</tr>

<?php	foreach ($items['item'] as $select) {
			$attributes = array();
			$extradef = NULL;
			$element = NULL;
				if (!empty($select['id'])) $itemid = $select['id'];
				else $itemid = $select['fromid'];

			if (!empty($select['attributes'])) {
				foreach ($select['attributes'] as $att => $value) {
					if($att == 'slotType' || $att == 'weaponType') $slotType = $value;
						if(!empty($slotType) && $slotType == $slottype) $show = true;
						else $show = false;
				}
			}

			if($show == true) { ?>
			<tr>
				<td><img src="<?php echo $itemServer.$itemid.'.gif'; ?>" /></td>
				<td><?php echo ucwords($select['name']); ?></td>
				<td><?php
				foreach ($select['attributes'] as $array => $value) {

					$extra = NULL;
					if($value > 0) $extra = '+';
						switch ($array) {
						case 'weight':
							echo ucwords($array).': '.intval($value/100).'.'.substr($value, -2).' oz<br>';
						break;
						case 'containerSize':
							echo t('items.attr_slots') .': '.$value.'<br>';
						break;
						case 'armor':
							echo ucwords($array).': '.$value.'<br>';
						break;
						case 'attack':
							echo ucwords($array).': '.$value;
							if($element != NULL) echo ' ('.$element.')';
							echo '<br>';
						break;
						case 'defense':
							echo ucwords($array).': '.$value;
							if($extradef != NULL) echo ' ('.$extradef.')';
							echo '<br>';
						break;
						case 'skillFist':
							echo t('items.fight_fist') .': '.$extra.$value.'<br>';
						break;
						case 'skillAxe':
							echo t('items.fight_axe') .': '.$extra.$value.'<br>';
						break;
						case 'skillSword':
							echo t('items.fight_sword') .': '.$extra.$value.'<br>';
						break;
						case 'skillClub':
							echo t('items.fight_club') .': '.$extra.$value.'<br>';
						break;
						case 'skillAxe':
							echo t('items.fight_axe') .': '.$extra.$value.'<br>';
						break;
						case 'skillDist':
							echo t('items.fight_dist') .': '.$extra.$value.'<br>';
						break;
						case 'skillShield':
							echo t('items.fight_shield') .': '.$extra.$value.'<br>';
						break;
						case 'range':
							echo ucwords($array).': '.$value.'<br>';
						break;
						case 'shootType':
							echo t('items.attr_shoot') .': '.ucwords($value).'<br>';
						break;
						case 'hitChance':
							echo t('items.attr_hit') .': '.$extra.$value.'%<br>';
						break;
						case 'magiclevelpoints':
							echo t('items.attr_magic') .': '.$extra.$value.'<br>';
						break;
						case 'absorbPercentEnergy':
							echo t('items.prot_energy') .': '.$extra.$value.'%<br>';
						break;
						case 'absorbPercentFire':
							echo t('items.prot_fire') .': '.$extra.$value.'%<br>';
						break;
						case 'absorbPercentEarth':
							echo t('items.prot_earth') .': '.$extra.$value.'%<br>';
						break;
						case 'absorbPercentPoison':
							echo t('items.prot_poison') .': '.$extra.$value.'%<br>';
						break;
						case 'absorbPercentIce':
							echo t('items.prot_ice') .': '.$extra.$value.'%<br>';
						break;
						case 'absorbPercentHoly':
							echo t('items.prot_holy') .': '.$extra.$value.'%<br>';
						break;
						case 'absorbPercentDeath':
							echo t('items.prot_death') .': '.$extra.$value.'%<br>';
						break;
						case 'absorbPercentLifeDrain':
							echo t('items.prot_lifedrain') .': '.$extra.$value.'%<br>';
						break;
						case 'absorbPercentManaDrain':
							echo t('items.prot_manadrain') .': '.$extra.$value.'%<br>';
						break;
						case 'absorbPercentDrown':
							echo t('items.prot_drown') .': '.$extra.$value.'%<br>';
						break;
						case 'absorbPercentPhysical':
							echo t('items.prot_physical') .': '.$extra.$value.'%<br>';
						break;
						case 'absorbPercentIce':
							echo t('items.prot_ice') .': '.$extra.$value.'%<br>';
						break;
						/**case 'suppressDrunk':
							echo t('items.sup_drunk') .': '. t('common.yes') .'<br>';
						break;
						case 'suppressEnergy':
							echo t('items.sup_energy') .': '. t('common.yes') .'<br>';
						break;
						case 'suppressFire':
							echo t('items.sup_fire') .': '. t('common.yes') .'<br>';
						break;
						case 'suppressPoison':
							echo t('items.sup_poison') .': '. t('common.yes') .'<br>';
						break;
						case 'suppressDrown':
							echo t('items.sup_drown') .': '. t('common.yes') .'<br>';
						break;
						case 'suppressPhysical':
							echo t('items.sup_bleeding') .': '. t('common.yes') .'<br>';
						break;
						case 'suppressFreeze':
							echo t('items.sup_freeze') .': '. t('common.yes') .'<br>';
						break;
						case 'suppressDazzle':
							echo t('items.sup_dazzle') .': '. t('common.yes') .'<br>';
						break;
						case 'suppressCurse':
							echo t('items.sup_curse') .': '. t('common.yes') .'<br>';
						break;
						Those are not necessary in my opinion, but if you want to show
						**/
						case 'speed':
							echo t('items.attr_speed') .': '.$extra.($value/2).'<br>';
						break;
						case 'charges':
							echo t('items.attr_charges') .': '.$value.'<br>';
						break;
					}
				}
			?>
				</td>
			</tr>


<?php
			}
		} ?>

		</tbody>
	</table>

	<?php
	}
} else { ?>
	<h1>Items</h1>
	<p>Items have currently not been loaded into the website by the server admin.</p>
<?php }
?>
<?php endif; ?>
