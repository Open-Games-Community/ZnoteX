<?php
/**
 * Title: Search
 * Icon: fa-search
 * Group: Overview
 * Order: 5
 * Hidden: true
 * Description: Everything in the panel, by name or by what a setting does.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

$query   = trim((string)($_GET['q'] ?? ''));
$results = acp_search($query);

$pages    = array_values(array_filter($results, static fn(array $r): bool => $r['kind'] === 'page'));
$settings = array_values(array_filter($results, static fn(array $r): bool => $r['kind'] === 'setting'));

$highlight = static function (string $text, string $q): string {
	$text = h($text);
	foreach (preg_split('/\s+/', trim($q)) ?: [] as $term) {
		if ($term === '') continue;
		$text = preg_replace('/(' . preg_quote(h($term), '/') . ')/i', '<mark>$1</mark>', $text);
	}
	return $text;
};
?>

<form class="acp-search-page" method="get">
	<input type="hidden" name="p" value="search">
	<div class="acp-field">
		<input class="acp-input acp-input--lg" type="search" name="q" value="<?= h($query) ?>"
			   placeholder="<?= h(t('acp.search.placeholder')) ?>" autofocus>
	</div>
</form>

<?php if ($query === ''): ?>
	<div class="acp-flash acp-flash--info">
		<i class="fa fa-info-circle"></i>
		<span>
			<?= str_replace(
				['{download}', '{paypal}', '{auction}'],
				['<code>download</code>', '<code>paypal</code>', '<code>auction</code>'],
				h(t('acp.search.hint'))
			) ?>
		</span>
	</div>
<?php elseif (!$results): ?>
	<div class="acp-flash acp-flash--error">
		<i class="fa fa-exclamation-triangle"></i>
		<span><?= t('acp.search.nothing') ?> <strong><?= h($query) ?></strong>.</span>
	</div>
<?php else: ?>

	<p class="acp-hint">
		<?= t('acp.search.results_line', [
			'count' => count($results),
			'noun'  => (count($results) === 1) ? t('acp.search.result_one') : t('acp.search.result_many'),
		]) ?> <strong><?= h($query) ?></strong>
	</p>

	<?php foreach (array(t('acp.search.section_pages') => $pages, t('acp.search.section_settings') => $settings) as $heading => $rows): ?>
		<?php if (!$rows) continue; ?>
		<section class="acp-card">
			<header class="acp-card-head"><h2><?= h($heading) ?></h2></header>
			<div class="acp-card-body is-flush">
				<ul class="acp-search-results">
					<?php foreach ($rows as $row): ?>
						<li>
							<a href="<?= h($row['url']) ?>">
								<i class="fa <?= h($row['icon']) ?>"></i>
								<span class="acp-search-main">
									<span class="acp-search-title"><?= $highlight($row['title'], $query) ?></span>
									<span class="acp-search-ctx"><?= $row['context'] ?></span>
									<?php if (!empty($row['detail'])): ?>
										<span class="acp-search-detail"><?= $highlight($row['detail'], $query) ?></span>
									<?php endif; ?>
								</span>
								<i class="fa fa-angle-right acp-search-go"></i>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
	<?php endforeach; ?>

<?php endif; ?>
