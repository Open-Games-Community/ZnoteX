<?php

?>
<h1><?= t('char.search_title') ?></h1>
<form action="characterprofile.php" method="get">
	<ul>
		<li>
			<?= t('char.search_name') ?><br>
			<input type="text" name="name" autofocus>
		</li>
		<li>
			<input type="submit" value="<?= t('char.search_submit') ?>">
		</li>
	</ul>
</form>
