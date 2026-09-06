<?php

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

$repoConfig = plugin_repository_config();
$catalogue  = plugin_repository_list(isset($_GET['refresh']));
$remote     = $catalogue['plugins'];
$repoError  = (string)($catalogue['error'] ?? '');
$installed  = znote_plugins(true);
?>

<div class="acp-toolbar">
	<div>
		<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('plugins')) ?>">
			<i class="fa fa-arrow-left"></i> <?= t('acp.plgbr.back') ?>
		</a>
	</div>
	<div class="acp-actions is-tight">
		<span class="is-muted"><?= t('acp.plgbr.available', ['n' => count($remote)]) ?></span>
		<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('plugins', ['tab' => 'browse', 'refresh' => 1])) ?>">
			<i class="fa fa-refresh"></i> <?= t('acp.plgbr.refresh') ?>
		</a>
	</div>
</div>

<?php if (!$repoConfig['enabled']): ?>

	<section class="acp-card">
		<div class="acp-card-body">
			<?php acp_empty(t('acp.plgbr.repo_off'), 'fa-plug'); ?>
			<p class="is-muted" style="text-align:center;">
				<?= t('acp.plgbr.repo_off_hint', ['code' => '<code>$config[\'plugin_repository\'][\'enabled\'] = true;</code>']) ?>
			</p>
		</div>
	</section>

<?php elseif ($repoError !== ''): ?>

	<div class="acp-flash acp-flash--error">
		<i class="fa fa-exclamation-triangle"></i>
		<span>
			<strong><?= t('acp.plgbr.catalogue_error') ?></strong><br>
			<?= h($repoError) ?><br>
			<span class="is-muted"><?= t('acp.plgbr.catalogue_url') ?> <code><?= h($repoConfig['index']) ?></code></span>
		</span>
	</div>

<?php elseif (!$remote): ?>

	<section class="acp-card">
		<div class="acp-card-body">
			<?php acp_empty(t('acp.plgbr.catalogue_empty'), 'fa-inbox'); ?>
		</div>
	</section>

