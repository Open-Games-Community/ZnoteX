<?php
$znxPayH = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$providerConfigs = array(
	'paypal' => $paypal ?? array(),
	'stripe' => $stripe ?? array(),
	'mercadopago' => $mercadopago ?? array(),
);
$providerLabels = array(
	'paypal' => t('buypoints.paypal'),
	'stripe' => 'Stripe',
	'mercadopago' => 'Mercado Pago',
);
$enabledProviders = array();
foreach (array('paypal', 'stripe', 'mercadopago') as $provider) {
	if (!empty($providerConfigs[$provider]['enabled'])) $enabledProviders[] = $provider;
}
$hasPagseguro = !empty($config['pagseguro']['enabled']);
$hasPaygol = !empty($config['paygol']['enabled']);
$anyEnabled = $enabledProviders || $hasPagseguro || $hasPaygol;
$firstTable = true;
?>

<style>
.znx-pay-modern{--pay-surface:var(--box-inner-bg,var(--box-bg,var(--nz-panel,var(--wc-bg-2,var(--z-panel-2,var(--s-panel,var(--primary,rgb(30,33,40))))))));--pay-surface-2:color-mix(in srgb,var(--pay-surface) 82%,#000);--pay-well:color-mix(in srgb,var(--pay-surface) 65%,#000);--pay-text:var(--font-color,var(--text,var(--nz-text,var(--wc-text,var(--z-text,var(--s-text,rgb(155,162,177)))))));--pay-muted:color-mix(in srgb,var(--pay-text) 58%,transparent);--pay-border:var(--border,var(--box-inner-border,var(--box-border,var(--nz-border-soft,var(--wc-line,var(--z-border,var(--s-border2,rgb(19,20,23))))))));--pay-accent:var(--accent,var(--link,var(--nz-accent,#d1a233)));--pay-good:#79c36a;--pay-radius:6px;margin:0 0 18px;color:var(--pay-text)}
.znx-pay-modern *{box-sizing:border-box}
.znx-pay-head{margin:0 0 12px;padding:14px 16px;border:1px solid var(--pay-border);border-radius:var(--pay-radius);background:var(--pay-surface);box-shadow:inset 0 1px 0 rgba(255,255,255,.03)}
.znx-pay-head h1{margin:0 0 5px;font-size:26px;line-height:1.05;color:var(--pay-accent)}
.znx-pay-head p{margin:0;color:var(--pay-muted)}
.znx-pay-section{margin:0 0 14px;border:1px solid var(--pay-border);border-radius:var(--pay-radius);background:var(--pay-surface-2);box-shadow:none;overflow:hidden}
.znx-pay-section__head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 14px;background:var(--pay-well);color:var(--pay-accent);border-bottom:1px solid var(--pay-border)}
.znx-pay-section__head h2{margin:0;font-size:16px;color:var(--pay-accent)}
.znx-pay-section__head span{color:var(--pay-muted);font-size:13px}
.znx-pay-table-wrap{overflow-x:auto}
.znx-pay-table{width:100%;border-collapse:separate;border-spacing:0}
.znx-pay-table th{padding:10px 12px;background:var(--pay-surface);color:var(--pay-muted);font-size:12px;letter-spacing:.04em;text-align:left;text-transform:uppercase;white-space:nowrap}
.znx-pay-table td{padding:11px 12px;border-top:1px solid var(--pay-border);vertical-align:middle}
.znx-pay-table tr:hover td{background:color-mix(in srgb,var(--pay-accent) 6%,transparent)}
.znx-pay-price{font-weight:900;color:var(--pay-text)}
.znx-pay-points{display:inline-flex;align-items:center;gap:7px;font-weight:900;color:var(--pay-accent)}
.znx-pay-points::before{content:"";display:inline-block;width:5px;height:5px;border-radius:50%;background:var(--pay-accent)}
.znx-pay-bonus{display:inline-flex;padding:4px 8px;border:1px solid rgba(121,195,106,.35);border-radius:var(--pay-radius);background:rgba(121,195,106,.08);color:var(--pay-good);font-size:12px;font-weight:900}
.znx-pay-action form{margin:0}
.znx-pay-btn{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:8px 14px;border:1px solid var(--pay-accent);border-radius:var(--pay-radius);background:var(--pay-accent);color:#1a1510;font-weight:900;cursor:pointer;box-shadow:none;white-space:nowrap}
.znx-pay-btn:hover{filter:brightness(1.1)}
.znx-pay-provider-form{padding:14px}
.znx-pay-provider-form form{display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin:0}
.znx-pay-provider-form input[type=number]{min-height:34px;padding:7px 10px;border:1px solid var(--pay-border);border-radius:var(--pay-radius);background:var(--pay-well);color:var(--pay-text)}
.znx-pay-empty{padding:18px;border:1px solid var(--pay-border);border-radius:var(--pay-radius);background:var(--pay-surface-2)}
@media(max-width:640px){.znx-pay-section__head{display:block}.znx-pay-section__head span{display:block;margin-top:4px}.znx-pay-table th:nth-child(3),.znx-pay-table td:nth-child(3){display:none}}
</style>

<section class="znx-pay-modern buypoints" id="buypoints">
	<header class="znx-pay-head">
		<h1><?= $znxPayH(t('buypoints.title')) ?></h1>
		<p><?= $znxPayH(t('buypoints.points')) ?> / <?= $znxPayH(t('buypoints.price')) ?></p>
	</header>

	<?php if (!$anyEnabled): ?>
		<div class="znx-pay-empty">
			<h2><?= $znxPayH(t('buypoints.disabled')) ?></h2>
			<p><?= $znxPayH(t('buypoints.disabled_text')) ?></p>
		</div>
	<?php endif; ?>

	<?php foreach ($enabledProviders as $provider): ?>
		<?php
		$cfg = $providerConfigs[$provider];
		$showBonus = !empty($cfg['showBonus']);
		$currency = (string)($cfg['currency'] ?? '');
		$pointsPerCurrency = (float)($cfg['points_per_currency'] ?? 0);
		?>
		<section class="znx-pay-section">
			<div class="znx-pay-section__head">
				<h2><?= $znxPayH($providerLabels[$provider]) ?></h2>
				<span><?= $znxPayH($currency) ?></span>
			</div>
			<div class="znx-pay-table-wrap">
				<table<?= $firstTable ? ' id="buypointsTable"' : '' ?> class="znx-pay-table">
					<thead>
						<tr>
							<th><?= $znxPayH(t('buypoints.price')) ?></th>
							<th><?= $znxPayH(t('buypoints.points')) ?></th>
							<?php if ($showBonus): ?><th><?= $znxPayH(t('buypoints.bonus_col')) ?></th><?php endif; ?>
							<th><?= $znxPayH(t('buypoints.action')) ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ((array)$prices as $price => $points): ?>
							<tr>
								<td><span class="znx-pay-price"><?= $znxPayH($price) ?> <?= $znxPayH($currency) ?></span></td>
								<td><span class="znx-pay-points"><?= number_format((int)$points) ?></span></td>
								<?php if ($showBonus): ?><td><span class="znx-pay-bonus"><?= $znxPayH(calculate_discount($pointsPerCurrency * (float)$price, (int)$points)) ?> <?= $znxPayH(t('buypoints.bonus')) ?></span></td><?php endif; ?>
								<td class="znx-pay-action">
									<?php if ($provider === 'paypal'): ?>
										<form action="https://www.paypal.com/cgi-bin/webscr" method="POST">
											<input type="hidden" name="cmd" value="_xclick">
											<input type="hidden" name="business" value="<?= $znxPayH($paypal['email']) ?>">
											<input type="hidden" name="item_name" value="<?= $znxPayH(t('buypoints.shop_points_on', array('points' => $points, 'site' => $config['site_title']))) ?>">
											<input type="hidden" name="item_number" value="1">
											<input type="hidden" name="amount" value="<?= $znxPayH($price) ?>">
											<input type="hidden" name="no_shipping" value="1">
											<input type="hidden" name="no_note" value="1">
											<input type="hidden" name="currency_code" value="<?= $znxPayH($paypal['currency']) ?>">
											<input type="hidden" name="lc" value="GB">
											<input type="hidden" name="bn" value="PP-BuyNowBF">
											<input type="hidden" name="return" value="<?= $znxPayH($paypal['success']) ?>">
											<input type="hidden" name="cancel_return" value="<?= $znxPayH($paypal['failed']) ?>">
											<input type="hidden" name="rm" value="2">
											<input type="hidden" name="notify_url" value="<?= $znxPayH($paypal['ipn']) ?>">
											<input type="hidden" name="custom" value="<?= (int)$session_user_id ?>">
											<button type="submit" class="znx-pay-btn"><?= $znxPayH(t('buypoints.purchase')) ?></button>
										</form>
									<?php else: ?>
										<form action="payment.php" method="post">
											<?php Token::create(); ?>
											<input type="hidden" name="provider" value="<?= $znxPayH($provider) ?>">
											<input type="hidden" name="price" value="<?= $znxPayH($price) ?>">
											<button type="submit" class="znx-pay-btn"><?= $znxPayH(t('buypoints.purchase')) ?></button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
		<?php $firstTable = false; ?>
	<?php endforeach; ?>

	<?php if ($hasPagseguro): $pagseguroCfg = $config['pagseguro']; ?>
		<section class="znx-pay-section">
			<div class="znx-pay-section__head"><h2><?= $znxPayH(t('buypoints.pagseguro')) ?></h2><span><?= $znxPayH($pagseguroCfg['currency']) ?></span></div>
			<div class="znx-pay-provider-form">
				<form target="pagseguro" action="https://<?= $znxPayH($pagseguroCfg['urls']['www']) ?>/checkout/checkout.jhtml" method="post">
					<input type="hidden" name="email_cobranca" value="<?= $znxPayH($pagseguroCfg['email']) ?>">
					<input type="hidden" name="tipo" value="CP">
					<input type="hidden" name="moeda" value="<?= $znxPayH($pagseguroCfg['currency']) ?>">
					<input type="hidden" name="ref_transacao" value="<?= (int)$session_user_id ?>">
					<input type="hidden" name="item_id_1" value="1">
					<input type="hidden" name="item_descr_1" value="<?= $znxPayH($pagseguroCfg['product_name']) ?>">
					<input type="number" name="item_quant_1" min="1" step="4" value="1">
					<input type="hidden" name="item_peso_1" value="0">
					<input type="hidden" name="item_valor_1" value="<?= $znxPayH($pagseguroCfg['price']) ?>">
					<button type="submit" class="znx-pay-btn"><?= $znxPayH(t('buypoints.purchase')) ?></button>
				</form>
			</div>
		</section>
	<?php endif; ?>

	<?php if ($hasPaygol): $paygolCfg = $config['paygol']; ?>
		<section class="znx-pay-section">
			<div class="znx-pay-section__head"><h2><?= $znxPayH(t('buypoints.paygol')) ?></h2><span><?= $znxPayH($paygolCfg['currency']) ?></span></div>
			<div class="znx-pay-provider-form">
				<p><?= t('buypoints.paygol_line', array('price' => $paygolCfg['price'], 'currency' => $znxPayH($paygolCfg['currency']), 'points' => $paygolCfg['points'])) ?></p>
				<form name="pg_frm" method="post" action="http://www.paygol.com/micropayment/paynow">
					<input type="hidden" name="pg_serviceid" value="<?= $znxPayH($paygolCfg['serviceID']) ?>">
					<input type="hidden" name="pg_currency" value="<?= $znxPayH($paygolCfg['currency']) ?>">
					<input type="hidden" name="pg_name" value="<?= $znxPayH($paygolCfg['name']) ?>">
					<input type="hidden" name="pg_custom" value="<?= $znxPayH($session_user_id) ?>">
					<input type="hidden" name="pg_price" value="<?= $znxPayH($paygolCfg['price']) ?>">
					<input type="hidden" name="pg_return_url" value="<?= $znxPayH($paygolCfg['returnURL']) ?>">
					<input type="hidden" name="pg_cancel_url" value="<?= $znxPayH($paygolCfg['cancelURL']) ?>">
					<input type="image" name="pg_button" src="https://www.paygol.com/micropayment/img/buttons/150/black_en_pbm.png" border="0" alt="PayGol">
				</form>
			</div>
		</section>
	<?php endif; ?>
</section>
