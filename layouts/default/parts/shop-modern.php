<?php
$znxShopH = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$shopSession = '';
if (($loggedin ?? false) === true) {
	$shopSession = bin2hex(random_bytes(32));
	$_SESSION['shop_session'] = $shopSession;
}

if (empty($shop['enabled'])) {
	echo '<h1>' . t('buypoints.disabled') . '</h1><p>' . t('buypoints.disabled_text') . '</p>';
	return;
}

$outfitFallbackIds = array(136,137,138,139,140,141,142,147,148,149,150,155,156,157,158,252,269,270,279,288,324,336,366,431,433,464,466,471,513,514,542,128,129,130,131,132,133,134,143,144,145,146,151,152,153,154,251,268,273,278,289,325,335,367,430,432,463,465,472,512,516,541);
$shopCategories = array(
	'outfits' => array('label' => t('shop.cat_outfits'), 'items' => array()),
	'mounts' => array('label' => t('shop.cat_mounts'), 'items' => array()),
	'items' => array('label' => t('shop.cat_items'), 'items' => array()),
	'premium' => array('label' => t('shop.cat_premium'), 'items' => array()),
	'misc' => array('label' => t('shop.cat_misc'), 'items' => array()),
);

foreach ((array)$shop_list as $offerId => $offer) {
	switch ((int)($offer['type'] ?? 0)) {
		case 1: $shopCategories['items']['items'][$offerId] = $offer; break;
		case 2: $shopCategories['premium']['items'][$offerId] = $offer; break;
		case 5: $shopCategories['outfits']['items'][$offerId] = $offer; break;
		case 6: $shopCategories['mounts']['items'][$offerId] = $offer; break;
		default: $shopCategories['misc']['items'][$offerId] = $offer; break;
	}
}

$visibleCategories = array_filter($shopCategories, static fn($category): bool => !empty($category['items']));
$firstCategory = true;

$renderImage = static function (array $offer, string $category) use ($config, $outfitFallbackIds, $znxShopH): string {
	$type = (int)($offer['type'] ?? 0);
	$itemId = $offer['itemid'] ?? 0;
	$count = (int)($offer['count'] ?? 0);

	if ($category === 'outfits' && !empty($config['show_outfits']['shop'])) {
		$ids = is_array($itemId) ? array_values($itemId) : array($itemId);
		$html = '';
		foreach (array_slice($ids, 0, 2) as $id) {
			$html .= '<img class="znx-shop-card__img is-outfit" src="' . $znxShopH($config['show_outfits']['imageServer']) . '?id=' . (int)$id . '&addons=' . $count . '&head=78&body=68&legs=58&feet=76" alt="">';
		}
		return $html;
	}

	if ($category === 'mounts' && !empty($config['show_outfits']['shop'])) {
		$outfitId = $outfitFallbackIds[array_rand($outfitFallbackIds)];
		return '<img class="znx-shop-card__img is-mount" src="' . $znxShopH($config['show_outfits']['imageServer']) . '?id=' . (int)$outfitId . '&addons=3&head=78&body=68&legs=58&feet=76&mount=' . (int)$itemId . '&direction=2" alt="">';
	}

	if (!empty($config['shop']['showImage']) && (int)$itemId > 0 && $type !== 3 && $type !== 4) {
		return '<img class="znx-shop-card__img" src="' . $znxShopH(znote_item_image_url((int)$itemId)) . '" alt="">';
	}

	$icon = $type === 2 ? 'VIP' : ($type === 3 ? 'SEX' : ($type === 4 ? 'NAME' : 'ITEM'));
	return '<span class="znx-shop-card__placeholder" aria-hidden="true">' . $icon . '</span>';
};

$offerMeta = static function (array $offer, string $category) use ($znxShopH): string {
	$count = (int)($offer['count'] ?? 0);
	if ($category === 'premium') return $znxShopH(t('shop.days', array('count' => $count)));
	if ($category === 'items' && $count > 1) return $count . 'x';
	if ($category === 'misc') return $count === 0 ? t('common.unlimited') : $count . 'x';
	if ($category === 'outfits' && $count > 0) return 'Addons ' . $count;
	return '';
};
?>

