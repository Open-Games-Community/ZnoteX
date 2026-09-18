<?php
?>
<div class="znx-acct">

<div class="znx-acct-head"><?= t('helpdesk.latest') ?></div>
<?php if ($tickets !== false): ?>
	<div class="znx-acct-table-wrap">
	<table class="znx-acct-table">
		<tr class="yellow">
			<td>ID:</td>
			<td><?= t('helpdesk.subject') ?></td>
			<td><?= t('helpdesk.creation') ?></td>
			<td>Status:</td>
		</tr>
			<?php
			foreach ($tickets as $ticket) {
				echo '<tr class="special">';
					echo '<td>'. $ticket['id'] .'</td>';
					echo '<td><a href="helpdesk.php?view='. $ticket['id'] .'">'. $ticket['subject'] .'</a></td>';
					echo '<td>'. getClock($ticket['creation'], true) .'</td>';
					echo '<td>'. $ticket['status'] .'</td>';
				echo '</tr>';
			}
			?>
	</table>
	</div>
<?php endif; ?>

<div class="znx-acct-head"><?= t('helpdesk.title') ?></div>
<?php if ($helpdeskCreated): ?>
	<div class="znx-acct-info"><?= t('helpdesk.created') ?></div>
<?php else: ?>
	<?php if (empty($errors) === false): ?>
		<div class="znx-acct-info znx-editchar-notice znx-editchar-notice--error"><?= output_errors($errors) ?></div>
	<?php endif; ?>
	<form action="" method="post" class="znx-editchar-box">
		<div class="znx-settings-field">
			<label>Account Name</label>
			<input type="text" name="username" size="40" value="<?php echo $account['name']; ?>" disabled class="znx-acct-select">
		</div>
		<div class="znx-settings-field">
			<label>Email</label>
			<input type="text" name="email" size="40" value="<?php echo $account['email']; ?>" disabled class="znx-acct-select">
		</div>
		<div class="znx-settings-field">
			<label><?= t('helpdesk.subject') ?></label>
			<input type="text" name="subject" size="40" class="znx-acct-select">
		</div>
		<div class="znx-settings-field">
			<label>Message</label>
			<textarea name="message" rows="7" cols="30" class="znx-editchar-textarea"></textarea>
		</div>
		<?php
		if ($config['use_captcha']) {
			?>
			<div class="znx-settings-field">
				 <div class="g-recaptcha" data-sitekey="<?php echo $config['captcha_site_key']; ?>"></div>
			</div>
			<?php
		}
		?>
		<?php
			/* Form file */
			Token::create();
		?>
		<input type="hidden" name="username" value="<?php echo $account['name']; ?>">
		<button type="submit" class="znx-acct-btn"><?= t('helpdesk.submit') ?></button>
	</form>
<?php endif; ?>

</div><!-- .znx-acct -->
