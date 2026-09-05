<?php
/**
 * Shop Coupons - the reference ZnoteX plugin.
 *
 * Loaded by engine/init.php on every request, but only while the plugin is
 * enabled. Its job is to register hooks and declare shared helpers; it must not
 * print anything, and it must be cheap - a plugin that queries the database
 * here pays for it on every page of the site.
 *
 * What this plugin adds:
 *   pages/redeem.php   public page at page.php?plugin=shop_coupons&p=redeem
 *   admin/coupons.php  admin module, listed under Plugins
 *   install.sql        its three tables
 *   the hooks below    a price discount, a purchase reaction, a footer banner
 *
 * Two kinds of code:
 *   points   credits N shop points immediately
 *   percent  parks a discount that the next purchase takes off the price
 */

// ---------------------------------------------------------------------------
// Hooks
// ---------------------------------------------------------------------------

/**
 * Take a redeemed percent discount off the price of the offer.
 *
 * 'shop.price' is a filter: it hands each callback the current price and uses
 * whatever comes back. ZnoteX applies the result to all three places that
 * matter - the "can you afford it" check, the points actually deducted, and
 * the shop log - so a discount cannot end up applied to one and not another.
 */
znote_hook_register('shop.price', static function ($price, array $data) {
	$discount = shop_coupons_pending_discount((int)($data['account_id'] ?? 0));
	if ($discount === null) {
		return $price;
	}

	$off = (int)floor((int)$price * ((int)$discount['percent'] / 100));

	return max(0, (int)$price - $off);
});

/**
 * Consume the discount, now that a purchase has actually gone through.
 *
 * 'shop.purchased' fires after the points have been taken. A plugin cannot
 * cancel a purchase from here - which is the point: by the time it runs the
 * points are spent, so it is safe to mark the discount as used.
 */
