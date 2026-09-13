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
