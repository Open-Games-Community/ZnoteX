<?php

if ($paypal['enabled']) {
?>

<h1><?= t('buypoints.title') ?></h1>
<h2><?= t('buypoints.paypal') ?></h2>
<table id="buypointsTable" class="table table-striped table-hover">
	<tr class="yellow">
		<th><?= t('buypoints.price') ?></th>
		<th><?= t('buypoints.points') ?></th>
		<?php if ($paypal['showBonus']) { ?>
			<th><?= t('buypoints.bonus_col') ?></th>
		<?php } ?>
		<th><?= t('buypoints.action') ?></th>
	</tr>
		<?php
		foreach ($prices as $price => $points) {
		echo '<tr class="special">';
		echo '<td>'. $price .'('. $paypal['currency'] .')</td>';
		echo '<td>'. $points .'</td>';
		if ($paypal['showBonus']) echo '<td>'. calculate_discount(($paypal['points_per_currency'] * $price), $points) .' '. t('buypoints.bonus') .'</td>';
		?>
		<td>
			<form action="https://www.paypal.com/cgi-bin/webscr" method="POST">
				<input type="hidden" name="cmd" value="_xclick">
				<input type="hidden" name="business" value="<?php echo hhb_tohtml($paypal['email']); ?>">
				<input type="hidden" name="item_name" value="<?= t('buypoints.shop_points_on', ['points' => $points, 'site' => hhb_tohtml($config['site_title'])]) ?>">
				<input type="hidden" name="item_number" value="1">
				<input type="hidden" name="amount" value="<?php echo $price; ?>">
				<input type="hidden" name="no_shipping" value="1">
				<input type="hidden" name="no_note" value="1">
				<input type="hidden" name="currency_code" value="<?php echo hhb_tohtml($paypal['currency']); ?>">
				<input type="hidden" name="lc" value="GB">
				<input type="hidden" name="bn" value="PP-BuyNowBF">
				<input type="hidden" name="return" value="<?php echo hhb_tohtml($paypal['success']); ?>">
				<input type="hidden" name="cancel_return" value="<?php echo hhb_tohtml($paypal['failed']); ?>">
				<input type="hidden" name="rm" value="2">
				<input type="hidden" name="notify_url" value="<?php echo hhb_tohtml($paypal['ipn']); ?>" />
				<input type="hidden" name="custom" value="<?php echo (int)$session_user_id; ?>">
				<input type="submit" value="  <?= t('buypoints.purchase') ?>  ">
			</form>
		</td>
		<?php
		echo '</tr>';
		}
		?>
</table>
<?php } ?>

<?php if (!empty($stripe['enabled'])) { ?>
<h1><?= t('buypoints.title') ?></h1>
<h2>Stripe</h2>
<table class="table table-striped table-hover">
	<tr class="yellow">
		<th><?= t('buypoints.price') ?></th>
		<th><?= t('buypoints.points') ?></th>
		<?php if (!empty($stripe['showBonus'])) { ?><th><?= t('buypoints.bonus_col') ?></th><?php } ?>
		<th><?= t('buypoints.action') ?></th>
	</tr>
	<?php foreach ($prices as $price => $points) { ?>
		<tr class="special">
			<td><?= hhb_tohtml($price) ?>(<?= hhb_tohtml($stripe['currency']) ?>)</td>
			<td><?= (int)$points ?></td>
			<?php if (!empty($stripe['showBonus'])) { ?><td><?= calculate_discount(((int)$stripe['points_per_currency'] * $price), $points) ?> <?= t('buypoints.bonus') ?></td><?php } ?>
			<td>
				<form action="payment.php" method="post">
					<?php Token::create(); ?>
					<input type="hidden" name="provider" value="stripe">
					<input type="hidden" name="price" value="<?= hhb_tohtml($price) ?>">
					<input type="submit" value="  <?= t('buypoints.purchase') ?>  ">
				</form>
			</td>
		</tr>
	<?php } ?>
</table>
<?php } ?>

