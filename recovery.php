<?php require_once 'engine/init.php';
logged_in_redirect();
theme_open();
if ($config['mailserver']['accountRecovery']) {
	// Fetch, sanitize and assign POST and GET variables.
	$mode = (isset($_GET['mode']) && !empty($_GET['mode'])) ? getValue($_GET['mode'] ?? null) : false;
	$email = (isset($_POST['email']) && !empty($_POST['email'])) ? getValue($_POST['email'] ?? null) : false;
	$character = (isset($_POST['character']) && !empty($_POST['character'])) ? getValue($_POST['character'] ?? null) : false;
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
			if (!$username) {
				// Recover username
				$salt = '';
				if ($config['ServerEngine'] != 'TFS_03') {
					// TFS 0.2 and 1.0
					$password = sha1($password);
				} else {
					// TFS 0.3/4
					if (config('salt') === true) {
						$saltdata = mysql_select_single("SELECT `salt` FROM `accounts` WHERE `email`='$email' LIMIT 1;");
						if ($saltdata !== false) $salt .= $saltdata['salt'];
					}
					$password = sha1($salt.$password);
				}

				if ($config['ServerEngine'] != 'OTHIRE')
					$user = mysql_select_single("SELECT `p`.`id` AS `player_id`, `a`.`name` FROM `players` `p` INNER JOIN `accounts` `a` ON `p`.`account_id` = `a`.`id` WHERE `p`.`name` = '$character' AND `a`.`email` = '$email' AND `a`.`password` = '$password' LIMIT 1;");
				else
					$user = mysql_select_single("SELECT `p`.`id` AS `player_id`, `a`.`id` FROM `players` `p` INNER JOIN `accounts` `a` ON `p`.`account_id` = `a`.`id` WHERE `p`.`name` = '$character' AND `a`.`email` = '$email' AND `a`.`password` = '$password' LIMIT 1;");

				if ($user !== false) {
					// Found user

					$mailer = new Mail($config['mailserver']);
					$title = "$_SERVER[HTTP_HOST]: Your username";
					$body = "<h1>'. t('recovery.title'). '</h1>";
					$body .= "<p>'. t('recovery.your_username2'). ' <b>$user[name]</b><br>";
					$body .= "Enjoy your stay at ".$config['mailserver']['fromName'].". <br>";
					$body .= "<hr>I am an automatic no-reply e-mail. Any emails sent back to me will be ignored.</p>";
					$mailer->sendMail($email, $title, $body, $user['name']);

					?>
					<h1><?= t('recovery.found') ?></h1>
					<p><?= t('recovery.sent_username2') ?> <b><?php echo $email; ?></b>.</p>
					<p>If you can't find the email within 5 minutes, check your junk/trash inbox as it may be mislocated there.</p>
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
				if ($config['ServerEngine'] != 'TFS_03') {
					// TFS 0.2 and 1.0
					$password = sha1($newpass);
				} else {
					// TFS 0.3/4
					if (config('salt') === true) {
						$saltdata = mysql_select_single("SELECT `salt` FROM `accounts` WHERE `email`='$email' LIMIT 1;");
						if ($saltdata !== false) $salt .= $saltdata['salt'];
					}
					$password = sha1($salt.$newpass);
				}

				if ($config['ServerEngine'] != 'OTHIRE')
					$user = mysql_select_single("SELECT `p`.`id` AS `player_id`, `a`.`name`, `a`.`id` AS `account_id` FROM `players` `p` INNER JOIN `accounts` `a` ON `p`.`account_id` = `a`.`id` WHERE `p`.`name` = '$character' AND `a`.`email` = '$email' AND `a`.`name` = '$username' LIMIT 1;");
				else
					$user = mysql_select_single("SELECT `p`.`id` AS `player_id`, `a`.`id` AS `account_id` FROM `players` `p` INNER JOIN `accounts` `a` ON `p`.`account_id` = `a`.`id` WHERE `p`.`name` = '$character' AND `a`.`email` = '$email' AND `a`.`id` = '$username' LIMIT 1;");

				if ($user !== false) {
					// Found user
					// Give him the new password
					mysql_update("UPDATE `accounts` SET `password`='$password' WHERE `id`='".$user['account_id']."' LIMIT 1;");
					// Send him a mail with the new password
					$mailer = new Mail($config['mailserver']);
					$title = "$_SERVER[HTTP_HOST]: Your new password";
					$body = "<h1>'. t('recovery.title'). '</h1>";
					$body .= "<p>'. t('recovery.new_password'). ' <b>$newpass</b><br>";
					$body .= "We recommend you to login and change it before you continue playing. <br>";
					$body .= "Enjoy your stay at ".$config['mailserver']['fromName'].". <br>";
					$body .= "<hr>I am an automatic no-reply e-mail. Any emails sent back to me will be ignored.</p>";
					$mailer->sendMail($email, $title, $body, $user['name']);
					?>
					<h1><?= t('recovery.found') ?></h1>
					<p><?= t('recovery.sent_password') ?> <b><?php echo $email; ?></b>.</p>
					<p>If you can't find the email within 5 minutes, check your junk/trash inbox as it may be mislocated there.</p>
					<?php
				} else {
					// Wrong submitted info
					?>
					<h1><?= t('recovery.failed') ?></h1>
					<p><?= t('recovery.wrong_data') ?></p>
					<?php
				}
			} else { // Token
				$password = sha1($password);
				$user = mysql_select_single("SELECT `a`.`id`, `a`.`name`, `za`.`activekey` FROM `accounts` AS `a` INNER JOIN `znote_accounts` AS `za` ON `a`.`id` = `za`.`account_id` WHERE `a`.`name`='{$username}' AND `a`.`password`='{$password}' AND `a`.`email`='{$email}' LIMIT 1;");
				if ($user !== false) {
					// Found user
					$recoverylink = $config['site_url'] . '/recovery.php?a='.$user['id'].'&k='.$user['activekey'];
					$mailer = new Mail($config['mailserver']);
					$title = $config['site_title'].": '. t('recovery.remove_2fa'). ' link";
					$body = "<h1>'. t('recovery.remove_2fa'). '</h1>";
					$body .= "<p>If you really want to remove Two-Factor Authentication, click on the following link:<br>";
					$body .= "<a href='$recoverylink' target='_BLANK'>$recoverylink</a><br>";
					$body .= "Enjoy your stay at ".$config['mailserver']['fromName'].". <br>";
					$body .= "<hr>I am an automatic no-reply e-mail. Any emails sent back to me will be ignored.</p>";
					$mailer->sendMail($email, $title, $body, $user['name']);
					?>
					<h1><?= t('recovery.confirm_email') ?></h1>
					<p><?= t('recovery.sent_link') ?> <b><?php echo $email; ?></b>.</p>
					<p><?= t('recovery.click_link') ?> <?= t('common.2fa') ?>.</p>
					<p>If you can't find the email within 5 minutes, check your junk/trash inbox as it may be mislocated there.</p>
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

		$a = (isset($_GET['a']) && !empty($_GET['a'])) ? (int)$_GET['a'] : false;
		$k = (isset($_GET['k']) && !empty($_GET['k'])) ? (int)$_GET['k'] : false;

		// '. t('recovery.remove_2fa'). '
		if ($a !== false && $k !== false && !engineIsCanary()) {
			$account = mysql_select_single("SELECT `a`.`id`, `a`.`secret`, `za`.`secret` FROM `accounts` AS `a` INNER JOIN `znote_accounts` AS `za` ON `a`.`id`=`za`.`account_id` WHERE `a`.`id`='$a' AND `za`.`activekey`='$k' LIMIT 1;");
			if ($account !== false) {
				mysql_update("UPDATE `accounts` SET `secret`=NULL WHERE `id`='$a' LIMIT 1;");
				mysql_update("UPDATE `znote_accounts` SET `secret`=NULL WHERE `account_id`='$a' LIMIT 1;");
				?>
				<h1><?= t('recovery.2fa_disabled') ?></h1>
				<p>You may now login with just your username and password.</p>
				<?php
			} else {
				?>
				<h1><?= t('recovery.verify_failed2') ?></h1>
				<p><?= t('recovery.cannot_auth') ?></p>
				<?php
			}
		} else { // Regular view
			?>
			<h1><?= t('recovery.title') ?></h1>
			<!-- HTML code -->
			<?php
			if (in_array($mode, array('username', 'password', 'token'))) {
				?>
				<form action="" method="POST">
					<label for="email">Email:</label><input type="text" name="email" placeholder="name@mail.com"><br>
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
				if ($config['twoFactorAuthenticator']) {
					?>
					<p><?= t('recovery.wish_recover') ?> <a href="?mode=username">username</a>, <a href="?mode=password">password</a> or remove <a href="?mode=token"><?= t('common.2fa') ?></a>?</p>
					<?php
				} else {
					?>
					<p><?= t('recovery.wish_recover') ?> <a href="?mode=username">username</a> or <a href="?mode=password">password</a>?</p>
					<?php
				}
			}
		}
	}
} else {
	?>
	<h1><?= t('recovery.disabled') ?></h1>
	<p><?= t('recovery.disabled_text2') ?></p>
	<?php
}
theme_close(); ?>
