<?php
/**
 * Browse tab: themes offered by the remote catalogue.
 * Included by admin/modules/layouts.php when ?tab=browse.
 *
 * @var array  $themes    installed themes, keyed by folder
 * @var string $active    the active theme's key
 */

$repoConfig = theme_repository_config();
$catalogue  = theme_repository_list(isset($_GET['refresh']));
$remote     = $catalogue['themes'];
$repoError  = (string)($catalogue['error'] ?? '');
?>

<div class="acp-toolbar">
	<div>
		<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('layouts')) ?>">
			<i class="fa fa-arrow-left"></i> <?= t('acp.laybr.back') ?>
		</a>
	</div>
	<div class="acp-actions is-tight">
		<span class="is-muted"><?= t('acp.laybr.available', ['n' => count($remote)]) ?></span>
		<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('layouts', ['tab' => 'browse', 'refresh' => 1])) ?>">
			<i class="fa fa-refresh"></i> <?= t('acp.laybr.refresh') ?>
		</a>
	</div>
</div>

<?php if (!$repoConfig['enabled']): ?>

	<section class="acp-card">
		<div class="acp-card-body">
			<?php acp_empty(t('acp.laybr.repo_off'), 'fa-plug'); ?>
			<p class="is-muted" style="text-align:center;">
				<?= t('acp.laybr.repo_off_hint', ['code' => '<code>$config[\'layout_repository\'][\'enabled\'] = true;</code>']) ?>
			</p>
		</div>
	</section>

<?php elseif ($repoError !== ''): ?>

	<div class="acp-flash acp-flash--error">
		<i class="fa fa-exclamation-triangle"></i>
		<span>
			<strong><?= t('acp.laybr.catalogue_error') ?></strong><br>
			<?= h($repoError) ?><br>
			<span class="is-muted"><?= t('acp.laybr.catalogue_url') ?> <code><?= h($repoConfig['index']) ?></code></span>
		</span>
	</div>

<?php elseif (!$remote): ?>

	<section class="acp-card">
		<div class="acp-card-body">
			<?php acp_empty(t('acp.laybr.catalogue_empty'), 'fa-inbox'); ?>
		</div>
	</section>

