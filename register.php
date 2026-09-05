<?php
require_once 'engine/init.php';
logged_in_redirect();
theme_open();
require_once('config.countries.php');

if (empty($_POST) === false) {
	// $_POST['']
	$required_fields = array('username', 'password', 'password_again', 'email', 'selected');
	foreach($_POST as $key=>$value) {
		if (empty($value) && in_array($key, $required_fields) === true) {
			$errors[] = t('reg.fill_all');
			break 1;
		}
	}

	// check errors (= user exist, pass long enough
	if (empty($errors) === true) {
		/* Token used for cross site scripting security */
		if (!Token::isValid($_POST['token'] ?? null)) {
			$errors[] = t('login.token_invalid');
		}

		if ($config['use_captcha']) {
			if(!verifyGoogleReCaptcha($_POST['g-recaptcha-response'])) {
				$errors[] = t('reg.captcha');
			}
		}

		if (user_exist($_POST['username']) === true) {
			$errors[] = t('reg.name_taken');
		}

		// Don't allow "default admin names in config.php" access to register.
		$isNoob = in_array(strtolower($_POST['username']), $config['page_admin_access']) ? true : false;
		if ($isNoob) {
			$errors[] = t('reg.name_blocked');
		}
		if ($config['client'] >= 830) {
			if (preg_match("/^[a-zA-Z0-9]+$/", $_POST['username']) == false) {
				$errors[] = t('reg.name_chars');
			}
		} else {
			if (preg_match("/^[0-9]+$/", $_POST['username']) == false) {
				$errors[] = t('reg.name_digits');
			}
			if ((int)$_POST['username'] < 100000 || (int)$_POST['username'] > 999999999) {
				$errors[] = t('reg.name_length_num');
			}
		}
		// name restriction
		$resname = explode(" ", $_POST['username']);
		foreach($resname as $res) {
			if(in_array(strtolower($res), $config['invalidNameTags'])) {
				$errors[] = t('reg.restricted_word');
			}
			else if(strlen($res) == 1) {
				$errors[] = t('reg.words_too_short');
			}
		}
		if (strlen($_POST['username']) > 32) {
			$errors[] = t('reg.name_too_long');
		}
		// end name restriction
		if (strlen($_POST['password']) < 6) {
			$errors[] = t('reg.pw_too_short');
		}
		if (strlen($_POST['password']) > 29) {
			$errors[] = t('reg.pw_too_long');
		}
		if ($_POST['password'] !== $_POST['password_again']) {
			$errors[] = t('reg.pw_mismatch');
		}
		if (filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) === false) {
			$errors[] = t('reg.email_invalid');
		}
		if (user_email_exist($_POST['email']) === true) {
			$errors[] = t('reg.email_taken');
		}
		if ($_POST['selected'] != 1) {
			$errors[] = t('reg.accept_rules');
		}
		if ($config['validate_IP'] === true) {
			if (validate_ip(getIP()) === false) {
				$errors[] = t('reg.bad_ip');
			}
		}
		if (strlen($_POST['flag']) < 1) {
			$errors[] = t('reg.choose_country');
		}
	}
}

