<?php
/**
 * Title: News
 * Icon: fa-newspaper-o
 * Group: Content
 * Order: 10
 * Description: Write, edit and remove front-page articles.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

function acp_news_rebuild_cache(): void {
	$cache = new Cache('engine/cache/news');
	$cache->setContent(fetchAllNews() ?: []);
	$cache->save();
}

// ---------------------------------------------------------------------------
// Mutations
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$do = (string)($_POST['do'] ?? '');
	$id = intv($_POST['id'] ?? 0);

	if ($do === 'delete' && $id > 0) {
		mysql_delete("DELETE FROM `znote_news` WHERE `id` = {$id} LIMIT 1;");
		acp_news_rebuild_cache();
		acp_log('news.delete', '#' . $id);
		acp_flash_success(t('acp.news.deleted'));
		acp_redirect('news');
	}

	if ($do === 'create') {
		$charId = intv($_POST['selected_char'] ?? 0);
		$title  = trim((string)($_POST['title'] ?? ''));
		$text   = (string)($_POST['text'] ?? '');

		if ($charId > 0 && $title !== '' && trim($text) !== '') {
			mysql_insert("
				INSERT INTO `znote_news` (`title`, `text`, `date`, `pid`)
				VALUES ('" . esc($title) . "', '" . esc($text) . "', " . time() . ", {$charId});
			");
			acp_news_rebuild_cache();
			acp_log('news.create', $title);
			acp_flash_success(t('acp.news.published'));
			acp_redirect('news');
		}

		acp_flash_error(t('acp.news.fill_fields'));
		acp_redirect('news', ['action' => 'add']);
	}

	if ($do === 'update' && $id > 0) {
		$title = trim((string)($_POST['title'] ?? ''));
		$text  = (string)($_POST['text'] ?? '');

		mysql_update("
			UPDATE `znote_news`
			SET `title` = '" . esc($title) . "', `text` = '" . esc($text) . "'
			WHERE `id` = {$id}
			LIMIT 1;
		");
		acp_news_rebuild_cache();
		acp_log('news.update', $title !== '' ? $title : ('#' . $id));
		acp_flash_success(t('acp.news.updated'));
		acp_redirect('news');
	}
}

// ---------------------------------------------------------------------------
// View state
// ---------------------------------------------------------------------------
$action = (string)($_GET['action'] ?? '');
$editId = intv($_GET['id'] ?? 0);

$news = fetchAllNews();
$news = is_array($news) ? $news : [];

$editing = null;
if ($action === 'edit' && $editId > 0) {
	foreach ($news as $n) {
		if ((int)$n['id'] === $editId) {
			$editing = $n;
			break;
		}
	}
	if ($editing === null) {
		acp_flash_error(t('acp.news.not_found'));
		acp_redirect('news');
	}
}

// Staff characters on the admin's own account can be set as author.
$authors = [];
if ($action === 'add') {
	$charList = user_character_list($user_data['id']);
	if (is_array($charList)) {
		foreach ($charList as $row) {
			$charName = (string)($row['name'] ?? '');
			if ($charName === '') {
				continue;
			}
			$charId = (int)user_character_id($charName);
			$charD  = user_character_data($charId, 'group_id', 'id');
			if (is_array($charD) && (int)($charD['group_id'] ?? 0) > 1) {
				$authors[$charId] = $charName;
			}
		}
	}
}

$bbcodeHelp = [
	'[b]' . t('acp.news.bb.bold') . '[/b]  [i]' . t('acp.news.bb.italic') . '[/i]  [u]' . t('acp.news.bb.underline') . '[/u]  [s]' . t('acp.news.bb.struck') . '[/s]',
	'[size=5]' . t('acp.news.bb.larger') . '[/size]   (1 to 7)',
	'[color=#4da3ff]' . t('acp.news.bb.colored') . '[/color]',
	'[center]' . t('acp.news.bb.centered') . '[/center]  [left]..[/left]  [right]..[/right]',
	'[ul][li]' . t('acp.news.bb.bullet') . '[/li][/ul]',
	'[ol][li]' . t('acp.news.bb.numbered') . '[/li][/ol]',
	'[quote]' . t('acp.news.bb.quoted') . '[/quote]',
	'[quote=Someone]' . t('acp.news.bb.attributed') . '[/quote]',
	'[code]' . t('acp.news.bb.code') . '[/code]',
	'[url=https://example.com]' . t('acp.news.bb.link') . '[/url]',
	'[img]https://example.com/image.png[/img]',
	'[youtube]wK0w0x62PjA[/youtube]',
];
?>

<div class="acp-toolbar">
	<div>
		<?php if ($action === 'add'): ?>
			<strong><?= t('acp.news.new_article') ?></strong>
		<?php elseif ($editing !== null): ?>
			<strong><?= t('acp.news.editing') ?></strong> <?= h((string)$editing['title']) ?>
		<?php else: ?>
			<span class="acp-pill acp-pill--grey"><?= t('acp.news.published_count', ['count' => count($news)]) ?></span>
		<?php endif; ?>
	</div>
	<div class="acp-actions is-tight">
		<?php if ($action !== ''): ?>
			<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('news')) ?>"><i class="fa fa-arrow-left"></i> <?= t('acp.news.back_to_list') ?></a>
		<?php else: ?>
			<a class="acp-btn" href="<?= h(acp_url('news', ['action' => 'add'])) ?>"><i class="fa fa-plus"></i> <?= t('acp.news.create_article') ?></a>
		<?php endif; ?>
	</div>
</div>

<?php if ($action === 'add' || $editing !== null): ?>
	<div class="acp-grid acp-grid--2">
		<section class="acp-card">
			<header class="acp-card-head">
				<h2><?= $editing !== null ? t('acp.news.edit_article') : t('acp.news.new_article') ?></h2>
			</header>
			<div class="acp-card-body">

				<?php if ($action === 'add' && !$authors): ?>
					<div class="acp-flash acp-flash--error">
						<i class="fa fa-exclamation-triangle"></i>
						<span>
							<?= t('acp.news.no_author', ['code' => '<code>group_id &gt; 1</code>']) ?>
						</span>
					</div>
				<?php endif; ?>

				<form method="post">
					<?= acp_csrf_field() ?>
					<input type="hidden" name="do" value="<?= $editing !== null ? 'update' : 'create' ?>">
					<?php if ($editing !== null): ?>
						<input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
					<?php endif; ?>

					<?php if ($action === 'add'): ?>
						<div class="acp-field">
							<label class="acp-label" for="selected_char"><?= t('acp.news.publish_as') ?></label>
							<select class="acp-select" id="selected_char" name="selected_char" <?= $authors ? '' : 'disabled' ?>>
								<?php foreach ($authors as $charId => $charName): ?>
									<option value="<?= (int)$charId ?>"><?= h($charName) ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>

					<div class="acp-field">
						<label class="acp-label" for="title"><?= t('acp.news.title') ?></label>
						<input class="acp-input" id="title" name="title" value="<?= $editing !== null ? h((string)$editing['title']) : '' ?>" required>
					</div>

					<div class="acp-field">
						<label class="acp-label" for="text"><?= t('acp.news.body') ?></label>
						<?php acp_editor('text', $editing !== null ? (string)$editing['text'] : '', ['height' => 340]); ?>
					</div>

					<div class="acp-actions">
						<button class="acp-btn acp-btn--green" type="submit" <?= ($action === 'add' && !$authors) ? 'disabled' : '' ?>>
							<i class="fa fa-check"></i> <?= $editing !== null ? t('acp.news.save_changes') : t('acp.news.publish_article') ?>
						</button>
						<a class="acp-btn acp-btn--ghost" href="<?= h(acp_url('news')) ?>"><?= t('acp.news.cancel') ?></a>
					</div>
				</form>
			</div>
		</section>

		<section class="acp-card">
			<header class="acp-card-head">
				<h2><?= t('acp.news.formatting') ?></h2>
				<p><?= t('acp.news.formatting_sub') ?></p>
			</header>
			<div class="acp-card-body">
				<pre class="acp-dump"><?= h(implode("\n", $bbcodeHelp)) ?></pre>
			</div>
		</section>
	</div>

<?php else: ?>

	<section class="acp-card">
		<header class="acp-card-head"><h2><?= t('acp.news.published_articles') ?></h2></header>
		<div class="acp-card-body is-flush">
			<?php if ($news): ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<thead>
							<tr>
								<th><?= t('acp.news.col_date') ?></th>
								<th><?= t('acp.news.col_author') ?></th>
								<th><?= t('acp.news.col_title') ?></th>
								<th class="is-num"><?= t('acp.news.col_actions') ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($news as $n):
								$id   = (int)($n['id'] ?? 0);
								$nm   = (string)($n['name'] ?? '');
							?>
								<tr>
									<td class="is-nowrap is-muted"><?= h(getClock((int)($n['date'] ?? 0), true)) ?></td>
									<td>
										<a href="<?= h(acp_site('characterprofile.php?name=' . urlencode($nm))) ?>" target="_blank" rel="noopener"><?= h($nm) ?></a>
									</td>
									<td><?= h((string)($n['title'] ?? '')) ?></td>
									<td class="is-num is-nowrap">
										<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('news', ['action' => 'edit', 'id' => $id])) ?>">
											<i class="fa fa-pencil"></i> <?= t('acp.news.edit') ?>
										</a>
										<form class="acp-inline-form" method="post" data-confirm="<?= h(t('acp.news.confirm_delete')) ?>">
											<?= acp_csrf_field() ?>
											<input type="hidden" name="do" value="delete">
											<input type="hidden" name="id" value="<?= $id ?>">
											<button class="acp-btn acp-btn--red acp-btn--sm" type="submit"><i class="fa fa-trash"></i> <?= t('acp.news.delete') ?></button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<?php acp_empty(t('acp.news.empty'), 'fa-newspaper-o'); ?>
			<?php endif; ?>
		</div>
	</section>

<?php endif; ?>
