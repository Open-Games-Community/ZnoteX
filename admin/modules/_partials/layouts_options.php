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
			<i class="fa fa-angle-left"></i> Back to themes
		</a>
	</div>
	<div class="acp-actions is-tight">
		<?php if (!$isActive): ?>
			<form class="acp-inline-form" method="post">
				<?= acp_csrf_field() ?>
				<input type="hidden" name="activate" value="<?= h($optionTheme) ?>">
				<button class="acp-btn" type="submit"><i class="fa fa-check"></i> Activate</button>
			</form>
		<?php else: ?>
			<a class="acp-btn acp-btn--ghost" href="<?= h(acp_site()) ?>" target="_blank" rel="noopener">
				<i class="fa fa-external-link"></i> View site
			</a>
		<?php endif; ?>
	</div>
</div>

<section class="acp-card">
	<header class="acp-card-head">
		<h2>
			<?= h($theme['name']) ?> &mdash; options
			<?php if ($isActive): ?><span class="acp-pill acp-pill--green">Active</span><?php endif; ?>
		</h2>
		<p>
			<code><?= h('layouts/' . $optionTheme . '/') ?></code>
			<?php if ($theme['author'] !== ''): ?>&middot; by <?= h($theme['author']) ?><?php endif; ?>
			<?php if ($theme['version'] !== ''): ?>&middot; v<?= h($theme['version']) ?><?php endif; ?>
		</p>
	</header>

	<div class="acp-card-body">
		<p class="acp-hint">
			Saved per theme, in the database. The theme's files are never modified, so an update to
			the theme cannot lose these. Leave a field empty to fall back to the theme's own default.
		</p>

		<form method="post">
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
							<span class="is-muted">Enabled</span>
						</label>
					<?php else: ?>
						<input class="acp-input" id="opt_<?= h($optKey) ?>" name="opt[<?= h($optKey) ?>]"
							   type="<?= $opt['type'] === 'url' ? 'url' : 'text' ?>"
							   value="<?= h($value) ?>"
							   placeholder="<?= h($opt['default']) ?>">
					<?php endif; ?>

					<?php if ($opt['help'] !== ''): ?>
						<p class="acp-hint"><?= h($opt['help']) ?></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<div class="acp-actions">
				<button class="acp-btn acp-btn--green" type="submit"><i class="fa fa-check"></i> Save options</button>
				<a class="acp-btn acp-btn--ghost" href="<?= h($backUrl) ?>">Back to themes</a>
			</div>
		</form>
	</div>
</section>