<?php if (!empty($mercadopago['enabled'])) { ?>
<h1><?= t('buypoints.title') ?></h1>
<h2>Mercado Pago</h2>
<table class="table table-striped table-hover">
	<tr class="yellow">
		<th><?= t('buypoints.price') ?></th>
		<th><?= t('buypoints.points') ?></th>
		<?php if (!empty($mercadopago['showBonus'])) { ?><th><?= t('buypoints.bonus_col') ?></th><?php } ?>
		<th><?= t('buypoints.action') ?></th>
	</tr>
	<?php foreach ($prices as $price => $points) { ?>
		<tr class="special">
			<td><?= hhb_tohtml($price) ?>(<?= hhb_tohtml($mercadopago['currency']) ?>)</td>
			<td><?= (int)$points ?></td>
			<?php if (!empty($mercadopago['showBonus'])) { ?><td><?= calculate_discount(((int)$mercadopago['points_per_currency'] * $price), $points) ?> <?= t('buypoints.bonus') ?></td><?php } ?>
			<td>
				<form action="payment.php" method="post">
					<?php Token::create(); ?>
					<input type="hidden" name="provider" value="mercadopago">
					<input type="hidden" name="price" value="<?= hhb_tohtml($price) ?>">
					<input type="submit" value="  <?= t('buypoints.purchase') ?>  ">
				</form>
			</td>
		</tr>
	<?php } ?>
</table>
<?php } ?>

<?php
if ($config['pagseguro']['enabled'] == true) {
?>
	<h2><?= t('buypoints.pagseguro') ?></h2>
	<form target="pagseguro" action="https://<?=hhb_tohtml($pagseguro['urls']['www'])?>/checkout/checkout.jhtml" method="post">
		<input type="hidden" name="email_cobranca" value="<?=hhb_tohtml($pagseguro['email'])?>">
		<input type="hidden" name="tipo" value="CP">
		<input type="hidden" name="moeda" value="<?=hhb_tohtml($pagseguro['currency'])?>">
		<input type="hidden" name="ref_transacao" value="<?php echo (int)$session_user_id; ?>">
		<input type="hidden" name="item_id_1" value="1">
		<input type="hidden" name="item_descr_1" value="<?=hhb_tohtml($pagseguro['product_name'])?>">
		<input type="number" name="item_quant_1" min="1" step="4" value="1">
		<input type="hidden" name="item_peso_1" value="0">
		<input type="hidden" name="item_valor_1" value="<?=$pagseguro['price']?>">
		<input type="submit" value="  <?= t('buypoints.purchase') ?>  ">
	</form>
	<br>
<?php } ?>

<?php
if ($config['paygol']['enabled'] == true) {
?>
<!-- PayGol Form using Post method -->
<h2><?= t('buypoints.paygol') ?></h2>
<?php $paygol = $config['paygol']; ?>
<p><?= t('buypoints.paygol_line', ['price' => $paygol['price'], 'currency' => hhb_tohtml($paygol['currency']), 'points' => $paygol['points']]) ?></p>
<form name="pg_frm" method="post" action="http://www.paygol.com/micropayment/paynow" >
	<input type="hidden" name="pg_serviceid" value="<?php echo hhb_tohtml($paygol['serviceID']); ?>">
	<input type="hidden" name="pg_currency" value="<?php echo hhb_tohtml($paygol['currency']); ?>">
	<input type="hidden" name="pg_name" value="<?php echo hhb_tohtml($paygol['name']); ?>">
	<input type="hidden" name="pg_custom" value="<?php echo hhb_tohtml($session_user_id); ?>">
	<input type="hidden" name="pg_price" value="<?php echo $paygol['price']; ?>">
	<input type="hidden" name="pg_return_url" value="<?php echo hhb_tohtml($paygol['returnURL']); ?>">
	<input type="hidden" name="pg_cancel_url" value="<?php echo hhb_tohtml($paygol['cancelURL']); ?>">
	<input type="image" name="pg_button" src="https://www.paygol.com/micropayment/img/buttons/150/black_en_pbm.png" border="0" alt="Make payments with PayGol: the easiest way!" title="Make payments with PayGol: the easiest way!">
</form>
<?php }

if (!$config['paypal']['enabled'] && !$config['paygol']['enabled'] && !$config['pagseguro']['enabled'] && empty($stripe['enabled']) && empty($mercadopago['enabled'])) echo '<h1>'. t('buypoints.disabled') .'</h1><p>'. t('buypoints.disabled_text') .'</p>';
