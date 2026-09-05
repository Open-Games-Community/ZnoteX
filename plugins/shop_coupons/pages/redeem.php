<?php
/**
 * Public page: page.php?plugin=shop_coupons&p=redeem
 *
 * A plugin page is a plain fragment. page.php has already run engine/init.php
 * and opened the theme, so everything a normal ZnoteX page has is available -
 * $config, $user_data, the mysql_* helpers - and the theme wraps whatever this
 * file prints. Do not include init.php, and do not print a header or a footer.
 *
 * The theme decides how this looks: it is styled by the theme's own CSS, plus
 * body.page_shop_coupons_redeem for anything specific to this page.
 */

if (!isset($config)) {
	http_response_code(403);
	die('Direct access denied.');
}

$accountId = shop_coupons_account_id();
$result    = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coupon_code'])) {
	// Token::isValid is ZnoteX's CSRF check, the same one the other forms use.
	if (!Token::isValid($_POST['token'] ?? '')) {
		$result = array('ok' => false, 'message' => t('coupons.err_session'));
	} else {
		$result = shop_coupons_redeem((string)$_POST['coupon_code'], $accountId);
	}
}

$discount = shop_coupons_pending_discount($accountId, true);
?>

<h1><?= t('coupons.redeem_title') ?></h1>

<?php if ($accountId === 0): ?>

	<p><?= t('coupons.need_login') ?></p>

<?php else: ?>

	<?php if ($result !== null): ?>
		<p style="padding:10px 13px;border-radius:5px;border:1px solid <?= $result['ok'] ? '#3f7d43' : '#9a3d3d' ?>;background:<?= $result['ok'] ? 'rgba(63,125,67,.12)' : 'rgba(154,61,61,.12)' ?>;">
			<?= htmlspecialchars($result['message'], ENT_QUOTES, 'UTF-8') ?>
		</p>
	<?php endif; ?>

	<?php if ($discount !== null): ?>
		<p><?= t('coupons.pending', ['percent' => (int)$discount['percent']]) ?></p>
	<?php endif; ?>

	<form method="post" action="">
		<?php Token::create(); ?>
		<p>
			<label for="coupon_code"><?= t('coupons.code_label') ?></label><br>
			<input type="text" id="coupon_code" name="coupon_code" maxlength="32"
			       autocomplete="off" spellcheck="false" placeholder="SUMMER2026"
			       style="text-transform:uppercase;">
		</p>
		<p><input type="submit" value="<?= t('coupons.redeem') ?>"></p>
	</form>

	<p><?= t('coupons.rules') ?></p>

<?php endif; ?>
