<div class="well myaccount_widget widget" id="loginContainer">
	<div class="header">
		<?= t('widget.account.welcome', ['name' => $user_data['name']]) ?>
	</div>
	<div class="body">
		<ul class="linkbuttons">
			<li>
				<a href='myaccount.php'><?= t('widget.account.my_account') ?></a>
			</li>
			<li>
				<a href='createcharacter.php'><?= t('widget.account.create_character') ?></a>
			</li>
			<li>
				<a href='changepassword.php'><?= t('widget.account.change_password') ?></a>
			</li>
			<li>
				<a href='settings.php'><?= t('widget.account.settings') ?></a>
			</li>
			<li>
				<a href='logout.php'><?= t('widget.account.logout') ?></a>
			</li>
		</ul>
	</div>
</div>
