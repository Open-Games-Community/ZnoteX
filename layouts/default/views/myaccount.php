<div id="myaccount">
	<h1><?= t('acc.page_title') ?></h1>
	<p><?= t('acc.welcome') ?> <?php echo $user_data['name']; ?><br>
		<?php
		if ($user_data['premdays'] != 0) echo t('acc.premium_days', ['days' => $user_data['premdays']]);
		else echo t('acc.free_account');

		if ($config['mailserver']['myaccount_verify_email']):
			?><br>Email: <?php echo $user_data['email'];
			if ($user_znote_data['active_email'] == 1) {
				?> (Verified).<?php
			} else {
				?><br><strong><?= t('acc.email_not_verified') ?> <a href="?authenticate"><?= t('acc.please_verify') ?></a>.</strong><?php
			}
		endif; ?>
	</p>
	<?php if ($twofa2_status !== null): ?>
		<p><?= t_default('twofa2.website_status', 'Website 2FA:') ?> <a href="twofa.php"><?= $twofa2_status['any_enabled'] ? t_default('common.enabled', 'Enabled') : t_default('common.disabled', 'Disabled') ?></a></p>
	<?php endif; ?>
	<?php if ($legacy_twofa_status !== null): ?>
		<p><?= t_default('twofa2.legacy_status', 'Legacy game 2FA:') ?> <a href="twofa.php"><?= $legacy_twofa_status ? t_default('common.enabled', 'Enabled') : t_default('common.disabled', 'Disabled') ?></a></p>
	<?php endif; ?>
	<h2><?= t('common.character') ?> List: <?php echo $char_count; ?> characters.</h2>
	<?php if ($char_array): ?>
		<table id="myaccountTable" class="table table-striped table-hover">
			<tr class="yellow">
				<th>NAME</th>
				<th>LEVEL</th>
				<th>VOCATION</th>
				<th>TOWN</th>
				<th>LAST LOGIN</th>
				<th>STATUS</th>
				<th>HIDE</th>
			</tr>
			<?php foreach ($char_array as $value): ?>
				<tr>
					<td><a href="characterprofile.php?name=<?php echo $value['name']; ?>"><?php echo $value['name']; ?></a></td>
					<td><?php echo $value['level']; ?></td>
					<td><?php echo $value['vocation']; ?></td>
					<td><?php echo $value['town_id']; ?></td>
					<td><?php echo $value['lastlogin']; ?></td>
					<td><?php echo $value['online']; ?></td>
					<td><?php echo hide_char_to_name($value['hide_char']); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
		<!-- FORMS TO EDIT CHARACTER-->
		<form action="" method="post">
			<table class="table">
				<tr>
					<td>
						<select id="selected_character" name="selected_character" class="form-control">
							<?php foreach ($char_array as $character): ?>
								<option value="<?php echo $character['name']; ?>"><?php echo $character['name']; ?></option>
							<?php endforeach; ?>
						</select>
					</td>
					<td>
						<select id="action" name="action" class="form-control" onChange="changedOption(this)">
							<option value="none" selected><?= t('acc.select_action') ?></option>
							<option value="toggle_hide"><?= t('acc.toggle_hide') ?></option>
							<option value="change_comment"><?= t('acc.change_comment') ?></option>
							<option value="change_gender"><?= t('acc.change_gender') ?></option>
							<option value="change_name"><?= t('acc.change_name') ?></option>
							<option value="delete_character" class="needconfirmation"><?= t('acc.delete_char') ?></option>
						</select>
					</td>
					<td id="submit_form">
						<?php
							/* Form file */
							Token::create();
						?>
						<input id="submit_button" type="submit" value="<?= t('common.submit') ?>" class="btn btn-primary btn-block"></input>
					</td>
				</tr>
			</table>
		</form>
	<?php else: ?>
		<?= "You don't have any characters. Why don't you <a href='createcharacter.php'>create one</a>?" ?>
	<?php endif; ?>
</div>
<script>
	function changedOption(e) {
		if (e.value == 'change_name') {
			var lastCell = document.getElementById('submit_form');
			var x = document.createElement('TD');
			x.id = "new_name";
			x.innerHTML = '<input type="text" name="newName" placeholder="New Name" class="form-control">';
			lastCell.parentNode.insertBefore(x, lastCell);
		} else {
			var child = document.getElementById('new_name');
			if (child) {
				child.parentNode.removeChild(child);
			}
		}
	}
</script>
<script>
	document.addEventListener('DOMContentLoaded', function () {
		var submitButton = document.getElementById('submit_button');
		var actionSelect = document.getElementById('action');
		var characterSelect = document.getElementById('selected_character');
		if (!submitButton || !actionSelect || !characterSelect) {
			return;
		}

		submitButton.addEventListener('click', function (event) {
			var selectedAction = actionSelect.options[actionSelect.selectedIndex];
			if (selectedAction && selectedAction.classList.contains('needconfirmation')) {
				var selectedCharacter = characterSelect.options[characterSelect.selectedIndex];
				var name = selectedCharacter ? selectedCharacter.text : '';
				if (!confirm('Do you really want to DELETE character: ' + name + '?')) {
					event.preventDefault();
				}
			}
		});
	});
</script>
