<?php
/**
 * Account settings.
 *
 * Prepared by settings.php:
 *   $formState  'success' | 'errors' | 'form'
 *   $errors     array of messages, when $formState is 'errors'
 *
 * The account write happens in settings.php, never here.
 */
?>
<h1>Settings</h1>

<?php if ($formState === 'success'): ?>

	Your settings have been updated.

<?php else: ?>

	<?php if ($formState === 'errors'): ?>
		<?= output_errors($errors) ?>
	<?php endif; ?>

	<form action="" method="post">
		<ul>
			<li>
				email:<br>
				<input type="text" name="new_email" value="<?php echo $user_data['email']; ?>">
			</li>
			<li>
				Country:<br>
				<select name="new_flag" id="flag_select">
					<option value="">(Please choose)</option>
					<?php
					foreach(array('pl', 'se', 'br', 'us', 'gb', ) as $c)
						echo '<option value="' . $c . '">' . $config['countries'][$c] . '</option>';

						echo '<option value="">----------</option>';
						foreach($config['countries'] as $code => $c)
							echo '<option value="' . $code . '"' . (isset($user_znote_data['flag']) && $user_znote_data['flag'] == $code ? ' selected' : '') . '>' . $c . '</option>';
					?>
				</select>
			</li>
			<?php Token::create(); ?>
			<li>
				<input type="submit" value="Update settings">
			</li>
		</ul>
	</form>

<?php endif; ?>
