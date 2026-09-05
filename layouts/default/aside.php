<?php
/**
 * Right column of the default theme.
 *
 * Included only because shells/default.php calls theme_sidebar(). A theme with
 * no sidebar simply does not call it, and does not need this file at all.
 *
 * Each widget() call loads widgets/<name>.php from this theme, falling back to
 * the default theme's copy.
 */
?>
<!-- RIGHT PANE -->
<div class="pull-right rightPane">
<?php

if (user_logged_in() === true) {

	widget('myaccount');

	if (isset($user_data) && is_admin($user_data)) {
		widget('admin');
	}

} else {
	widget('login');
}

if (!empty($config['otservers_eu_voting']['enabled'])) {
	widget('vote');
}

widget('charactersearch');
widget('topplayers');
widget('highscore');

if (!empty($config['powergamers']['enabled'])) {
	widget('powergamers');
}

widget('serverinfo');

if (!empty($config['ServerEngine']) && $config['ServerEngine'] !== 'TFS_02') {
	widget('houses');
}

/* FOLLOW BLOCK */
if (!empty($follow['enabled'])): ?>
	<div class="well">
		<div class="header"><?= t('widget.follow.title') ?></div>
		<div class="body">
			<table class="smedia centralizeContent">
				<tr>
					<td><a href="<?= htmlspecialchars($follow['facebook'] ?? '#') ?>" target="_blank" rel="noopener"><i class="fa fa-facebook"></i></a></td>
					<td><a href="<?= htmlspecialchars($follow['twitter']  ?? '#') ?>" target="_blank" rel="noopener"><i class="fa fa-twitter"></i></a></td>
					<td><a href="<?= htmlspecialchars($follow['youtube']  ?? '#') ?>" target="_blank" rel="noopener"><i class="fa fa-youtube"></i></a></td>
					<td><a href="<?= htmlspecialchars($follow['twitch']   ?? '#') ?>" target="_blank" rel="noopener"><i class="fa fa-twitch"></i></a></td>
				</tr>
			</table>
		</div>
	</div>
<?php endif; ?>

</div>
<!-- RIGHT PANE END -->
