<div class="well myaccount_widget widget">
	<div class="header">
		<?= t('widget.admin.title') ?>
	</div>
	<div class="body">
		<ul class="linkbuttons">
			<li>
				<a href="admin/index.php"><i class="fa fa-sliders"></i> <?= t('widget.admin.panel') ?></a>
			</li>
			<?php
			// Everything that used to be a separate admin_*.php link now lives
			// inside the panel. Only the counter is worth surfacing out here.
			$new = 0;
			$cache = new Cache('engine/cache/asideFeedbackCount');
			if ($cache->hasExpired()) {
				$cat = 4; // Category ID for feedback section
				$threads = mysql_select_multi("SELECT `id`, `player_id` FROM `znote_forum_threads` WHERE `forum_id`='$cat' AND `closed`='0';");
				if ($threads !== false) {
					$staffs = mysql_select_multi("SELECT `id` FROM `players` WHERE `group_id` > '1';");

					foreach($threads as $thread) {
						$response = false;
						$posts = mysql_select_multi("SELECT `id`, `player_id` FROM `znote_forum_posts` WHERE `thread_id`='". $thread['id'] ."';");
						if ($posts !== false) {
							foreach($posts as $post) {
								foreach ($staffs as $staff) {
									if ($post['player_id'] == $staff['id']) $response = true;
								}
							}
						}

						if (!$response) $new++;
					}
				}
				$cache->setContent($new);
				$cache->save();
			} else {
				$new = $cache->load();
			}
			?>
			<li>
				<a href='forum.php?cat=4'><?= t('widget.admin.feedback', ['count' => $new]) ?></a>
			</li>
		</ul>
	</div>
</div>
