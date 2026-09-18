<div class="znx-acct">

<div class="znx-acct-head"><?= t('acc.page_title') ?></div>

<div class="znx-acct-info">
	<p><?= t('acc.welcome') ?> <strong><?php echo $user_data['name']; ?></strong><br>
		<?php
		if ($user_data['premdays'] != 0) echo t('acc.premium_days', ['days' => $user_data['premdays']]);
		else echo t('acc.free_account');

		if ($config['mailserver']['myaccount_verify_email']):
			?><br><?= t('login.email') ?>: <?php echo $user_data['email'];
			if ($user_znote_data['active_email'] == 1) {
				?> (<?= t('acc.verified_short') ?>).<?php
			} else {
				?><br><strong><?= t('acc.email_not_verified') ?> <a href="?authenticate"><?= t('acc.please_verify') ?></a>.</strong><?php
			}
		endif; ?>
	</p>

	<?php if ($twofa2_status !== null || $legacy_twofa_status !== null): ?>
		<div class="znx-acct-2fa">
			<?php if ($twofa2_status !== null): ?>
				<span class="znx-acct-2fa__row"><?= t_default('twofa2.website_status', 'Website 2FA:') ?> <a href="twofa.php" class="znx-acct-badge <?= $twofa2_status['any_enabled'] ? 'is-on' : 'is-off' ?>"><?= $twofa2_status['any_enabled'] ? t_default('common.enabled', 'Enabled') : t_default('common.disabled', 'Disabled') ?></a></span>
			<?php endif; ?>
			<?php if ($legacy_twofa_status !== null): ?>
				<span class="znx-acct-2fa__row"><?= t_default('twofa2.legacy_status', 'Legacy game 2FA:') ?> <a href="twofa.php" class="znx-acct-badge <?= $legacy_twofa_status ? 'is-on' : 'is-off' ?>"><?= $legacy_twofa_status ? t_default('common.enabled', 'Enabled') : t_default('common.disabled', 'Disabled') ?></a></span>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>

<div class="znx-acct-head"><?= t('acc.char_list_count', ['count' => $char_count]) ?></div>
<?php if ($char_array): ?>
		<div class="znx-acct-table-wrap">
		<table id="myaccountTable" class="znx-acct-table">
			<tr class="yellow">
				<th><?= mb_strtoupper(t('common.name')) ?></th>
				<th><?= mb_strtoupper(t('common.level')) ?></th>
				<th><?= mb_strtoupper(t('common.vocation')) ?></th>
				<th><?= mb_strtoupper(t('common.town')) ?></th>
				<th><?= mb_strtoupper(t('char.last_login')) ?></th>
				<th><?= mb_strtoupper(t('common.status')) ?></th>
				<th><?= mb_strtoupper(t('acc.hide_col')) ?></th>
				<th></th>
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
					<td><a href="page.php?p=editcharacter&character=<?php echo urlencode($value['name']); ?>" class="znx-acct-edit-btn"><?= t_default('acc.edit_char', 'Edit') ?></a></td>
				</tr>
			<?php endforeach; ?>
		</table>
		</div>
	<?php else: ?>
		<div class="znx-empty-box"><?= t('acc.no_characters') ?></div>
	<?php endif; ?>

</div><!-- .znx-acct -->

<div id="myaccount">
	<h2 class="sr-only"><?= t_default('acc.plugins_heading', 'Account plugins') ?></h2>
</div>
