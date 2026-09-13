<?php require_once 'engine/init.php';
protect_page();
theme_open();
#region CANCEL CHARACTER DELETE
$undelete_id = $_GET['cancel_delete_id'] ?? null;
if($undelete_id) {
	$undelete_id = (int)$undelete_id;
	$undelete_q1 = db()->fetchOne("
		SELECT 
			`character_name` 
		FROM `znote_deleted_characters` 
		WHERE `done` = 0 
		AND `id` = ?
		AND `original_account_id` = ?
		AND NOW() < `time`
	", [$undelete_id, (int)$session_user_id]);
	if($undelete_q1) {
		db()->execute('DELETE FROM `znote_deleted_characters` WHERE `id` = ?', [$undelete_id]);
		echo t('acc.delete_cancelled', ['name' => $undelete_q1['character_name']]) .'<br/>';
	}
}
#endregion

// Variable used to check if main page should be rendered after handling POST ('. t('acc.change_comment'). ' page)
$render_page = true;

// Handle GET (verify email)
if (isset($_GET['authenticate']) && $config['mailserver']['myaccount_verify_email']):
	// If we need to process email verification
	if (isset($_GET['u']) && isset($_GET['k'])) {
		// Authenticate user, fetch user id and activation key
		$auid = (isset($_GET['u']) && (int)$_GET['u'] > 0) ? (int)$_GET['u'] : false;
		$akey = (isset($_GET['k']) && (int)$_GET['k'] > 0) ? (int)$_GET['k'] : false;
		if ($auid !== false && $akey !== false) {
			// Find a match
			$user = db()->fetchOne(
				"SELECT `id`, `active`, `active_email` FROM `znote_accounts` WHERE `account_id` = ? AND `activekey` = ? LIMIT 1;",
				[$auid, $akey]
			);
			if ($user !== false) {
				$userId = (int)$user['id'];
				$active = (int)$user['active'];
				$active_email = (int)$user['active_email'];
				$verify_points = ($active_email == 0 && $config['mailserver']['verify_email_points'] > 0)
					? (int)$config['mailserver']['verify_email_points']
					: 0;
				// Enable the account to login
				if ($active == 0 || $active_email == 0) {
					$new_activeKey = rand(100000000, 999999999);
					db()->execute(
						"UPDATE `znote_accounts`
						SET `active` = 1, `active_email` = 1, `activekey` = ?, `points` = `points` + ?
						WHERE `id` = ?
						LIMIT 1;",
						[$new_activeKey, $verify_points, $userId]
					);
				}
				echo '<h1>'. t('common.congrats') .'</h1> <p>'. t('acc.email_verified') .'</p>';
				if ($verify_points > 0) echo "<p>As thanks for having a verified email, you have received <a href='/shop.php'>{$verify_points} shop points</a>!</p>";
				$user_znote_data['active_email'] = 1;
				$user_znote_data['points'] = (int)$user_znote_data['points'] + $verify_points;
			} else {
				echo '<h1>'. t('acc.auth_failed'). '</h1> <p>Either the activation link is wrong, or your account is already activated.</p>';
			}
		} else {
			echo '<h1>'. t('acc.auth_failed') .'</h1> <p>'. t('acc.auth_failed_text') .'</p>';
		}
	} else { // We need to send email verification
		$verify_account_id = (int)$session_user_id;
		$user = db()->fetchOne(
			"SELECT `id`, `activekey`, `active_email` FROM `znote_accounts` WHERE `account_id` = ? LIMIT 1;",
			[$verify_account_id]
		);
		if ($user !== false) {
			$thisurl = config('site_url') . "/myaccount.php";
			$thisurl .= "?authenticate&u=".$verify_account_id."&k=".$user['activekey'];

			$mailer = new Mail($config['mailserver']);

			$title = "Please authenticate your email at {$_SERVER['HTTP_HOST']}.";

			$body = "<h1>Please click on the following link to authenticate your account:</h1>";
			$body .= "<p><a href='{$thisurl}'>{$thisurl}</a></p>";
			$body .= "<p>Thank you for verifying your email and enjoy your stay at {$config['mailserver']['fromName']}.</p>";
			$body .= "<hr><p>I am an automatic no-reply e-mail. Any emails sent back to me will be ignored.</p>";

			$user_name = (znote_server_adapter()->accountIdentityColumn() !== 'id') ? $user_data['name'] : $user_data['id'];
			//echo "<h1>" . $title . "<h1>" . $body;
			$mailer->sendMail($user_data['email'], $title, $body, $user_name);
			?>
			<h1><?= t('acc.email_sent') ?></h1>
			<p>We have sent you an email with a verification link to your email address: <strong><?php echo $user_data['email']; ?></strong></p>
			<p>If you can't find the email within 5 minutes, check your <strong>junk/trash inbox (spam filter)</strong> as it may be misplaced there.</p>
			<?php
		} else {
			echo '<h1>'. t('acc.auth_failed'). '</h1> <p>Failed to verify user when trying to send a verification email.</p>';
		}
	}
endif;

// Handle POST
if (!empty($_POST['selected_character'])) {
	if (!empty($_POST['action'])) {
		// Validate token
		if (!Token::isValid($_POST['token'])) {
			exit();
		}
		// Sanitize values
		$action = getValue($_POST['action'] ?? null);
		$char_name = getValue($_POST['selected_character'] ?? null);

		// Handle actions
		switch($action) {
			// Change character comment PAGE2 (Success).
			case 'update_comment':
				if ((int)user_character_account_id($char_name) === $session_user_id) {
					user_update_comment(user_character_id($char_name), getValue($_POST['comment'] ?? null));
					echo t('acc.comment_updated');
				}
				break;
			// end

			// Hide character
			case 'toggle_hide':
				$hide = (user_character_hide($char_name) == 1 ? 0 : 1);
				if ((int)user_character_account_id($char_name) === $session_user_id) {
					user_character_set_hide(user_character_id($char_name), $hide);
				}
				break;
			// end

			// DELETE character
			case 'delete_character':
				if ((int)user_character_account_id($char_name) === $session_user_id) {
					$charid = user_character_id($char_name);
					if ($charid !== false) {
						if (!user_is_online_10($charid)) {
							if (guild_leader_gid($charid) === false) user_delete_character_soft($charid);
							else echo t('acc.is_guild_leader');
						} else echo t('acc.must_be_offline');
					}
				}
				break;
			// end

			// CHANGE character name
			case 'change_name':
				$oldname = $char_name;
				$newname = isset($_POST['newName']) ? getValue($_POST['newName'] ?? null) : '';

				$player = db()->fetchOne("SELECT `id`, `account_id` FROM `players` WHERE `name` = ? LIMIT 1;", [$oldname]);
				if ($player === false) {
					$errors[] = t('acc.sync_failed');
					echo '<font color="red"><b>';
					echo output_errors($errors);
					echo '</b></font>';
					break;
				}
				$player['online'] = (user_is_online_10($player['id'])) ? 1 : 0;

				// Check if user is online
				if ($player['online'] == 1) {
					$errors[] = t('acc.must_be_offline');
				}

				// Check if player has bough ticket
				$accountId = $player['account_id'];
				$order = db()->fetchOne(
					"SELECT `id`, `account_id` FROM `znote_shop_orders` WHERE `type` = 4 AND `account_id` = ? LIMIT 1;",
					[(int)$accountId]
				);
				if ($order === false) {
					$errors[] = t('acc.no_name_tickets');
				}

				// Check if player and account matches
				if ($order !== false && ($session_user_id != $accountId || $session_user_id != $order['account_id'])) {
					if (empty($errors)) {
						$errors[] = t('acc.sync_failed');
					}
				}

				$newname = validate_name($newname);
				if ($newname === false) {
					$errors[] = t('acc.name_max_words');
				} else {
					if (empty($newname)) {
						$errors[] = t('acc.name_required');
					} else if (user_character_exist($newname) !== false) {
						$errors[] = t('acc.name_taken');
					} else if (!preg_match("/^[a-zA-Z_ ]+$/", $newname)) {
						$errors[] = t('acc.name_letters');
					} else if (strlen($newname) < $config['minL'] || strlen($newname) > $config['maxL']) {
						$errors[] = t('acc.name_length', ['min' => $config['minL'], 'max' => $config['maxL']]);
					} else if (!ctype_upper($newname[0])) {
						$errors[] = t('acc.name_capital');
					}

					// name restriction
					$resname = explode(" ", $_POST['newName']);
					foreach($resname as $res) {
						if(in_array(strtolower($res), $config['invalidNameTags'])) {
							$errors[] = t('reg.restricted_word');
						} else if(strlen($res) == 1) {
							$errors[] = t('reg.words_too_short');
						}
					}
				}

				if (!empty($newname) && empty($errors)) {
					$db = db();
					if (!$db->beginTransaction()) {
						$errors[] = t('acc.sync_failed');
					} else {
						$ok = $db->execute("UPDATE `players` SET `name` = ? WHERE `id` = ? LIMIT 1;", [$newname, (int)$player['id']]);
						$ok = $ok && $db->execute("DELETE FROM `znote_shop_orders` WHERE `id` = ? LIMIT 1;", [(int)$order['id']]);

						if ($ok) {
							$db->commit();
							echo t('acc.name_changed', ['name' => $newname]);
						} else {
							$db->rollback();
							$errors[] = t('acc.sync_failed');
						}
					}

				}

				if (!empty($errors)) {
					echo '<font color="red"><b>';
					echo output_errors($errors);
					echo '</b></font>';
				}

				break;
			// end

			// Change character sex
			case 'change_gender':
				if ((int)user_character_account_id($char_name) === $session_user_id) {
					$char_id = (int)user_character_id($char_name);
					$account_id = user_character_account_id($char_name);

					$chr_data['online'] = user_is_online_10($char_id) ? 1 : 0;
					if ($chr_data['online'] != 1) {
						// Verify that we are not messing around with data
						if ($account_id != $user_data['id']) die("wtf? Something went wrong, try relogging.");

						// Fetch character tickets
						$tickets = shop_account_gender_tickets($account_id);
						$tickets = is_array($tickets) ? $tickets : array();
						if (!empty($tickets) || $config['free_sex_change'] == true) {
							// They are allowed to change gender
							$last = false;
							$infinite = false;
							$tks = 0;
							// Do we have any infinite tickets?
							foreach ($tickets as $ticket) {
								if ($ticket['count'] == 0) $infinite = true;
								else if ((int)$ticket['count'] > 0 && $infinite === false) $tks += (int)$ticket['count'];
							}
							if ($infinite === true) $tks = 0;
							$dbid = isset($tickets[0]['id']) ? (int)$tickets[0]['id'] : 0;
							// If they dont have unlimited tickets, remove a count from their ticket.
							if ($dbid > 0 && $tickets[0]['count'] > 1) { // Decrease count
								$tks--;
								$tkr = ((int)$tickets[0]['count'] - 1);
								shop_update_row_count($dbid, $tkr);
							} else if ($dbid > 0 && $tickets[0]['count'] == 1) { // '. t('common.delete'). ' record
								shop_delete_row_order($dbid);
								$tks--;
							}

							// Change character gender:
							//
							user_character_change_gender($char_name);
							echo t('acc.gender_changed', ['name' => $char_name]);
							if ($tks > 0) echo '<br>You have '. $tks .' gender change tickets left.';
							else if ($infinite !== true) echo '<br>You are out of tickets.';
						} else echo 'You don\'t have any character gender tickets, buy them in the <a href="shop.php">SHOP</a>!';
					} else echo t('acc.must_be_offline');
				}
				break;
			// end

			// Change character comment PAGE1:
			case 'change_comment':
				$render_page = false; // Regular "myaccount" page should not render
				if ((int)user_character_account_id($char_name) === $session_user_id) {
					$comment_data = user_znote_character_data(user_character_id($char_name), 'comment');
					view('myaccount_edit_comment', ['char_name' => $char_name, 'comment_data' => $comment_data]);
				}
				break;
			//end
		}
	}
}

if ($render_page) {
	$char_count = user_character_list_count($session_user_id);
	$pending_delete = user_pending_deletes($session_user_id);
	if ($pending_delete) {
		foreach($pending_delete as $delete) {
			if(new DateTime($delete['time']) > new DateTime())
				echo '<b>CAUTION!</b> Your character with name <b>' . $delete['character_name'] . ' will be deleted on ' . $delete['time'] . '</b>. <a href="myaccount.php?cancel_delete_id=' . $delete['id'] . '">'. t('acc.cancel_op'). '</a><br/>';
			else {
				user_delete_character(user_character_id($delete['character_name']));
				db()->execute('UPDATE `znote_deleted_characters` SET `done` = 1 WHERE `id` = ?', [(int)$delete['id']]);
				echo '<b>'. t('common.character'). ' ' . $delete['character_name'] . ' has been deleted</b>. This operation was requested by owner of this account.';
				$char_count--;
			}
		}
	}

	?>
	<?php
	$char_array = user_character_list($user_data['id']);

	$legacy_twofa_status = null;
	if ($config['twoFactorAuthenticator'] && znote_server_adapter()->supportsLegacyTwoFactor()) {
		$query = db()->fetchOne("SELECT `secret` FROM `accounts` WHERE `id` = ? LIMIT 1;", [(int)$session_user_id]);
		$legacy_twofa_status = (is_array($query) && $query['secret'] !== NULL);
	}
	$twofa2_status = znote2fa_v2_enabled() ? znote2fa_status((int)$session_user_id) : null;
	// Backward-compatible alias for third-party themes written before 2FA v2.
	$myaccount_status = $legacy_twofa_status;

	view('myaccount', [
		'char_array' => $char_array,
		'char_count' => $char_count,
		'myaccount_status' => $myaccount_status,
		'legacy_twofa_status' => $legacy_twofa_status,
		'twofa2_status' => $twofa2_status,
	]);
	?>
	<?php
}
theme_close();
?>
