<?php
if (!empty($config['UseChangelogTicker'])) {
	//////////////////////
	// Changelog ticker //
	// Load from cache
	$changelogCache = new Cache('engine/cache/changelog');
	$changelogCache->useMemory(false);
	$changelogs = $changelogCache->load();

	if (isset($changelogs) && !empty($changelogs) && $changelogs !== false) {
		?>
		<div class="well">
			<table id="changelogTable">
				<tr class="yellow">
					<td colspan="2">Latest Changelog Updates (<a href="changelog.php">Click here to see full changelog</a>)</td>
				</tr>
				<?php
				for ($i = 0; $i < count($changelogs) && $i < 5; $i++) {
					?>
					<tr>
						<td><?= getClock($changelogs[$i]['time'] ?? 0, true, true); ?></td>
						<td><?= $changelogs[$i]['text'] ?? ''; ?></td>
					</tr>
					<?php
				}
				?>
			</table>
		</div>
		<?php
	} else echo "No changelogs submitted.";
}

$page = isset($page) && is_numeric($page) ? (int)$page : 0;
$view = $view ?? '';

$cache = new Cache('engine/cache/news');
if ($cache->hasExpired()) {
	$news = fetchAllNews();
	$cache->setContent($news);
	$cache->save();
} else {
	$news = $cache->load();
}

// Design and present the list
if ($news) {

	$total_news = count($news);
	$row_news = $total_news / $config['news_per_page'];
	$page_amount = ceil($total_news / $config['news_per_page']);
	$current = $config['news_per_page'] * $page;

	if ($view !== "") { // We want to view a specific news post
		$si = false;
		if (ctype_digit($view) === false) {
			for ($i = 0; $i < count($news); $i++) if ($view === urlencode($news[$i]['title'])) $si = $i;
		} else {
			for ($i = 0; $i < count($news); $i++) if ((int)$view === (int)$news[$i]['id']) $si = $i;
		}

		if ($si !== false) {
			echo "hello world!";
			?>
			<div class="postHolder">
				<div class="well">
					<div class="header">
						<?php
						echo
							'<a href="?view=' . (int)$news[$si]['id'] . '">[#' . (int)$news[$si]['id'] . ']</a> '
							. getClock($news[$si]['date'], true)
							. ' by <a href="characterprofile.php?name='
							. urlencode($news[$si]['name'])
							. '">'
							. htmlspecialchars($news[$si]['name'], ENT_QUOTES, 'UTF-8')
							. '</a> - <b>'
							. znote_bbcode_raw($news[$si]['title'])
							. '</b>';
						?>
					</div>
					<div class="body">
						<p><?php echo znote_bbcode_raw($news[$si]['text']); ?></p>
					</div>
				</div>
			</div>
			<!-- OLD DESIGN: -->
			<?php
		} else {
			?>
			<table id="news">
				<tr class="yellow">
					<td class="zheadline">News post not found.</td>
				</tr>
				<tr>
					<td>
						<p>We failed to find the post you where looking for.</p>
					</td>
				</tr>
			</table>
			<?php
		}

	} else { // We want to view latest news or a page of news.
		for ($i = $current; $i < $current + $config['news_per_page']; $i++) {
			if (isset($news[$i])) {
				?>
				<div class="postHolder">
					<div class="well">
						<div class="header">
							<?php
							echo '<a href="?view=' . urlencode($news[$i]['title']) . '">'
								. getClock($news[$i]['date'], true)
								. '</a> by <a href="characterprofile.php?name='
								. urlencode($news[$i]['name'])
								. '">'
								. htmlspecialchars($news[$i]['name'], ENT_QUOTES, 'UTF-8')
								. '</a> - <b>'
								. znote_bbcode_raw($news[$i]['title'])
								. '</b>';
							?>
						</div>
						<div class="body">
							<p><?php echo znote_bbcode_raw($news[$i]['text']); ?></p>
						</div>
					</div>
				</div>
				<?php
			}
		}

		echo '<select name="newspage" onchange="location = this.options[this.selectedIndex].value;">';

		for ($i = 0; $i < $page_amount; $i++) {

			if ($i == $page) {

				echo '<option value="index.php?page='.$i.'" selected>Page '.$i.'</option>';

			} else {

				echo '<option value="index.php?page='.$i.'">Page '.$i.'</option>';
			}
		}

		echo '</select>';

	}

} else {
	echo '<p>No news exist.</p>';
}
?>
