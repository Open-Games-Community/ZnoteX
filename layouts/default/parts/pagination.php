<?php
$znxPageH = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$znxCurrentPage = (int)($page ?? 0);
$znxTotalPages = (int)($page_amount ?? 0);
$znxBaseUrl = $baseUrl ?? 'index.php';

if ($znxTotalPages > 0):
	$znxDelta = 2;
	$znxSteps = array();
	for ($i = 0; $i < $znxTotalPages; $i++) {
		if ($i === 0 || $i === $znxTotalPages - 1 || ($i >= $znxCurrentPage - $znxDelta && $i <= $znxCurrentPage + $znxDelta)) {
			$znxSteps[] = $i;
		}
	}
	$znxItems = array();
	$znxLast = null;
	foreach ($znxSteps as $i) {
		if ($znxLast !== null && $i - $znxLast > 1) $znxItems[] = '...';
		$znxItems[] = $i;
		$znxLast = $i;
	}
	?>
	<style>
	.znx-pager{--pager-surface:var(--box-inner-bg,var(--box-bg,var(--nz-panel,var(--wc-bg-2,var(--z-panel-2,var(--s-panel,var(--primary,rgb(30,33,40))))))));--pager-text:var(--font-color,var(--text,var(--nz-text,var(--wc-text,var(--z-text,var(--s-text,rgb(155,162,177)))))));--pager-muted:color-mix(in srgb,var(--pager-text) 58%,transparent);--pager-border:var(--border,var(--box-inner-border,var(--box-border,var(--nz-border-soft,var(--wc-line,var(--z-border,var(--s-border2,rgb(19,20,23))))))));--pager-accent:var(--accent,var(--link,var(--nz-accent,#d1a233)));display:flex;justify-content:center;align-items:center;gap:6px;flex-wrap:wrap;margin:22px 0 0;padding-top:18px;border-top:1px solid var(--pager-border)}
	.znx-pager a,.znx-pager__current,.znx-pager__ellipsis{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;border-radius:6px;font-size:13px;font-weight:700;text-decoration:none}
	.znx-pager a{border:1px solid var(--pager-border);background:var(--pager-surface);color:var(--pager-text);cursor:pointer}
	.znx-pager a:hover{border-color:var(--pager-accent);color:var(--pager-accent)}
	.znx-pager__current{border:1px solid var(--pager-accent);background:var(--pager-accent);color:#1a1510;cursor:default}
	.znx-pager__ellipsis{color:var(--pager-muted)}
	</style>
	<nav class="znx-pager" aria-label="<?= $znxPageH(t_default('common.pagination', 'Pagination')) ?>">
		<?php if ($znxCurrentPage > 0): ?>
			<a href="<?= $znxPageH($znxBaseUrl) ?>?page=<?= $znxCurrentPage - 1 ?>" aria-label="<?= $znxPageH(t('common.previous')) ?>">&laquo;</a>
		<?php endif; ?>
		<?php foreach ($znxItems as $znxItem): ?>
			<?php if ($znxItem === '...'): ?>
				<span class="znx-pager__ellipsis">&hellip;</span>
			<?php elseif ($znxItem === $znxCurrentPage): ?>
				<span class="znx-pager__current" aria-current="page"><?= (int)$znxItem + 1 ?></span>
			<?php else: ?>
				<a href="<?= $znxPageH($znxBaseUrl) ?>?page=<?= (int)$znxItem ?>"><?= (int)$znxItem + 1 ?></a>
			<?php endif; ?>
		<?php endforeach; ?>
		<?php if ($znxCurrentPage < $znxTotalPages - 1): ?>
			<a href="<?= $znxPageH($znxBaseUrl) ?>?page=<?= $znxCurrentPage + 1 ?>" aria-label="<?= $znxPageH(t('common.next')) ?>">&raquo;</a>
		<?php endif; ?>
	</nav>
<?php endif; ?>