znote_hook_register('shop.purchased', static function (array $data): void {
	$discount = shop_coupons_pending_discount((int)($data['account_id'] ?? 0));
	if ($discount === null) {
		return;
	}

	mysql_update("
		UPDATE `znote_coupon_discounts`
		SET `spent_at` = " . time() . "
		WHERE `id` = " . (int)$discount['id'] . " AND `spent_at` = 0
		LIMIT 1;
	");
});

/**
 * A small banner in the footer, for logged-in visitors.
 *
 * 'page.footer' is collected, not fired: whatever the callbacks return is
 * inserted before </body>. ZnoteX rewrites the theme's output to place it, so
 * this works with themes written long before the plugin existed - including
 * ones that never call a plugin function.
 */
znote_hook_register('page.footer', static function (): string {
	if (shop_coupons_account_id() === 0) {
		return '';
	}

	$url = htmlspecialchars(znote_plugin_url('shop_coupons', 'redeem'), ENT_QUOTES, 'UTF-8');

	return '<div style="position:fixed;left:14px;bottom:14px;z-index:900;padding:8px 13px;'
		. 'border-radius:6px;background:rgba(20,24,31,.92);color:#dfe4ec;'
		. 'font:13px system-ui,Arial,sans-serif;box-shadow:0 4px 18px rgba(0,0,0,.35);">'
		. t('coupons.banner') . ' <a href="' . $url . '" style="color:#d1a233;">' . t('coupons.banner_link') . '</a>'
		. '</div>';
});

// ---------------------------------------------------------------------------
// Shared helpers
//
// Both the public page and the admin module use these, so they live here
// rather than being written twice.
// ---------------------------------------------------------------------------

/** The logged-in account, or 0. Same id the shop charges. */
function shop_coupons_account_id(): int {
	return (function_exists('user_logged_in') && user_logged_in() === true)
		? (int)getSession('user_id')
		: 0;
}

/** What is stored and compared: uppercase, letters, digits, - and _ only. */
function shop_coupons_normalise(string $code): string {
	return strtoupper((string)preg_replace('/[^A-Za-z0-9_-]/', '', $code));
}

/** The account's usable discount, or null. Cached for the request. */
function shop_coupons_pending_discount(int $accountId, bool $refresh = false): ?array {
	static $cache = array();

	if ($accountId <= 0) {
		return null;
	}
	if (!$refresh && array_key_exists($accountId, $cache)) {
		return $cache[$accountId];
	}

	$now = time();

	$row = mysql_select_single("
		SELECT `id`, `percent`, `expires_at`
		FROM `znote_coupon_discounts`
		WHERE `account_id` = {$accountId}
		  AND `spent_at` = 0
		  AND (`expires_at` = 0 OR `expires_at` > {$now})
		ORDER BY `percent` DESC, `id` ASC
		LIMIT 1;
	");

	return $cache[$accountId] = (is_array($row) && $row) ? $row : null;
}

/**
 * Redeem a code for an account.
 *
 * Returns [ok => bool, message => string].
 */
function shop_coupons_redeem(string $code, int $accountId): array {
	$fail = static fn(string $m): array => array('ok' => false, 'message' => $m);

	$code = shop_coupons_normalise($code);
	if ($code === '') {
		return $fail(t('coupons.err_empty'));
	}
	if ($accountId <= 0) {
		return $fail(t('coupons.err_login'));
	}

	$coupon = mysql_select_single("
		SELECT `id`, `kind`, `value`, `uses_max`, `uses_done`, `expires_at`
		FROM `znote_coupons`
		WHERE `code` = '" . mysql_znote_escape_string($code) . "'
		LIMIT 1;
	");

	if (!is_array($coupon) || !$coupon) {
		return $fail(t('coupons.err_unknown'));
	}

	$id      = (int)$coupon['id'];
	$value   = (int)$coupon['value'];
	$kind    = (string)$coupon['kind'];
	$expires = (int)$coupon['expires_at'];
	$max     = (int)$coupon['uses_max'];

	if ($expires > 0 && $expires < time()) {
		return $fail(t('coupons.err_expired'));
	}
	if ($max > 0 && (int)$coupon['uses_done'] >= $max) {
		return $fail(t('coupons.err_used_up'));
	}

	// The real guard against a double redemption: the unique key on
	// (coupon_id, account_id) refuses the second row. Two requests arriving at
	// the same moment would both pass the checks above; only one wins here.
	$claimed = mysql_insert("
		INSERT INTO `znote_coupon_uses` (`coupon_id`, `account_id`, `used_at`)
		VALUES ({$id}, {$accountId}, " . time() . ");
	");

	if ($claimed === false) {
		return $fail(t('coupons.err_already'));
	}

	mysql_update("UPDATE `znote_coupons` SET `uses_done` = `uses_done` + 1 WHERE `id` = {$id} LIMIT 1;");

	if ($kind === 'percent') {
		mysql_insert("
			INSERT INTO `znote_coupon_discounts` (`account_id`, `percent`, `expires_at`, `spent_at`)
			VALUES ({$accountId}, {$value}, {$expires}, 0);
		");

		shop_coupons_pending_discount($accountId, true);

		$message = t('coupons.ok_percent', ['percent' => $value]);
	} else {
		$balance = mysql_select_single("SELECT `points` FROM `znote_accounts` WHERE `account_id` = {$accountId} LIMIT 1;");

		if (!is_array($balance) || !$balance) {
			// Undo the claim rather than record a use for points never given.
			mysql_delete("DELETE FROM `znote_coupon_uses` WHERE `coupon_id` = {$id} AND `account_id` = {$accountId};");
			mysql_update("UPDATE `znote_coupons` SET `uses_done` = `uses_done` - 1 WHERE `id` = {$id} LIMIT 1;");

			return $fail(t('coupons.err_nobalance'));
		}

		$new = (int)$balance['points'] + $value;
		mysql_update("UPDATE `znote_accounts` SET `points` = {$new} WHERE `account_id` = {$accountId};");

		$message = t('coupons.ok_points', ['points' => $value, 'total' => $new]);
	}

	// Plugins can extend plugins: this one publishes a hook of its own.
	znote_hook('coupon.redeemed', array(
		'code'       => $code,
		'kind'       => $kind,
		'value'      => $value,
		'account_id' => $accountId,
	));

	return array('ok' => true, 'message' => $message);
}
