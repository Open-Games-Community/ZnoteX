<?php

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

$theme    = $themes[$optionTheme];
$isActive = ($optionTheme === $active);
$backUrl  = acp_url('layouts');
?>

<div class="acp-toolbar">
	<div>
		<a class="acp-btn acp-btn--ghost" href="<?= h($backUrl) ?>">
			<i class="fa fa-angle-left"></i> <?= t('acp.layopt.back') ?>
		</a>
	</div>
	<div class="acp-actions is-tight">
		<?php if (!$isActive): ?>
			<form class="acp-inline-form" method="post">
				<?= acp_csrf_field() ?>
				<input type="hidden" name="activate" value="<?= h($optionTheme) ?>">
				<button class="acp-btn" type="submit"><i class="fa fa-check"></i> <?= t('acp.layopt.activate') ?></button>
			</form>
		<?php else: ?>
			<a class="acp-btn acp-btn--ghost" href="<?= h(acp_site()) ?>" target="_blank" rel="noopener">
				<i class="fa fa-external-link"></i> <?= t('acp.layopt.view_site') ?>
			</a>
		<?php endif; ?>
	</div>
</div>

<section class="acp-card">
	<header class="acp-card-head">
		<h2>
			<?= h($theme['name']) ?> &mdash; <?= t('acp.layopt.options_suffix') ?>
			<?php if ($isActive): ?><span class="acp-pill acp-pill--green"><?= t('acp.layopt.active') ?></span><?php endif; ?>
		</h2>
		<p>
			<code><?= h('layouts/' . $optionTheme . '/') ?></code>
			<?php if ($theme['author'] !== ''): ?>&middot; <?= t('acp.layopt.by_author', ['author' => h($theme['author'])]) ?><?php endif; ?>
			<?php if ($theme['version'] !== ''): ?>&middot; v<?= h($theme['version']) ?><?php endif; ?>
		</p>
	</header>

	<div class="acp-card-body">
		<p class="acp-hint">
			<?= t('acp.layopt.saved_hint') ?>
		</p>

		<form method="post" enctype="multipart/form-data">
			<?= acp_csrf_field() ?>
			<input type="hidden" name="theme_options" value="<?= h($optionTheme) ?>">

			<?php foreach ($optionList as $optKey => $opt):
				$value = theme_option($optKey, '', $optionTheme);
			?>
				<div class="acp-field">
					<label class="acp-label" for="opt_<?= h($optKey) ?>"><?= h($opt['label']) ?></label>

					<?php if ($opt['type'] === 'textarea'): ?>
						<textarea class="acp-textarea" id="opt_<?= h($optKey) ?>" name="opt[<?= h($optKey) ?>]" rows="3"><?= h($value) ?></textarea>
					<?php elseif ($opt['type'] === 'checkbox'): ?>
						<label style="display:flex;align-items:center;gap:8px;font-weight:400;">
							<input type="checkbox" id="opt_<?= h($optKey) ?>" name="opt[<?= h($optKey) ?>]" value="1" <?= $value !== '' ? 'checked' : '' ?>>
							<span class="is-muted"><?= t('acp.layopt.enabled') ?></span>
						</label>
					<?php elseif ($opt['type'] === 'image'): ?>
						<?php $shown = ($value !== '') ? $value : $opt['default']; ?>
						<?php if ($shown !== ''): ?>
							<p style="margin:0 0 8px;">
								<img src="<?= h(acp_site($shown)) ?>" alt=""
									 style="max-width:260px;max-height:110px;border:1px solid var(--acp-line);background:#0b0d10;">
							</p>
						<?php endif; ?>
						<input class="acp-input" id="opt_<?= h($optKey) ?>" name="opt[<?= h($optKey) ?>]"
							   type="text" value="<?= h($value) ?>"
							   placeholder="<?= h($opt['default'] !== '' ? $opt['default'] : t('acp.layopt.image_placeholder')) ?>">
						<p class="acp-hint" style="margin:6px 0 4px;"><?= t('acp.layopt.upload_hint') ?></p>
						<input class="acp-input" type="file" name="optfile[<?= h($optKey) ?>]" accept="image/png,image/jpeg,image/gif,image/webp">
					<?php else: ?>
						<input class="acp-input" id="opt_<?= h($optKey) ?>" name="opt[<?= h($optKey) ?>]"
							   type="<?= in_array($opt['type'], array('url', 'datetime-local'), true) ? h($opt['type']) : 'text' ?>"
							   value="<?= h($value) ?>"
							   placeholder="<?= h($opt['default']) ?>">
					<?php endif; ?>

					<?php if ($opt['help'] !== ''): ?>
						<p class="acp-hint"><?= h($opt['help']) ?></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<div class="acp-actions">
				<button class="acp-btn acp-btn--green" type="submit"><i class="fa fa-check"></i> <?= t('acp.layopt.save_btn') ?></button>
				<a class="acp-btn acp-btn--ghost" href="<?= h($backUrl) ?>"><?= t('acp.layopt.back') ?></a>
			</div>
		</form>
	</div>
</section>
