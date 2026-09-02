<?php
/**
 * View for the front page (index.php).
 *
 * A view is the middle block of one root page - the part between the header
 * and the footer. The page's logic already ran: every variable it prepared is
 * available here.
 *
 * This theme only ships this one view. Every other page of the site falls back
 * to layouts/default/views/, wrapped in THIS theme's shell and styled by THIS
 * theme's CSS. Add views/highscores.php the day you want to restyle that page,
 * and not before.
 */
?>
<h1>Welcome to <?= theme_title() ?></h1>

<p>
	You are looking at <code>layouts/_example/views/index.php</code>.
	The rest of the site is running on the default theme's views, dressed by
	this theme.
</p>

<?php
$news = fetchAllNews();
if (is_array($news)):
	foreach (array_slice($news, 0, (int)($config['news_per_page'] ?? 5)) as $article): ?>
		<article class="well">
			<h2><?= htmlspecialchars((string)$article['title'], ENT_QUOTES, 'UTF-8') ?></h2>
			<p class="txt">
				<?= htmlspecialchars(getClock((int)$article['date'], true), ENT_QUOTES, 'UTF-8') ?>
				by <?= htmlspecialchars((string)($article['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
			</p>
			<div><?= $article['text'] ?></div>
		</article>
	<?php endforeach;
else: ?>
	<p>No news posted yet. Write one from the admin panel.</p>
<?php endif; ?>
