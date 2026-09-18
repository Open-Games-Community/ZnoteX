<?php
if (!empty($config['UseChangelogTicker'])) {
	theme_include('parts/changelog-ticker.php', array('changelogs' => $changelogs ?? false));
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
			?>
			<div class="postHolder">
				<div class="well">
					<div class="header">
						<?php
						echo
							'<a href="?view=' . (int)$news[$si]['id'] . '">[#' . (int)$news[$si]['id'] . ']</a> '
							. getClock($news[$si]['date'], true)
							. ' ' . h(t('news.by')) . ' <a href="characterprofile.php?name='
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
					<td class="zheadline"><?= h(t('news.not_found')) ?></td>
				</tr>
				<tr>
					<td>
						<p><?= h(t('news.not_found_text')) ?></p>
					</td>
				</tr>
			</table>
			<?php
		}

	} else { // We want to view latest news or a page of news.
		echo '<div class="znx-section-head">' . h(t_default('news.section_title', 'Latest News')) . '</div>';
		for ($i = $current; $i < $current + $config['news_per_page']; $i++) {
			if (isset($news[$i])) {
				?>
				<div class="postHolder">
					<div class="well">
						<div class="header">
							<?php
							echo '<a href="?view=' . urlencode($news[$i]['title']) . '">'
								. getClock($news[$i]['date'], true)
								. '</a> ' . h(t('news.by')) . ' <a href="characterprofile.php?name='
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

		theme_include('parts/pagination.php', array('page' => $page, 'page_amount' => $page_amount, 'baseUrl' => 'index.php'));

	}

} else {
	echo '<div class="znx-empty-box">' . h(t('news.none')) . '</div>';
}
?>