?>
<h1><?= t('reg.heading') ?></h1>
<?php
if (isset($_GET['success']) && empty($_GET['success'])) {
	if ($config['mailserver']['register']) {
		?>
		<h1><?= t('reg.email_required') ?></h1>
		<p>We have sent you an email with an activation link to your submitted email address.</p>
		<p>If you can't find the email within 5 minutes, check your <strong>junk/trash inbox (spam filter)</strong> as it may be mislocated there.</p>
		<?php
	} else echo t('reg.created');
} elseif (isset($_GET['authenticate']) && empty($_GET['authenticate'])) {
	// Authenticate user, fetch user id and activation key
	$auid = (isset($_GET['u']) && (int)$_GET['u'] > 0) ? (int)$_GET['u'] : false;
	$akey = (isset($_GET['k']) && (int)$_GET['k'] > 0) ? (int)$_GET['k'] : false;
	// Find a match
	$user = mysql_select_single("SELECT `id`, `active`, `active_email` FROM `znote_accounts` WHERE `account_id`='$auid' AND `activekey`='$akey' LIMIT 1;");
	if ($user !== false) {
		$user = (int) $user['id'];
		$active = (int) $user['active'];
		$active_email = (int) $user['active_email'];
		// Enable the account to login
		if ($active == 0 || $active_email == 0) {
			mysql_update("UPDATE `znote_accounts` SET `active`='1', `active_email`='1' WHERE `id`= $user LIMIT 1;");
		}
		echo '<h1>'. t('common.congrats') .'</h1> <p>'. t('reg.created') .'</p>';
	} else {
		echo '<h1>'. t('acc.auth_failed') .'</h1> <p>'. t('acc.auth_failed_text') .'</p>';
	}
} else {
	if (empty($_POST) === false && empty($errors) === true) {
		if ($config['log_ip']) {
			znote_visitor_insert_detailed_data(1);
		}

		//Register
		$register_data = array(
			'name'		=>	$_POST['username'],
			'password'	=>	$_POST['password'],
			'email'		=>	$_POST['email'],
			'created'	=>	time(),
			'ip'		=>	getIPLong(),
			'flag'		=> 	$_POST['flag']
		);

		$accountId = user_create_account($register_data, $config['mailserver']);

		$createPremiumDays = max(0, min(999, (int)($config['account_create_premdays'] ?? 0)));
		if ($accountId > 0 && $createPremiumDays > 0) {
			user_account_add_premdays($accountId, $createPremiumDays);
		}

		// Plugins can react to a new account: a welcome bonus, a webhook, an
		// entry in a referral ledger.
		znote_hook('account.registered', array(
			'name'  => $register_data['name'] ?? '',
			'email' => $register_data['email'] ?? '',
		));
		if (!$config['mailserver']['debug']) header('Location: register.php?success');
		exit();
		//End register

	} else if (empty($errors) === false){
		echo '<font color="red"><b>';
		echo output_errors($errors);
		echo '</b></font>';
	}
?>
	<form action="" method="post">
		<ul>
			<li><?= t('reg.label_name') ?><br>
				<input type="text" name="username">
			</li>

			<li><?= t('reg.label_pw') ?><br>
				<input type="password" name="password">
			</li>
			
			<li><?= t('reg.label_pw2') ?><br>
				<input type="password" name="password_again">
			</li>
			
			<li>Email:<br>
				<input type="text" name="email">
			</li>
			
			<li><?= t('reg.label_country') ?><br>
				<select name="flag">
					<option value="">(Please choose)</option>
					<?php
					foreach(array('pl', 'se', 'br', 'us', 'gb', ) as $c)
						echo '<option value="' . $c . '">' . $config['countries'][$c] . '</option>';

						echo '<option value="">----------</option>';
						foreach($config['countries'] as $code => $c)
							echo '<option value="' . $code . '">' . $c . '</option>';
					?>
				</select>
			</li>
			
			<?php
			if ($config['use_captcha']) {
				?>
				<li>
					 <div class="g-recaptcha" data-sitekey="<?php echo $config['captcha_site_key']; ?>"></div>
				</li>
				<?php
			}
			?>

			<li><h2><?= t('reg.rules_title') ?></h2>
				<p><?= t('reg.rule_golden') ?></p>
				<p><?= t('reg.rule_pwned') ?></p>
				<p>No <a href='https://en.wikipedia.org/wiki/Cheating_in_video_games' target="_blank">cheating</a> allowed.</p>
				<p>No <a href='https://en.wikipedia.org/wiki/Video_game_bot' target="_blank">botting</a> allowed.</p>
				<p>The staff can delete, ban, do whatever they want with your account and your <br>
					submitted information. (Including exposing and logging your IP).</p>
			</li>

			<li><?= t('reg.rules_agree') ?><br>
				<select name="selected">
				  <option value="0">Umh...</option>
				  <option value="1">Yes.</option>
				  <option value="2">No.</option>
				</select>
			</li>
			<?php
				/* Form file */
				Token::create();
			?>
			<li>
				<input type="submit" value="<?= t('reg.submit') ?>">
			</li>
		</ul>
	</form>
<?php
}
theme_close();
?>
