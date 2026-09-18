<?php
/**
 * Front page.
 *
 * Prepared by index.php:
 *   $changelogs  changelog ticker entries, or false
 *   $news        news articles, or false
 *   $page, $view pagination state
 */

	if ($config['allowSubPages'] && ($f = theme_file('sub/index.php')) !== null) include $f;
	else {
		if ($config['UseChangelogTicker']) {
			theme_include('parts/changelog-ticker.php', array('changelogs' => $changelogs ?? false));
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
					<table id="news">
						<tr class="yellow">
							<td class="zheadline"><?php echo '<a href="?view='.$news[$si]['id'].'">[#'.$news[$si]['id'].']</a> '. getClock($news[$si]['date'], true) .' '. t('news.by') .' <a href="characterprofile.php?name='. $news[$si]['name'] .'">'. $news[$si]['name'] .'</a> - <b>'. znote_bbcode_raw($news[$si]['title']) .'</b>'; ?></td>
						</tr>
						<tr>
							<td>
								<p><?php echo znote_bbcode_raw($news[$si]['text']); ?></p>
							</td>
						</tr>
					</table>
					<?php
				} else {
					?>
					<table id="news">
						<tr class="yellow">
							<td class="zheadline"><?= t('news.not_found') ?></td>
						</tr>
						<tr>
							<td>
								<p><?= t('news.not_found_text') ?></p>
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
						<table id="news">
							<tr class="yellow">
								<td class="zheadline"><?php echo '<a href="?view='.urlencode($news[$i]['title']).'">'.getClock($news[$i]['date'], true).'</a> '. t('news.by') .' <a href="characterprofile.php?name='. $news[$i]['name'] .'">'. $news[$i]['name'] .'</a> - <b>'. znote_bbcode_raw($news[$i]['title']) .'</b>'; ?></td>
							</tr>
							<tr>
								<td>
									<p><?php echo znote_bbcode_raw($news[$i]['text']); ?></p>
								</td>
							</tr>
						</table>
						<?php
					}
				}

				theme_include('parts/pagination.php', array('page' => $page, 'page_amount' => $page_amount, 'baseUrl' => 'index.php'));

			}

		} else {
			echo '<div class="znx-empty-box">'. h(t('news.none')) .'</div>';
		}
	}
