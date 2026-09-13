<?php
?>
<h1><?= t('helpdesk.latest') ?></h1>
<?php if ($tickets !== false): ?>
	<table>
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
<?php endif; ?>

<h1><?= t('helpdesk.title') ?></h1>
<?php if ($helpdeskCreated): ?>
	<?= t('helpdesk.created') ?>
<?php else: ?>
	<?php if (empty($errors) === false): ?>
		<font color="red"><b><?= output_errors($errors) ?></b></font>
	<?php endif; ?>
	<form action="" method="post">
		<ul>
			<li>
				Account Name:<br>
				<input type="text" name="username" size="40" value="<?php echo $account['name']; ?>" disabled>
			</li>
			<li>
				Email:<br>
				<input type="text" name="email" size="40" value="<?php echo $account['email']; ?>" disabled>
			</li>
			<li>
				<?= t('helpdesk.subject') ?><br>
				<input type="text" name="subject" size="40">
			</li>
			<li>
				Message:<br>
				<textarea name="message" rows="7" cols="30"></textarea>
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
			<?php
				/* Form file */
				Token::create();
			?>
			<li>
				<input type="hidden" name="username" value="<?php echo $account['name']; ?>">
				<input type="submit" value="<?= t('helpdesk.submit') ?>">
			</li>
		</ul>
	</form>
<?php endif; ?>
