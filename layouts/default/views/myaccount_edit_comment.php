<h1><?= t('acc.change_comment_on') ?></h1>
<form action="" method="post">
	<ul>
		<li>
			<input name="action" type="hidden" value="update_comment">
			<input name ="selected_character" type="text" value="<?php echo $char_name; ?>" readonly="readonly">
		</li>
		<li>
			<font class="profile_font" name="profile_font_comment"><?= t('acc.comment') ?></font> <br>
			<textarea name="comment" cols="70" rows="10"><?php echo $comment_data['comment']; ?></textarea>
		</li>
		<?php
			/* Form file */
			Token::create();
		?>
		<li><input type="submit" value="<?= t('acc.update_comment') ?>"></li>
	</ul>
</form>