<?php else: ?>

	<div class="acp-flash acp-flash--info">
		<i class="fa fa-info-circle"></i>
		<span>
			<?= t('acp.plgbr.warning', [
				'hosts'     => '<code>' . h(implode('</code>, <code>', $repoConfig['allowed_hosts'])) . '</code>',
				'configphp' => '<code>config.php</code>',
			]) ?>
		</span>
	</div>

	<div class="acp-media">
		<?php foreach ($remote as $key => $item):
			$isInstalled = isset($installed[$key]);
			$localVer    = $isInstalled ? (string)$installed[$key]['version'] : '';
			$isUpdate    = ($isInstalled && $item['version'] !== '' && $localVer !== '' && version_compare($item['version'], $localVer, '>'));
		?>
			<article class="acp-media-item<?= $isInstalled && !$isUpdate ? ' is-dimmed' : '' ?>">

				<?php if ($item['screenshot'] !== ''): ?>
					<img src="<?= h($item['screenshot']) ?>" alt="<?= h($item['name']) ?>" loading="lazy" referrerpolicy="no-referrer">
				<?php else: ?>
					<div style="display:grid;place-items:center;height:170px;background:var(--acp-panel-2);color:var(--acp-fg-muted);">
						<span><i class="fa fa-plug"></i> &nbsp;<?= t('acp.plgbr.no_screenshot') ?></span>
					</div>
				<?php endif; ?>

				<div class="acp-media-body">
					<h3>
						<?= h($item['name']) ?>
						<?php if ($isUpdate): ?>
							<span class="acp-pill acp-pill--amber"><?= t('acp.plgbr.update_available') ?></span>
						<?php elseif ($isInstalled): ?>
							<span class="acp-pill acp-pill--grey"><?= t('acp.plgbr.installed_pill') ?></span>
						<?php endif; ?>
					</h3>

					<p><?= h($item['description']) ?></p>

					<?php if ($isUpdate && (string)($item['changelog'] ?? '') !== ''): ?>
						<div style="margin:10px 0;padding:10px 12px;border:1px solid var(--acp-border);border-left:3px solid var(--acp-amber);border-radius:8px;background:var(--acp-panel-2);font-size:12px;">
							<strong style="display:block;margin-bottom:5px;">
								<i class="fa fa-list-ul"></i>
								<?= t('acp.plgbr.whats_new', ['version' => h($item['version'])]) ?>
							</strong>
							<div class="is-muted" style="white-space:pre-line;line-height:1.45;"><?= h($item['changelog']) ?></div>
						</div>
					<?php endif; ?>

					<p class="is-muted" style="font-size:12px;">
						<code>plugins/<?= h($key) ?>/</code>
						<?php if ($item['author'] !== ''): ?>&middot; <?= t('acp.plgbr.by_author', ['author' => h($item['author'])]) ?><?php endif; ?>
						<?php if ($item['version'] !== ''): ?>
							&middot; v<?= h($item['version']) ?>
							<?php if ($isUpdate): ?>
								<span class="acp-pill acp-pill--amber"><?= t('acp.plgbr.you_have', ['version' => h($localVer)]) ?></span>
							<?php endif; ?>
						<?php endif; ?>
					</p>

					<?php if (!$item['installable']): ?>
						<p style="font-size:12px;color:var(--acp-red);"><?= t('acp.plgbr.not_installable') ?></p>
					<?php endif; ?>
				</div>

				<div class="acp-media-foot">
					<?php if (!$item['installable']): ?>

						<?php if ($item['url'] !== ''): ?>
							<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h($item['url']) ?>" target="_blank" rel="noopener">
								<i class="fa fa-external-link"></i> <?= t('acp.plgbr.open_page') ?>
							</a>
						<?php endif; ?>

					<?php elseif ($isInstalled && !$isUpdate): ?>

						<span class="acp-btn acp-btn--ghost acp-btn--sm is-disabled">
							<i class="fa fa-check"></i> <?= t('acp.plgbr.installed_badge') ?>
						</span>
						<form class="acp-inline-form" method="post"
							  data-confirm="<?= h(t('acp.plgbr.confirm_reinstall', ['name' => $item['name'], 'key' => $key])) ?>">
							<?= acp_csrf_field() ?>
							<input type="hidden" name="repo_install" value="<?= h($key) ?>">
							<input type="hidden" name="overwrite" value="1">
							<button class="acp-btn acp-btn--ghost acp-btn--sm" type="submit">
								<i class="fa fa-refresh"></i> <?= t('acp.plgbr.reinstall') ?>
							</button>
						</form>

					<?php else: ?>

						<form class="acp-inline-form" method="post"
							  data-confirm="<?= $isInstalled
								  ? h(t('acp.plgbr.confirm_update', ['name' => $item['name'], 'version' => $item['version'], 'key' => $key]))
								  : h(t('acp.plgbr.confirm_install', ['name' => $item['name']])) ?>">
							<?= acp_csrf_field() ?>
							<input type="hidden" name="repo_install" value="<?= h($key) ?>">
							<?php if ($isInstalled): ?><input type="hidden" name="overwrite" value="1"><?php endif; ?>
							<button class="acp-btn acp-btn--sm" type="submit">
								<i class="fa fa-<?= $isInstalled ? 'arrow-up' : 'download' ?>"></i>
								<?= $isInstalled ? t('acp.plgbr.update_btn') : t('acp.plgbr.install_btn') ?>
							</button>
						</form>

					<?php endif; ?>

					<?php if ($item['url'] !== '' && $item['installable']): ?>
						<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h($item['url']) ?>" target="_blank" rel="noopener">
							<i class="fa fa-external-link"></i> <?= t('acp.plgbr.details') ?>
						</a>
					<?php endif; ?>
				</div>
			</article>
		<?php endforeach; ?>
	</div>

<?php endif; ?>