<?php else: ?>

	<div class="acp-flash acp-flash--info">
		<i class="fa fa-info-circle"></i>
		<span>
			<?= t('acp.laybr.warning', [
				'hosts'     => '<code>' . h(implode('</code>, <code>', $repoConfig['allowed_hosts'])) . '</code>',
				'configphp' => '<code>config.php</code>',
			]) ?>
		</span>
	</div>

	<div class="acp-media">
		<?php foreach ($remote as $key => $item):
			$installed = isset($themes[$key]);
			$isActive  = ($key === $active);
			$localVer  = $installed ? (string)$themes[$key]['version'] : '';
			$isUpdate  = ($installed && $item['version'] !== '' && $localVer !== '' && version_compare($item['version'], $localVer, '>'));
		?>
			<article class="acp-media-item<?= $installed && !$isUpdate ? ' is-dimmed' : '' ?>">

				<?php if ($item['screenshot'] !== ''): ?>
					<img src="<?= h($item['screenshot']) ?>" alt="<?= h($item['name']) ?>" loading="lazy"
						 referrerpolicy="no-referrer">
				<?php else: ?>
					<div style="display:grid;place-items:center;height:170px;background:var(--acp-panel-2);color:var(--acp-fg-muted);">
						<span><i class="fa fa-picture-o"></i> &nbsp;<?= t('acp.laybr.no_screenshot') ?></span>
					</div>
				<?php endif; ?>

				<div class="acp-media-body">
					<h3>
						<?= h($item['name']) ?>
						<?php if ($isActive): ?>
							<span class="acp-pill acp-pill--green"><?= t('acp.laybr.active') ?></span>
						<?php elseif ($isUpdate): ?>
							<span class="acp-pill acp-pill--amber"><?= t('acp.laybr.update_available') ?></span>
						<?php elseif ($installed): ?>
							<span class="acp-pill acp-pill--grey"><?= t('acp.laybr.installed_pill') ?></span>
						<?php endif; ?>
					</h3>

					<p><?= h($item['description']) ?></p>

					<p class="is-muted" style="font-size:12px;">
						<code>layouts/<?= h($key) ?>/</code>
						<?php if ($item['author'] !== ''): ?>&middot; <?= t('acp.laybr.by_author', ['author' => h($item['author'])]) ?><?php endif; ?>
						<?php if ($item['version'] !== ''): ?>
							&middot; v<?= h($item['version']) ?>
							<?php if ($isUpdate): ?>
								<span class="acp-pill acp-pill--amber"><?= t('acp.laybr.you_have', ['version' => h($localVer)]) ?></span>
							<?php endif; ?>
						<?php endif; ?>
					</p>

					<?php if (!$item['installable']): ?>
						<p style="font-size:12px;color:var(--acp-red);">
							<?= t('acp.laybr.not_installable') ?>
						</p>
					<?php endif; ?>
				</div>

				<div class="acp-media-foot">
					<?php if (!$item['installable']): ?>

						<?php if ($item['url'] !== ''): ?>
							<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h($item['url']) ?>" target="_blank" rel="noopener">
								<i class="fa fa-external-link"></i> <?= t('acp.laybr.open_page') ?>
							</a>
						<?php endif; ?>

					<?php elseif ($installed && !$isUpdate): ?>

						<span class="acp-btn acp-btn--ghost acp-btn--sm is-disabled">
							<i class="fa fa-check"></i> <?= t('acp.laybr.installed_badge') ?>
						</span>
						<form class="acp-inline-form" method="post"
							  data-confirm="<?= h(t('acp.laybr.confirm_reinstall', ['name' => $item['name'], 'key' => $key])) ?>">
							<?= acp_csrf_field() ?>
							<input type="hidden" name="install" value="<?= h($key) ?>">
							<input type="hidden" name="overwrite" value="1">
							<button class="acp-btn acp-btn--ghost acp-btn--sm" type="submit">
								<i class="fa fa-refresh"></i> <?= t('acp.laybr.reinstall') ?>
							</button>
						</form>

					<?php else: ?>

						<form class="acp-inline-form" method="post"
							  data-confirm="<?= $installed
								  ? h(t('acp.laybr.confirm_update', ['name' => $item['name'], 'version' => $item['version'], 'key' => $key]))
								  : h(t('acp.laybr.confirm_install', ['name' => $item['name']])) ?>">
							<?= acp_csrf_field() ?>
							<input type="hidden" name="install" value="<?= h($key) ?>">
							<?php if ($installed): ?>
								<input type="hidden" name="overwrite" value="1">
							<?php endif; ?>
							<button class="acp-btn acp-btn--sm" type="submit">
								<i class="fa fa-<?= $installed ? 'arrow-up' : 'download' ?>"></i>
								<?= $installed ? t('acp.laybr.update_btn') : t('acp.laybr.install_btn') ?>
							</button>
						</form>

					<?php endif; ?>

					<?php if ($item['url'] !== '' && $item['installable']): ?>
						<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h($item['url']) ?>" target="_blank" rel="noopener">
							<i class="fa fa-external-link"></i> <?= t('acp.laybr.details') ?>
						</a>
					<?php endif; ?>
				</div>
			</article>
		<?php endforeach; ?>
	</div>

<?php endif; ?>

<section class="acp-card">
	<header class="acp-card-head">
		<h2>Publishing a theme here</h2>
		<p>What the catalogue expects</p>
	</header>
	<div class="acp-card-body">
		<p>
			The catalogue is a JSON file at
			<code><?= h($repoConfig['index'] !== '' ? $repoConfig['index'] : 'not configured') ?></code>,
			one entry per theme:
		</p>
		<pre class="acp-dump">[
  {
    "key": "darkfantasy",
    "name": "Dark Fantasy",
    "version": "1.0.0",
    "author": "Alex",
    "description": "One or two sentences.",
    "screenshot": "https://raw.githubusercontent.com/&lt;user&gt;/&lt;repo&gt;/layouts/darkfantasy/screenshot.png",
    "download":   "https://raw.githubusercontent.com/&lt;user&gt;/&lt;repo&gt;/layouts/darkfantasy.zip",
    "url":        "https://github.com/&lt;user&gt;/&lt;repo&gt;/tree/layouts/darkfantasy"
  }
]</pre>
		<p>
			<code>key</code> becomes the folder name in <code>layouts/</code>. The archive must contain
			<code>shells/default.php</code> &mdash; one wrapping folder is allowed and stripped, which is
			what <code>git archive</code> and GitHub's "Download ZIP" produce.
		</p>
		<p class="is-muted">
			Bumping <code>version</code> above the installed one turns the button into
			<strong>Update</strong> on every site using the catalogue.
		</p>
	</div>
</section>
