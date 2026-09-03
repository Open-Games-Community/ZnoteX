<?php
/**
 * Step 5 - the administrator.
 *
 * Creates an account, a character on it, and remembers the account name so the
 * last step can put it in page_admin_access.
 *
 * The account is created the way register.php does it, so the password hash
 * matches what login.php expects on this engine.
 */

if (!defined('ZNOTE_INSTALL')) { http_response_code(403); die('Direct access denied.'); }

$engine  = (string)install_get('ServerEngine', 'TFS_10');
$isOthire = ($engine === 'OTHIRE');
$done    = (bool)install_get('admin_done', false);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$done) {

	$accountName = trim((string)($_POST['account'] ?? ''));
	$password    = (string)($_POST['password'] ?? '');
	$password2   = (string)($_POST['password_again'] ?? '');
	$email       = trim((string)($_POST['email'] ?? ''));
	$character   = trim((string)($_POST['character'] ?? ''));

	$problems = array();

	if ($accountName === '' || strlen($accountName) > 30) { $problems[] = 'The account name is required, up to 30 characters.'; }
	if (strlen($password) < 6)                            { $problems[] = 'The password must be at least 6 characters.'; }
	if ($password !== $password2)                         { $problems[] = 'The passwords do not match.'; }
	if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) { $problems[] = 'A valid e-mail address is required.'; }
	if ($character === '' || strlen($character) > 20)     { $problems[] = 'The character name is required, up to 20 characters.'; }
	if (!preg_match('/^[A-Za-z ]+$/', $character))        { $problems[] = 'The character name may only contain letters and spaces.'; }

	if ($problems) {
		install_error(implode('<br>', array_map('ih', $problems)));
	} else {

		$link = install_connect($connectError);

		if ($link === null) {
			install_error('Lost the database connection: ' . ih((string)$connectError));
		} else {

			$esc = static fn(string $v): string => $link->real_escape_string($v);

			// Refuse rather than silently attach to someone else's account.
			$taken = @$link->query("SELECT `id` FROM `players` WHERE `name` = '" . $esc($character) . "' LIMIT 1");
			if ($taken !== false && $taken->num_rows > 0) {
				install_error('A character named <strong>' . ih($character) . '</strong> already exists.');
			} else {

				$now  = time();
				$hash = sha1($password);

				if ($isOthire) {
					// OTHire identifies accounts by number, not by name.
					$accountId = (int)$accountName;
					@$link->query("INSERT INTO `accounts` (`id`, `password`, `email`) VALUES ({$accountId}, '{$hash}', '" . $esc($email) . "')");
				} else {
					$creation = ($engine === 'TFS_10' || $engine === 'TFS_16' || $engine === 'CANARY')
						? ", `creation`" : '';
					$creationValue = $creation !== '' ? ", {$now}" : '';

					@$link->query("INSERT INTO `accounts` (`name`, `password`, `email`{$creation})
						VALUES ('" . $esc($accountName) . "', '{$hash}', '" . $esc($email) . "'{$creationValue})");
					$accountId = (int)$link->insert_id;
				}

				if ($accountId <= 0) {
					install_error('Could not create the account: ' . ih($link->error));
				} else {

					@$link->query("INSERT INTO `znote_accounts` (`account_id`, `ip`, `created`, `points`, `active`, `active_email`, `activekey`, `flag`)
						VALUES ({$accountId}, 0, {$now}, 0, 1, 1, 0, '')");

					// A level 8 knight with the stock starting stats. The point
					// is to have a character whose name grants panel access;
					// tune it in game or from Admin Panel > Character Skills.
					$ok = @$link->query("INSERT INTO `players`
						(`name`, `group_id`, `account_id`, `level`, `vocation`, `health`, `healthmax`,
						 `experience`, `maglevel`, `mana`, `manamax`, `town_id`, `cap`, `sex`, `looktype`)
						VALUES ('" . $esc($character) . "', 1, {$accountId}, 8, 4, 185, 185, 4200, 0, 90, 90, 1, 470, 1, 128)");

					if (!$ok) {
						install_error('The account was created but the character was not: ' . ih($link->error)
							. '<br>Your server\'s <code>players</code> table may need columns this insert does not set.');
					} else {
						$playerId = (int)$link->insert_id;
						@$link->query("INSERT INTO `znote_players` (`player_id`, `created`, `hide_char`, `comment`)
							VALUES ({$playerId}, {$now}, 0, '')");

						install_state(array(
							'admin_done'      => true,
							'admin_account'   => $accountName,
							'admin_character' => $character,
						));
						install_max_step(6);

						$link->close();
						header('Location: ' . install_url(6));
						exit;
					}
				}
			}

			$link->close();
		}
	}
}
?>
<h1>Administrator</h1>

<?php if ($done): ?>

	<p class="good">
		Account <strong><?= ih(install_get('admin_account')) ?></strong> and character
		<strong><?= ih(install_get('admin_character')) ?></strong> were created. The account
		is given panel access at the next step.
	</p>
	<div class="actions">
		<a class="btn" href="<?= install_url(6) ?>">Continue</a>
	</div>

<?php else: ?>

	<p class="lead">
		An account to log in with, and a character on it. ZnoteX grants admin rights by
		<em>account name</em>, so the account name below is what unlocks the panel.
	</p>

	<form method="post">
		<div class="row">
			<div class="field">
				<label class="lbl" for="account"><?= $isOthire ? 'Account number' : 'Account name' ?></label>
				<input type="text" id="account" name="account" maxlength="30"
					   value="<?= ih(install_get('admin_account')) ?>" required>
				<?php if ($isOthire): ?>
					<p class="hint">OTHire identifies accounts by number.</p>
				<?php endif; ?>
			</div>
			<div class="field">
				<label class="lbl" for="email">E-mail</label>
				<input type="email" id="email" name="email" required>
			</div>
		</div>

		<div class="row">
			<div class="field">
				<label class="lbl" for="password">Password</label>
				<input type="password" id="password" name="password" required>
				<p class="hint">At least 6 characters.</p>
			</div>
			<div class="field">
				<label class="lbl" for="password_again">Password again</label>
				<input type="password" id="password_again" name="password_again" required>
			</div>
		</div>

		<div class="field">
			<label class="lbl" for="character">Character name</label>
			<input type="text" id="character" name="character" maxlength="20" required>
			<p class="hint">Letters and spaces only. Your character in game &mdash; panel access comes from the account name above.</p>
		</div>

		<div class="info">
			The character is created as a level 8 knight with the default starting stats. Change it
			in game, or from <strong>Admin Panel &rarr; Character Skills</strong>, once you are in.
		</div>

		<div class="actions">
			<button class="btn" type="submit">Create the administrator</button>
			<a class="btn ghost" href="<?= install_url(4) ?>">Back</a>
		</div>
	</form>

<?php endif; ?>
