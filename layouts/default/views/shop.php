<?php

if ($shop['enabled']) {
?>

<h1><?= t('shop.offers') ?></h1>
<?php
if ($loggedin === true) {
	if (!empty($_POST['buy']) && isset($_SESSION['shop_session']) && $_SESSION['shop_session'] == ($_POST['session'] ?? null)) {
		if ($user_znote_data['points'] >= $buy['points']) {
			?><td><?= t('shop.you_have') ?> <?php echo (int)($user_znote_data['points'] - $buy['points']); ?> <?= t('common.points') ?>. (<a href="buypoints.php"><?= t('shop.buy_points') ?></a>).</td><?php
		} else {
			?><td><?= t('shop.you_have') ?> <?php echo $user_znote_data['points']; ?> <?= t('common.points') ?>. (<a href="buypoints.php"><?= t('shop.buy_points') ?></a>).</td><?php
		}
	} else {
		?><td><?= t('shop.you_have') ?> <?php echo $user_znote_data['points']; ?> <?= t('common.points') ?>. (<a href="buypoints.php"><?= t('shop.buy_points') ?></a>).</td><?php
	}
	if ($config['shop_auction']['characterAuction']) {
		?>
		<p><?= t('shop.chars_hint') ?> <a href="auctionChar.php"><?= t('auction.title') ?></a></p>
		<?php
	}
} else {
	?><p><?= t('shop.need_login') ?></p><?php
}

$outfitsIds = array(136,137,138,139,140,141,142,147,148,149,150,155,156,157,158,252,269,270,279,288,324,336,366,431,433,464,466,471,513,514,542,128,129,130,131,132,133,134,143,144,145,146,151,152,153,154,251,268,273,278,289,325,335,367,430,432,463,465,472,512,516,541);
$category_items = array();
$category_premium = array();
$category_outfits = array();
$category_mounts = array();
$category_misc = array();
foreach ($shop_list as $key => $offer) {

	switch ($offer['type']) {
		case 1:
			$category_items[$key] = $offer;
		break;
		case 2:
			$category_premium[$key] = $offer;
		break;
		case 3:
			$category_misc[$key] = $offer;
		break;
		case 4:
			$category_misc[$key] = $offer;
		break;
		case 5:
			$category_outfits[$key] = $offer;
		break;
		case 6:
			$category_mounts[$key] = $offer;
		break;
		default:
			$category_misc[$key] = $offer;
		break;
	}
}

// Render a bunch of tables (one for each category)
?>
<div id="categoryNavigator">
	<a class="nav_link" href="#all"><?= t('shop.cat_all') ?></a>
	<?php if (!empty($category_items)): ?><a class="nav_link" href="#cat_itemids"><?= t('shop.cat_items') ?></a><?php endif; ?>
	<?php if (!empty($category_premium)): ?><a class="nav_link" href="#cat_premium"><?= t('shop.cat_premium') ?></a><?php endif; ?>
	<?php if (!empty($category_outfits)): ?><a class="nav_link" href="#cat_outfits"><?= t('shop.cat_outfits') ?></a><?php endif; ?>
	<?php if (!empty($category_mounts)): ?><a class="nav_link" href="#cat_mounts"><?= t('shop.cat_mounts') ?></a><?php endif; ?>
	<?php if (!empty($category_misc)): ?><a class="nav_link" href="#cat_misc"><?= t('shop.cat_misc') ?></a><?php endif; ?>
</div>
<script type="text/javascript">
	function domReady () {
		var links = document.getElementsByClassName("nav_link");
		for (var i=0; i < links.length; i++) {
			links[i].addEventListener('click', function(e){
				e.preventDefault();
				// Hide all tables
				for (var x=0; x < links.length; x++) {
					var hash = links[x].hash.substr(1);
					if (hash != 'all') {
						var table = document.getElementById(hash);
						if (table.classList.contains("show")) {
							table.classList.remove("show");
							table.classList.add("hide");
						}
					}
				}
				// Display only the one we selected
				var hash = this.hash.substr(1);
				if (hash != 'all') {
					var target = document.getElementById(hash);
					if (target.classList.contains('hide')) {
						target.classList.remove("hide");
						target.classList.add("show");
					}
				} else { // We clicked to show all tables
					// Show all tables
					for (var x=0; x < links.length; x++) {
						var hash = links[x].hash.substr(1);
						if (hash != 'all') {
							var table = document.getElementById(hash);
							if (table.classList.contains("hide")) {
								table.classList.remove("hide");
								table.classList.add("show");
							}
						}
					}
				}
			});
		}
	}
	// Mozilla, Opera, Webkit
	if ( document.addEventListener ) {
		document.addEventListener( "DOMContentLoaded", function(){
		document.removeEventListener( "DOMContentLoaded", arguments.callee, false);
		domReady();
	  }, false );
	// If IE event model is used
	} else if ( document.attachEvent ) {
		// ensure firing before onload
		document.attachEvent("onreadystatechange", function(){
		if ( document.readyState === "complete" ) {
			document.detachEvent( "onreadystatechange", arguments.callee );
			domReady();
		}
		});
	}
</script>

<?php if (!empty($category_items)): ?>
	<!-- ITEMIDS -->
	<table class="show" id="cat_itemids">
		<tr class="yellow">
			<td><?= t('shop.item') ?></td>
			<?php if ($config['shop']['showImage']) { ?><td><?= t('shop.image') ?></td><?php } ?>
			<td><?= t('shop.count') ?></td>
			<td><?= t('shop.points_col') ?></td>
			<?php if ($loggedin === true): ?><td><?= t('shop.action') ?></td><?php endif; ?>
		</tr>
		<?php foreach ($category_items as $key => $offers): ?>
			<tr class="special">
				<td><?php echo $offers['description']; ?></td>
				<?php if ($config['shop']['showImage']):?>
					<td><img src="http://<?php echo $config['shop']['imageServer']; ?>/<?php echo $offers['itemid']; ?>.<?php echo $config['shop']['imageType']; ?>" alt="img"></td>
				<?php endif; ?>
				<td><?php echo $offers['count']; ?>x</td>
				<td><?php echo $offers['points']; ?></td>
				<?php if ($loggedin === true): ?>
				<td>
					<form action="" method="POST">
						<input type="hidden" name="buy" value="<?php echo (int)$key; ?>">
						<input type="hidden" name="session" value="<?php echo time(); ?>">
						<input type="submit" value="<?= h(t('shop.purchase')) ?>"  class="needconfirmation" data-item-name="<?php echo $offers['description']; ?>" data-item-cost="<?php echo $offers['points']; ?>">
					</form>
				</td>
				<?php endif; ?>
			</tr>
		<?php endforeach; ?>
	</table>
<?php endif; ?>
<?php if (!empty($category_premium)): ?>
<!-- PREMIUM DURATION -->
<table class="show" id="cat_premium">
	<tr class="yellow">
		<td><?= t('shop.description') ?></td>
		<?php if ($config['shop']['showImage']) { ?><td><?= t('shop.image') ?></td><?php } ?>
		<td><?= t('shop.duration') ?></td>
		<td><?= t('shop.points_col') ?></td>
		<?php if ($loggedin === true): ?><td><?= t('shop.action') ?></td><?php endif; ?>
	</tr>
	<?php foreach ($category_premium as $key => $offers): ?>
		<tr class="special">
			<td><?php echo $offers['description']; ?></td>
			<?php if ($config['shop']['showImage']):?>
				<td><img src="http://<?php echo $config['shop']['imageServer']; ?>/<?php echo $offers['itemid']; ?>.<?php echo $config['shop']['imageType']; ?>" alt="img"></td>
			<?php endif; ?>
				<td><?= h(t('shop.days', ['count' => $offers['count']])) ?></td>
			<td><?php echo $offers['points']; ?></td>
			<?php if ($loggedin === true): ?>
			<td>
				<form action="" method="POST">
					<input type="hidden" name="buy" value="<?php echo (int)$key; ?>">
					<input type="hidden" name="session" value="<?php echo time(); ?>">
					<input type="submit" value="<?= h(t('shop.purchase')) ?>"  class="needconfirmation" data-item-name="<?php echo $offers['description']; ?>" data-item-cost="<?php echo $offers['points']; ?>">
				</form>
			</td>
			<?php endif; ?>
		</tr>
	<?php endforeach; ?>
</table>
<?php endif; ?>
<?php if (!empty($category_outfits)): ?>
<!-- OUTFITS -->
<table class="show" id="cat_outfits">
	<tr class="yellow">
		<td><?= t('shop.description') ?></td>
		<?php if ($config['shop']['showImage']) { ?><td><?= t('shop.image') ?></td><?php } ?>
		<td><?= t('shop.points_col') ?></td>
		<?php if ($loggedin === true): ?><td><?= t('shop.action') ?></td><?php endif; ?>
	</tr>
	<?php foreach ($category_outfits as $key => $offers):
		if (!is_array($offers['itemid'])) $offers['itemid'] = [$offers['itemid']];
		if (COUNT($offers['itemid']) > 2): ?>
			<tr class="special">
				<td colspan="2">
					<p><strong><?= t('shop.error') ?></strong> <?= h(t('shop.outfit_limit_error', ['count' => COUNT($offers['itemid'])])) ?>
						<br>[<?php echo implode(',', $offers['itemid']); ?>]</p>
				</td>
			</tr>
		<?php endif; ?>
		<tr class="special">
			<td><?php echo $offers['description']; ?></td>
			<?php if ($config['show_outfits']['shop']):?>
				<td><?php foreach($offers['itemid'] as $outfitId): ?>
					<img src="<?php echo $config['show_outfits']['imageServer']; ?>?id=<?php echo $outfitId; ?>&addons=<?php echo $offers['count']; ?>&head=<?php echo rand(1, 132); ?>&body=<?php echo rand(1, 132); ?>&legs=<?php echo rand(1, 132); ?>&feet=<?php echo rand(1, 132); ?>" alt="img">
				<?php endforeach; ?></td>
			<?php endif; ?>
			<td><?php echo $offers['points']; ?></td>
			<?php if ($loggedin === true): ?>
			<td>
				<form action="" method="POST">
					<input type="hidden" name="buy" value="<?php echo (int)$key; ?>">
					<input type="hidden" name="session" value="<?php echo time(); ?>">
					<input type="submit" value="<?= h(t('shop.purchase')) ?>"  class="needconfirmation" data-item-name="<?php echo $offers['description']; ?>" data-item-cost="<?php echo $offers['points']; ?>">
				</form>
			</td>
			<?php endif; ?>
		</tr>
	<?php endforeach; ?>
</table>
<?php endif; ?>
<?php if (!empty($category_mounts)): ?>
<!-- MOUNTS -->
<table class="show" id="cat_mounts">
	<tr class="yellow">
		<td><?= t('shop.description') ?></td>
		<?php if ($config['show_outfits']['shop']) { ?><td><?= t('shop.image') ?></td><?php } ?>
		<td><?= t('shop.points_col') ?></td>
		<?php if ($loggedin === true): ?><td><?= t('shop.action') ?></td><?php endif; ?>
	</tr>
	<?php foreach ($category_mounts as $key => $offers): ?>
		<tr class="special">
			<td><?php echo $offers['description']; ?></td>
			<?php if ($config['shop']['showImage']):?>
				<td><img src="<?php echo $config['show_outfits']['imageServer']; ?>?id=<?php echo $outfitsIds[rand(0,count($outfitsIds)-1)]; ?>&addons=<?php echo rand(1, 3); ?>&head=<?php echo rand(1, 132); ?>&body=<?php echo rand(1, 132); ?>&legs=<?php echo rand(1, 132); ?>&feet=<?php echo rand(1, 132); ?>&mount=<?php echo $offers['itemid']; ?>&direction=2" alt="img"></td>
			<?php endif; ?>
			<td><?php echo $offers['points']; ?></td>
			<?php if ($loggedin === true): ?>
			<td>
				<form action="" method="POST">
					<input type="hidden" name="buy" value="<?php echo (int)$key; ?>">
					<input type="hidden" name="session" value="<?php echo time(); ?>">
					<input type="submit" value="<?= h(t('shop.purchase')) ?>"  class="needconfirmation" data-item-name="<?php echo $offers['description']; ?>" data-item-cost="<?php echo $offers['points']; ?>">
				</form>
			</td>
			<?php endif; ?>
		</tr>
	<?php endforeach; ?>
</table>
<?php endif; ?>
<?php if (!empty($category_misc)): ?>
<!-- MISCELLANEOUS -->
<table class="show" id="cat_misc">
	<tr class="yellow">
		<td><?= t('shop.description') ?></td>
		<?php if ($config['shop']['showImage']) { ?><td><?= t('shop.image') ?></td><?php } ?>
		<td><?= t('shop.count_dur') ?></td>
		<td><?= t('shop.points_col') ?></td>
		<?php if ($loggedin === true): ?><td><?= t('shop.action') ?></td><?php endif; ?>
	</tr>
	<?php foreach ($category_misc as $key => $offers): ?>
		<tr class="special">
			<td><?php echo $offers['description']; ?></td>
			<?php if ($config['shop']['showImage']):?>
				<td><img src="http://<?php echo $config['shop']['imageServer']; ?>/<?php echo $offers['itemid']; ?>.<?php echo $config['shop']['imageType']; ?>" alt="img"></td>
			<?php endif;
			if ($offers['count'] === 0): ?>
				<td><?= t('common.unlimited') ?></td>
			<?php else: ?>
				<td><?php echo $offers['count']; ?>x</td>
			<?php endif; ?>
			<td><?php echo $offers['points']; ?></td>
			<?php if ($loggedin === true): ?>
			<td>
				<form action="" method="POST">
					<input type="hidden" name="buy" value="<?php echo (int)$key; ?>">
					<input type="hidden" name="session" value="<?php echo time(); ?>">
					<input type="submit" value="<?= h(t('shop.purchase')) ?>"  class="needconfirmation" data-item-name="<?php echo $offers['description']; ?>" data-item-cost="<?php echo $offers['points']; ?>">
				</form>
			</td>
			<?php endif; ?>
		</tr>
	<?php endforeach; ?>
</table>
<?php endif; ?>

<?php if ($shop['enableShopConfirmation']) { ?>
<script src="https://code.jquery.com/jquery-latest.min.js" type="text/javascript"></script>
<script>
	$(document).ready(function(){
		$(".needconfirmation").each(function(e){
			$(this).click(function(e){
				var itemname = $(this).attr("data-item-name");
				var itemcost = $(this).attr("data-item-cost");
				var r = confirm("Do you really want to purchase "+itemname+" for "+itemcost+" points?")
				if(r == false){
					e.preventDefault();
				}
			});
		});
	});
</script>
<?php }

	// Store current timestamp to prevent page-reload from processing old purchase
	$_SESSION['shop_session'] = time();

} else echo '<h1>'. t('buypoints.disabled') .'</h1><p>'. t('buypoints.disabled_text') .'</p>';
