<?php require_once 'engine/init.php';
znote_csrf_protect_public_post();
logged_in_redirect();
theme_open();
if (function_exists('tco_recovery_open')) {
	tco_recovery_open();
}
if ($config['mailserver']['accountRecovery']) {
	// Fetch, sanitize and assign POST and GET variables.
	$mode = (isset($_GET['mode']) && !empty($_GET['mode'])) ? getValue($_GET['mode'] ?? null) : false;
	$email = (isset($_POST['email']) && !empty($_POST['email'])) ? getValue($_POST['email'] ?? null) : false;
	$character = (isset($_POST['character']) && !empty($_POST['character'])) ? getValue($_POST['character'] ?? null) : false;
	if (!$email && !empty($_POST['email_rcv'])) {
		$email = getValue($_POST['email_rcv'] ?? null);
	}
	if (!$character && !empty($_POST['nick'])) {
		$character = getValue($_POST['nick'] ?? null);
	}
	$password = (isset($_POST['password']) && !empty($_POST['password'])) ? getValue($_POST['password'] ?? null) : false;
	$username = (isset($_POST['username']) && !empty($_POST['username'])) ? getValue($_POST['username'] ?? null) : false;
	//data_dump($_GET, $_POST, "Posted data.");

	if (!empty($_POST)) {
		$status = true;
		if ($config['use_captcha']) {
			if(!verifyGoogleReCaptcha($_POST['g-recaptcha-response'])) {
				$status = false;
			}
		}
		if ($status) {
			if (isset($_POST['action_type'])) {
				$actionType = getValue($_POST['action_type'] ?? '');

				if ($actionType === 'email') {
					$account = false;
					if ($email) {
						$account = db()->fetchOne("
							SELECT `a`.`id`, `a`.`name`, `a`.`email`, `za`.`activekey`
							FROM `accounts` AS `a`
							INNER JOIN `znote_accounts` AS `za` ON `a`.`id` = `za`.`account_id`
							WHERE `a`.`email` = ?
							ORDER BY `a`.`id` ASC
							LIMIT 1;
						", [$email]);
					} elseif ($character) {
						$account = db()->fetchOne("
							SELECT `a`.`id`, `a`.`name`, `a`.`email`, `za`.`activekey`
							FROM `players` AS `p`
							INNER JOIN `accounts` AS `a` ON `p`.`account_id` = `a`.`id`
							INNER JOIN `znote_accounts` AS `za` ON `a`.`id` = `za`.`account_id`
							WHERE `p`.`name` = ?
							LIMIT 1;
						", [$character]);
					}

					if (is_array($account) && !empty($account['email'])) {
						$recoverylink = $config['site_url'] . '/recovery.php?action=lostaccount_reset&a=' . (int)$account['id'] . '&k=' . (int)$account['activekey'];
						$mailer = new Mail($config['mailserver']);
						$title = t('recovery.mail_subject_lostaccount', ['host' => $_SERVER['HTTP_HOST']]);
						$body = '<h1>' . t('recovery.title') . '</h1>';
						$body .= '<p>' . t('recovery.mail_lostaccount_intro') . '</p>';
						$body .= '<p><a href="' . htmlspecialchars($recoverylink, ENT_QUOTES, 'UTF-8') . '" target="_BLANK">' . htmlspecialchars($recoverylink, ENT_QUOTES, 'UTF-8') . '</a></p>';
						$body .= '<p>' . t('recovery.mail_ignore') . '</p>';
						$body .= '<hr><p>' . t('recovery.mail_noreply') . '</p>';
						$mailer->sendMail((string)$account['email'], $title, $body, (string)$account['name']);
					}

					?>
					<h1><?= t('recovery.found') ?></h1>
					<p><?= t('recovery.sent_generic') ?></p>
					<?php
				} else {
					?>
					<h1><?= t('recovery.title') ?></h1>
					<p><?= t('recovery.not_automated') ?></p>
					<?php
				}
			} elseif (!$username) {
				// Recover username
				$salt = '';
				if (znote_server_adapter()->normalizedEngine() === 'TFS_03' && config('salt') === true) {
					$saltdata = db()->fetchOne(
						"SELECT `salt` FROM `accounts` WHERE `email` = ? LIMIT 1;",
						[$email]
					);
					if ($saltdata !== false) $salt .= $saltdata['salt'];
				}

				if (znote_server_adapter()->accountIdentityColumn() !== 'id')
					$candidate = db()->fetchOne(
						"SELECT `p`.`id` AS `player_id`, `a`.`id` AS `account_id`, `a`.`name`, `a`.`password`
						FROM `players` `p`
						INNER JOIN `accounts` `a` ON `p`.`account_id` = `a`.`id`
						WHERE `p`.`name` = ? AND `a`.`email` = ?
						LIMIT 1;",
						[$character, $email]
					);
				else
					$candidate = db()->fetchOne(
						"SELECT `p`.`id` AS `player_id`, `a`.`id` AS `account_id`, `a`.`id` AS `name`, `a`.`password`
						FROM `players` `p`
						INNER JOIN `accounts` `a` ON `p`.`account_id` = `a`.`id`
						WHERE `p`.`name` = ? AND `a`.`email` = ?
						LIMIT 1;",
						[$character, $email]
					);

				$user = false;
				if ($candidate !== false && user_verify_login_password((int)$candidate['account_id'], (string)$password, (string)$candidate['password'], $salt)) {
					$user = $candidate;
				}

				if ($user !== false) {
					// Found user

					$mailer = new Mail($config['mailserver']);
					$title = t('recovery.mail_subject_username', ['host' => $_SERVER['HTTP_HOST']]);
					$body = '<h1>' . t('recovery.title') . '</h1>';
					$body .= '<p>' . t('recovery.your_username2') . ' <b>' . htmlspecialchars((string)$user['name'], ENT_QUOTES, 'UTF-8') . '</b><br>';
					$body .= t('recovery.mail_enjoy_stay', ['site' => $config['mailserver']['fromName']]) . ' <br>';
					$body .= '<hr>' . t('recovery.mail_noreply') . '</p>';
					$mailer->sendMail($email, $title, $body, $user['name']);

					?>
					<h1><?= t('recovery.found') ?></h1>
					<p><?= t('recovery.sent_username2') ?> <b><?php echo $email; ?></b>.</p>
					<p><?= t('recovery.check_junk') ?></p>
					<?php
				} else {
					// Wrong submitted info
					?>
					<h1><?= t('recovery.failed') ?></h1>
					<p><?= t('recovery.wrong_data') ?></p>
					<?php
				}

			} elseif (!$password) {
				// Recover password
				$newpass = rand(100000000, 999999999);
				$salt = '';
				if (znote_server_adapter()->normalizedEngine() !== 'TFS_03') {
					// TFS 0.2 and 1.0
					$password = sha1($newpass);
				} else {
					// TFS 0.3/4
					if (config('salt') === true) {
						$saltdata = db()->fetchOne(
							"SELECT `salt` FROM `accounts` WHERE `email` = ? LIMIT 1;",
							[$email]
						);
						if ($saltdata !== false) $salt .= $saltdata['salt'];
					}
					$password = sha1($salt.$newpass);
				}

				if (znote_server_adapter()->accountIdentityColumn() !== 'id')
					$user = db()->fetchOne(
						"SELECT `p`.`id` AS `player_id`, `a`.`name`, `a`.`id` AS `account_id`
						FROM `players` `p`
						INNER JOIN `accounts` `a` ON `p`.`account_id` = `a`.`id`
						WHERE `p`.`name` = ? AND `a`.`email` = ? AND `a`.`name` = ?
						LIMIT 1;",
						[$character, $email, $username]
					);
				else
					$user = db()->fetchOne(
						"SELECT `p`.`id` AS `player_id`, `a`.`id` AS `account_id`, `a`.`id` AS `name`
						FROM `players` `p`
						INNER JOIN `accounts` `a` ON `p`.`account_id` = `a`.`id`
						WHERE `p`.`name` = ? AND `a`.`email` = ? AND `a`.`id` = ?
						LIMIT 1;",
						[$character, $email, $username]
					);

				if ($user !== false) {
					// Found user
					// Give him the new password
					db()->execute(
						"UPDATE `accounts` SET `password` = ? WHERE `id` = ? LIMIT 1;",
						[$password, (int)$user['account_id']]
					);
					user_set_website_password_hash((int)$user['account_id'], (string)$newpass);
					// Send him a mail with the new password
					$mailer = new Mail($config['mailserver']);
					$title = t('recovery.mail_subject_password', ['host' => $_SERVER['HTTP_HOST']]);
					$body = '<h1>' . t('recovery.title') . '</h1>';
					$body .= '<p>' . t('recovery.new_password') . ' <b>' . htmlspecialchars((string)$newpass, ENT_QUOTES, 'UTF-8') . '</b><br>';
					$body .= t('recovery.recommend_change_password') . ' <br>';
					$body .= t('recovery.mail_enjoy_stay', ['site' => $config['mailserver']['fromName']]) . ' <br>';
					$body .= '<hr>' . t('recovery.mail_noreply') . '</p>';
					$mailer->sendMail($email, $title, $body, $user['name']);
					?>
					<h1><?= t('recovery.found') ?></h1>
					<p><?= t('recovery.sent_password') ?> <b><?php echo $email; ?></b>.</p>
					<p><?= t('recovery.check_junk') ?></p>
					<?php
				} else {
					// Wrong submitted info
					?>
					<h1><?= t('recovery.failed') ?></h1>
					<p><?= t('recovery.wrong_data') ?></p>
					<?php
				}
			} else { // Token
				$candidate = db()->fetchOne(
					"SELECT `a`.`id`, `a`.`name`, `a`.`password`, `za`.`activekey`
					FROM `accounts` AS `a`
					INNER JOIN `znote_accounts` AS `za` ON `a`.`id` = `za`.`account_id`
					WHERE `a`.`name` = ? AND `a`.`email` = ?
					LIMIT 1;",
					[$username, $email]
				);
				$user = false;
				if ($candidate !== false && user_verify_login_password((int)$candidate['id'], (string)$password, (string)$candidate['password'])) {
					$user = $candidate;
				}
				if ($user !== false) {
					// Found user
					$recoverylink = $config['site_url'] . '/recovery.php?a='.$user['id'].'&k='.$user['activekey'];
					$mailer = new Mail($config['mailserver']);
					$title = $config['site_title'] . ': ' . t('recovery.remove_2fa') . ' link';
					$body = '<h1>' . t('recovery.remove_2fa') . '</h1>';
					$body .= '<p>' . t('recovery.remove_2fa_confirm_link') . '<br>';
					$body .= '<a href="' . htmlspecialchars($recoverylink, ENT_QUOTES, 'UTF-8') . '" target="_BLANK">' . htmlspecialchars($recoverylink, ENT_QUOTES, 'UTF-8') . '</a><br>';
					$body .= t('recovery.mail_enjoy_stay', ['site' => $config['mailserver']['fromName']]) . ' <br>';
					$body .= '<hr>' . t('recovery.mail_noreply') . '</p>';
					$mailer->sendMail($email, $title, $body, $user['name']);
					?>
					<h1><?= t('recovery.confirm_email') ?></h1>
					<p><?= t('recovery.sent_link') ?> <b><?php echo $email; ?></b>.</p>
					<p><?= t('recovery.click_link') ?> <?= t('common.2fa') ?>.</p>
					<p><?= t('recovery.check_junk') ?></p>
					<?php
				} else {
					// Wrong submitted info
					?>
					<h1><?= t('recovery.failed') ?></h1>
					<p><?= t('recovery.wrong_data') ?></p>
					<?php
				}


			}
		} else echo t('recovery.captcha_wrong');
	} else {

		$recoveryAction = (isset($_GET['action']) && !empty($_GET['action'])) ? getValue($_GET['action'] ?? null) : false;
		$a = (isset($_GET['a']) && !empty($_GET['a'])) ? (int)$_GET['a'] : false;
		$k = (isset($_GET['k']) && !empty($_GET['k'])) ? (int)$_GET['k'] : false;

		// '. t('recovery.remove_2fa'). '
		if ($a !== false && $k !== false && $recoveryAction === 'lostaccount_reset') {
			$account = db()->fetchOne(
				"SELECT `a`.`id`, `a`.`name`, `a`.`email`
				FROM `accounts` AS `a`
				INNER JOIN `znote_accounts` AS `za` ON `a`.`id` = `za`.`account_id`
				WHERE `a`.`id` = ? AND `za`.`activekey` = ?
				LIMIT 1;",
				[$a, $k]
			);
			if ($account !== false && !empty($account['email'])) {
				$newpass = substr(sha1(random_bytes(32)), 0, 12);
				if (znote_server_adapter()->normalizedEngine() === 'TFS_03' && config('salt') === true) {
					user_change_password03((int)$account['id'], $newpass);
				} else {
					user_change_password((int)$account['id'], $newpass);
				}
				db()->execute("UPDATE `znote_accounts` SET `activekey` = ? WHERE `account_id` = ? LIMIT 1;", [rand(100000000, 999999999), (int)$account['id']]);

				$mailer = new Mail($config['mailserver']);
				$title = t('recovery.mail_subject_recovered', ['host' => $_SERVER['HTTP_HOST']]);
				$body = '<h1>' . t('recovery.title') . '</h1>';
				$body .= '<p>' . t('recovery.your_username2') . ' <b>' . htmlspecialchars((string)$account['name'], ENT_QUOTES, 'UTF-8') . '</b><br>';
				$body .= t('recovery.new_password') . ' <b>' . htmlspecialchars($newpass, ENT_QUOTES, 'UTF-8') . '</b></p>';
				$body .= '<p>' . t('recovery.recommend_change_password_now') . '</p>';
				$body .= '<hr><p>' . t('recovery.mail_noreply') . '</p>';
				$mailer->sendMail((string)$account['email'], $title, $body, (string)$account['name']);
				?>
				<h1><?= t('recovery.found') ?></h1>
				<p><?= t('recovery.sent_generic') ?></p>
				<?php
			} else {
				?>
				<h1><?= t('recovery.verify_failed2') ?></h1>
				<p><?= t('recovery.cannot_auth') ?></p>
				<?php
			}
		} elseif ($a !== false && $k !== false && !engineIsCanary()) {
			$account = db()->fetchOne(
				"SELECT `a`.`id`, `a`.`secret`, `za`.`secret`
				FROM `accounts` AS `a`
				INNER JOIN `znote_accounts` AS `za` ON `a`.`id` = `za`.`account_id`
				WHERE `a`.`id` = ? AND `za`.`activekey` = ?
				LIMIT 1;",
				[$a, $k]
			);
			if ($account !== false) {
				db()->execute("UPDATE `accounts` SET `secret` = NULL WHERE `id` = ? LIMIT 1;", [$a]);
				db()->execute("UPDATE `znote_accounts` SET `secret` = NULL WHERE `account_id` = ? LIMIT 1;", [$a]);
				?>
				<h1><?= t('recovery.2fa_disabled') ?></h1>
				<p><?= t('recovery.2fa_disabled_text') ?></p>
				<?php
			} else {
				?>
				<h1><?= t('recovery.verify_failed2') ?></h1>
				<p><?= t('recovery.cannot_auth') ?></p>
				<?php
			}
		} else { // Regular view
			?>
			<h2><?= t('recovery.welcome_title') ?></h2>

			<p><?= t('recovery.intro_text') ?></p>

			<p><?= t('recovery.can_intro') ?></p>

			<ul class="CustomBulletPointList">
				<li><?= t('recovery.can_new_password') ?></li>
				<li><?= t('recovery.can_hacked') ?></li>
				<li><?= t('recovery.can_change_email') ?></li>
				<li><?= t('recovery.can_new_key') ?></li>
				<li><?= t('recovery.can_remove_auth') ?></li>
				<li><?= t('recovery.can_disable_email_auth') ?></li>
			</ul>

			<p><?= t('recovery.first_step') ?></p>

			<?php
			if (in_array($mode, array('username', 'password', 'token'))) {
				?>
				<form action="" method="POST">
					<label for="email"><?= t('recovery.email_label') ?></label><input type="text" name="email" placeholder="name@mail.com"><br>
					<label for="<?= t('common.character') ?>"><?= t('common.label_character') ?> </label><input type="text" name="character"><br>
					<?php

					if ($mode === 'password') {
						echo '<label for="username">'. t('common.label_username2'). '</label> <input type="text" name="username"><br>';
					} elseif ($mode === 'username') {
						echo '<label for="password">'. t('common.label_password') .'</label> <input type="password" name="password"><br>';
					} elseif ($mode === 'token') {
						echo '<label for="username">'. t('common.label_username2') .'</label> <input type="text" name="username"><br>';
						echo '<label for="password">'. t('common.label_password') .'</label> <input type="password" name="password"><br>';
					}

					if ($config['use_captcha']) {
						?>
							<div class="g-recaptcha" data-sitekey="<?php echo $config['captcha_site_key']; ?>"></div>
						<?php
					}
					?>
					<input type="submit" value="<?= t('recovery.submit') ?>">
				</form>
				<?php
			} else {
				?>
				<?php if (function_exists('tco_panel_open')) { tco_panel_open(t('recovery.panel_title')); } ?>
				<form action="" method="post" class="lostaccount-form">
					<input type="hidden" name="character" value="">
					<div class="lostaccount-field-title"><?= t('recovery.field_character') ?></div>
					<input type="text" name="nick" size="40" autofocus>
					<div class="lostaccount-field-title"><?= t('recovery.field_email') ?></div>
					<input type="text" name="email_rcv" size="40">
					<div class="lostaccount-field-title"><?= t('recovery.field_action') ?></div>
					<label class="lostaccount-option"><input type="radio" name="action_type" value="email" checked> <?= t('recovery.option_email') ?></label>
					<label class="lostaccount-option"><input type="radio" name="action_type" value="reckey"> <?= t('recovery.option_reckey') ?></label>
					<label class="lostaccount-option"><input type="radio" name="action_type" value="no_char"> <?= t('recovery.option_no_char') ?></label>
					<?php if ($config['use_captcha']) { ?>
						<div class="g-recaptcha" data-sitekey="<?php echo $config['captcha_site_key']; ?>"></div>
					<?php } ?>
					<input type="submit" value="<?= t('recovery.submit') ?>">
				</form>
				<?php if (function_exists('tco_panel_close')) { tco_panel_close(); } ?>
				<?php
			}
		}
	}
} else {
	?>
	<h1><?= t('recovery.disabled') ?></h1>
	<p><?= t('recovery.disabled_text2') ?></p>
	<?php
}
if (function_exists('tco_recovery_close')) {
	tco_recovery_close();
}
theme_close(); ?>