<style>
.znx-shop-modern{--shop-surface:var(--box-inner-bg,var(--box-bg,var(--nz-panel,var(--wc-bg-2,var(--z-panel-2,var(--s-panel,var(--primary,rgb(30,33,40))))))));--shop-surface-2:color-mix(in srgb,var(--shop-surface) 82%,#000);--shop-well:color-mix(in srgb,var(--shop-surface) 65%,#000);--shop-text:var(--font-color,var(--text,var(--nz-text,var(--wc-text,var(--z-text,var(--s-text,rgb(155,162,177)))))));--shop-muted:color-mix(in srgb,var(--shop-text) 58%,transparent);--shop-border:var(--border,var(--box-inner-border,var(--box-border,var(--nz-border-soft,var(--wc-line,var(--z-border,var(--s-border2,rgb(19,20,23))))))));--shop-accent:var(--accent,var(--link,var(--nz-accent,#d1a233)));--shop-radius:6px;margin:0 0 18px;color:var(--shop-text)}
.znx-shop-modern *{box-sizing:border-box}
.znx-shop-hero{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin:0 0 12px;padding:14px 16px;border:1px solid var(--shop-border);border-radius:var(--shop-radius);background:var(--shop-surface);box-shadow:inset 0 1px 0 rgba(255,255,255,.03)}
.znx-shop-hero h1{margin:0 0 5px;font-size:26px;line-height:1.05;color:var(--shop-accent)}
.znx-shop-hero p{margin:0;color:var(--shop-muted)}
.znx-shop-hero__actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:9px}
.znx-shop-hero__link{display:inline-flex;align-items:center;padding:6px 13px;border:1px solid var(--shop-accent);border-radius:999px;color:var(--shop-accent);font-weight:700;font-size:12px;text-decoration:none;white-space:nowrap}
.znx-shop-hero__link:hover{background:var(--shop-accent);color:#1a1510}
.znx-shop-balance{min-width:135px;padding:9px 12px;border:1px solid var(--shop-accent);border-radius:var(--shop-radius);background:var(--shop-well);color:var(--shop-muted);text-align:center}
.znx-shop-balance strong{display:block;font-size:22px;line-height:1;color:var(--shop-accent)}
.znx-shop-balance a{color:var(--shop-accent)}
.znx-shop-tabs{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 12px;padding:7px;border:1px solid var(--shop-border);border-radius:var(--shop-radius);background:var(--shop-surface)}
.znx-shop-tab{display:inline-flex;align-items:center;justify-content:center;padding:7px 12px;border:1px solid var(--shop-border);border-radius:var(--shop-radius);background:var(--shop-well);color:var(--shop-muted);font-weight:700;cursor:pointer}
.znx-shop-tab:hover{border-color:var(--shop-accent);color:var(--shop-accent)}
.znx-shop-tab.is-active{background:var(--shop-accent);border-color:var(--shop-accent);color:#1a1510}
.znx-shop-panel{display:none}
.znx-shop-panel.is-active{display:block}
.znx-shop-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));align-items:stretch;gap:10px}
.znx-shop-card{display:flex;min-height:230px;height:100%;flex-direction:column;position:relative;overflow:hidden;padding:10px;border:1px solid var(--shop-border);border-radius:var(--shop-radius);background:var(--shop-surface-2);box-shadow:none}
.znx-shop-card:hover{transform:translateY(-1px);border-color:var(--shop-accent)}
.znx-shop-card__media{display:flex;height:82px;align-items:center;justify-content:center;margin:0 0 9px;border:1px solid rgba(0,0,0,.25);border-radius:var(--shop-radius);background:var(--shop-well)}
.znx-shop-card__img{max-width:66px;max-height:66px;object-fit:contain;image-rendering:auto;filter:drop-shadow(0 5px 8px rgba(0,0,0,.35))}
.znx-shop-card__img.is-outfit,.znx-shop-card__img.is-mount{max-width:86px;max-height:76px}
.znx-shop-card__placeholder{display:grid;width:54px;height:42px;place-items:center;border:1px solid var(--shop-accent);border-radius:var(--shop-radius);background:var(--shop-surface);color:var(--shop-accent);font-size:12px;font-weight:900}
.znx-shop-card__body{display:flex;flex:1;flex-direction:column;text-align:center}
.znx-shop-card__title{margin:0 0 6px;font-size:14px;font-weight:800;line-height:1.25;color:var(--shop-text)}
.znx-shop-card__meta{min-height:18px;color:var(--shop-muted);font-size:12px}
.znx-shop-card__price{display:inline-flex;align-items:center;justify-content:center;gap:5px;margin:9px auto 10px;padding:5px 9px;border:1px solid color-mix(in srgb,var(--shop-accent) 35%,transparent);border-radius:var(--shop-radius);background:var(--shop-well);color:var(--shop-accent);font-size:12px;font-weight:900}
.znx-shop-card__price::before{content:"";display:inline-block;width:5px;height:5px;border-radius:50%;background:var(--shop-accent)}
.znx-shop-card form{margin:auto 0 0}
.znx-shop-buy{width:100%;min-height:34px;border:1px solid var(--shop-accent);border-radius:var(--shop-radius);background:var(--shop-accent);color:#1a1510;font-size:12px;font-weight:900;cursor:pointer;box-shadow:none}
.znx-shop-buy:hover{filter:brightness(1.1)}
.znx-shop-login{margin:auto 0 0;padding:8px;border-radius:var(--shop-radius);background:var(--shop-surface);color:var(--shop-muted);font-weight:700;text-align:center}
@media(max-width:680px){.znx-shop-hero{display:block}.znx-shop-balance{margin-top:10px}.znx-shop-tab{flex:1}.znx-shop-grid{grid-template-columns:repeat(auto-fill,minmax(140px,1fr))}}
</style>

<section class="znx-shop-modern shop" id="shop">
	<header class="znx-shop-hero">
		<div>
			<h1><?= $znxShopH(t('shop.offers')) ?></h1>
			<?php if (($loggedin ?? false) === true): ?>
				<p><?= $znxShopH(t('shop.you_have')) ?> <strong><?= (int)$user_znote_data['points'] ?></strong> <?= $znxShopH(t('common.points')) ?>.</p>
				<div class="znx-shop-hero__actions">
					<a href="buypoints.php" class="znx-shop-hero__link"><?= $znxShopH(t('shop.buy_points')) ?></a>
					<?php if (!empty($config['shop_auction']['characterAuction'])): ?>
						<a href="auctionChar.php" class="znx-shop-hero__link"><?= $znxShopH(t_default('shop.auction_button', 'Character Auctions')) ?></a>
					<?php endif; ?>
				</div>
			<?php else: ?>
				<p><?= $znxShopH(t('shop.need_login')) ?></p>
			<?php endif; ?>
		</div>
		<?php if (($loggedin ?? false) === true): ?>
			<div class="znx-shop-balance"><span><?= $znxShopH(t('common.points')) ?></span><strong><?= number_format((int)$user_znote_data['points']) ?></strong></div>
		<?php endif; ?>
	</header>

	<?php if (!$visibleCategories): ?>
		<p><?= $znxShopH(t('shop.offers')) ?>: 0</p>
	<?php else: ?>
		<nav class="znx-shop-tabs" aria-label="<?= $znxShopH(t('shop.offers')) ?>">
			<?php foreach ($visibleCategories as $key => $category): ?>
				<button type="button" class="znx-shop-tab<?= $firstCategory ? ' is-active' : '' ?>" data-shop-tab="<?= $znxShopH($key) ?>"><?= $znxShopH($category['label']) ?></button>
				<?php $firstCategory = false; ?>
			<?php endforeach; ?>
		</nav>

		<?php $firstCategory = true; ?>
		<?php foreach ($visibleCategories as $categoryKey => $category): ?>
			<section class="znx-shop-panel<?= $firstCategory ? ' is-active' : '' ?>" data-shop-panel="<?= $znxShopH($categoryKey) ?>">
				<div class="znx-shop-grid">
					<?php foreach ($category['items'] as $offerId => $offer): ?>
						<?php
						$titlePlain = trim(strip_tags((string)($offer['description'] ?? '')));
						$meta = $offerMeta($offer, $categoryKey);
						?>
						<article class="znx-shop-card shop-offer-card">
							<div class="znx-shop-card__media"><?= $renderImage($offer, $categoryKey) ?></div>
							<div class="znx-shop-card__body">
								<h3 class="znx-shop-card__title"><?= (string)($offer['description'] ?? '') ?></h3>
								<div class="znx-shop-card__meta"><?= $meta !== '' ? $znxShopH($meta) : '&nbsp;' ?></div>
								<div class="znx-shop-card__price shop-offer-price"><?= (int)$offer['points'] ?> <?= $znxShopH(t('common.points')) ?></div>
								<?php if (($loggedin ?? false) === true): ?>
									<form action="" method="POST">
										<input type="hidden" name="buy" value="<?= (int)$offerId ?>">
										<input type="hidden" name="session" value="<?= $znxShopH($shopSession) ?>">
										<button type="submit" class="znx-shop-buy needconfirmation" data-item-name="<?= $znxShopH($titlePlain) ?>" data-item-cost="<?= (int)$offer['points'] ?>"><?= $znxShopH(t('shop.purchase')) ?></button>
									</form>
								<?php else: ?>
									<div class="znx-shop-login"><?= $znxShopH(t('shop.need_login')) ?></div>
								<?php endif; ?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
			<?php $firstCategory = false; ?>
		<?php endforeach; ?>
	<?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('.znx-shop-tab').forEach(function (tab) {
		tab.addEventListener('click', function () {
			var code = tab.getAttribute('data-shop-tab');
			document.querySelectorAll('.znx-shop-tab').forEach(function (t) { t.classList.toggle('is-active', t === tab); });
			document.querySelectorAll('.znx-shop-panel').forEach(function (panel) { panel.classList.toggle('is-active', panel.getAttribute('data-shop-panel') === code); });
		});
	});
	<?php if (!empty($shop['enableShopConfirmation'])): ?>
	document.querySelectorAll('.needconfirmation').forEach(function (button) {
		button.addEventListener('click', function (event) {
			var itemName = this.getAttribute('data-item-name') || 'this offer';
			var itemCost = this.getAttribute('data-item-cost') || '0';
			if (!confirm('Do you really want to purchase ' + itemName + ' for ' + itemCost + ' points?')) {
				event.preventDefault();
			}
		});
	});
	<?php endif; ?>
});
</script>
