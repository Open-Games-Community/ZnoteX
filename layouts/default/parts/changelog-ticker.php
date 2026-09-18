<?php
$znxClogH = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>

<style>
.znx-clog{--clog-surface:var(--box-inner-bg,var(--box-bg,var(--nz-panel,var(--wc-bg-2,var(--z-panel-2,var(--s-panel,var(--primary,rgb(30,33,40))))))));--clog-surface-2:color-mix(in srgb,var(--clog-surface) 82%,#000);--clog-text:var(--font-color,var(--text,var(--nz-text,var(--wc-text,var(--z-text,var(--s-text,rgb(155,162,177)))))));--clog-muted:color-mix(in srgb,var(--clog-text) 58%,transparent);--clog-border:var(--border,var(--box-inner-border,var(--box-border,var(--nz-border-soft,var(--wc-line,var(--z-border,var(--s-border2,rgb(19,20,23))))))));--clog-accent:var(--accent,var(--link,var(--nz-accent,#d1a233)));--clog-radius:6px;margin:0 0 18px;color:var(--clog-text)}
.znx-clog *{box-sizing:border-box}
.znx-clog__head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 14px;border:1px solid var(--clog-border);border-radius:var(--clog-radius) var(--clog-radius) 0 0;background:var(--clog-surface);font-weight:800;color:var(--clog-accent)}
.znx-clog__head a{color:var(--clog-accent);font-weight:700;font-size:12.5px}
.znx-clog__body{border:1px solid var(--clog-border);border-top:0;border-radius:0 0 var(--clog-radius) var(--clog-radius);background:var(--clog-surface-2);overflow:hidden}
.znx-clog__empty{padding:16px;text-align:center;color:var(--clog-muted)}
.znx-clog-row{border-top:1px solid var(--clog-border)}
.znx-clog-row:first-child{border-top:0}
.znx-clog-row__summary{display:flex;align-items:baseline;gap:10px;padding:9px 14px;cursor:pointer;list-style:none}
.znx-clog-row__summary::-webkit-details-marker{display:none}
.znx-clog-row__summary:hover{background:color-mix(in srgb,var(--clog-accent) 8%,transparent)}
.znx-clog-row__time{flex:0 0 auto;font-size:12px;color:var(--clog-muted);white-space:nowrap}
.znx-clog-row__preview{flex:1 1 auto;min-width:0;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.znx-clog-row[open] .znx-clog-row__preview{display:none}
.znx-clog-row__toggle{flex:0 0 auto;margin-left:auto;width:16px;height:16px;border-radius:4px;border:1px solid var(--clog-accent);color:var(--clog-accent);font-size:12px;line-height:14px;text-align:center;font-weight:800}
.znx-clog-row__toggle::before{content:"+"}
.znx-clog-row[open] .znx-clog-row__toggle::before{content:"\2212"}
.znx-clog-row__full{padding:0 14px 12px;color:var(--clog-text);font-size:13px;line-height:1.5}
</style>

<div class="znx-clog">
	<div class="znx-clog__head">
		<span><?= $znxClogH(t('news.changelog_ticker')) ?></span>
		<a href="changelog.php"><?= $znxClogH(t('news.changelog_full')) ?></a>
	</div>
	<div class="znx-clog__body">
		<?php if (!empty($changelogs) && $changelogs !== false): ?>
			<?php for ($i = 0; $i < count($changelogs) && $i < 5; $i++): ?>
				<details class="znx-clog-row">
					<summary class="znx-clog-row__summary">
						<span class="znx-clog-row__time"><?= $znxClogH(getClock($changelogs[$i]['time'] ?? 0, true, true)) ?></span>
						<span class="znx-clog-row__preview"><?= $changelogs[$i]['text'] ?? '' ?></span>
						<span class="znx-clog-row__toggle" aria-hidden="true"></span>
					</summary>
					<div class="znx-clog-row__full"><?= $changelogs[$i]['text'] ?? '' ?></div>
				</details>
			<?php endfor; ?>
		<?php else: ?>
			<div class="znx-clog__empty"><?= $znxClogH(t('news.no_changelogs')) ?></div>
		<?php endif; ?>
	</div>
</div>
