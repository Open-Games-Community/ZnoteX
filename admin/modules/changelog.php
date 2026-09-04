<?php
/**
 * Title: Changelog
 * Icon: fa-list-ul
 * Group: Content
 * Order: 20
 * Description: Write and manage the entries shown on the public changelog page.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

/**
 * Rebuild the cache the public page reads.
 * changelog.php serves from engine/cache/changelog and only refreshes it after
 * a write, so every change here has to refresh it too or the site shows stale
 * entries until something else happens to save it.
 */
function acp_changelog_rebuild_cache(): void {
	$cache = new Cache('engine/cache/changelog');
	$cache->useMemory(false);
	$cache->setContent(mysql_select_multi("
		SELECT `id`, `text`, `time`, `report_id`, `status`
		FROM `znote_changelog`
		ORDER BY `id` DESC;
	") ?: []);
	$cache->save();
}

// The public page writes 35 for entries created by hand; reports.php writes the
// report status. Kept as-is so both keep meaning the same thing.
const ACP_CHANGELOG_MANUAL_STATUS = 35;

// ---------------------------------------------------------------------------
// Mutations
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$do = (string)($_POST['do'] ?? '');
	$id = intv($_POST['id'] ?? 0);

	if ($do === 'delete' && $id > 0) {
		mysql_delete("DELETE FROM `znote_changelog` WHERE `id` = {$id} LIMIT 1;");
		acp_changelog_rebuild_cache();
		acp_flash_success('Changelog entry deleted.');
		acp_redirect('changelog');
	}

	$text = trim((string)($_POST['text'] ?? ''));

	if ($text === '') {
		acp_flash_error('The entry cannot be empty.');
		acp_redirect('changelog', $id > 0 ? ['action' => 'edit', 'id' => $id] : []);
	}

	// The column is varchar(255). Cutting is no longer safe now that the text can
	// carry BBCode - substr() would happily slice [b]word[/b] into [b]word[/ and
	// leave the tag dangling on the public page. Refuse instead and say why.
	if (strlen($text) > 254) {
		acp_flash_error('Too long: ' . strlen($text) . ' of 254 characters. BBCode tags count toward the limit.');
		acp_redirect('changelog', $id > 0 ? ['action' => 'edit', 'id' => $id] : []);
	}

	if ($do === 'update' && $id > 0) {
		mysql_update("
			UPDATE `znote_changelog`
			SET `text` = '" . esc($text) . "'
			WHERE `id` = {$id}
			LIMIT 1;
		");
		acp_changelog_rebuild_cache();
		acp_flash_success('Changelog entry updated.');
		acp_redirect('changelog');
	}

	if ($do === 'create') {
		$when = intv($_POST['time'] ?? 0);
		if ($when <= 0) {
			$when = time();
		}

		mysql_insert("
			INSERT INTO `znote_changelog` (`text`, `time`, `report_id`, `status`)
			VALUES ('" . esc($text) . "', {$when}, 0, " . ACP_CHANGELOG_MANUAL_STATUS . ");
		");
		acp_changelog_rebuild_cache();
		acp_flash_success('Changelog entry published.');
		acp_redirect('changelog');
	}

	acp_flash_error('Unknown action.');
	acp_redirect('changelog');
}

// ---------------------------------------------------------------------------
// View state
// ---------------------------------------------------------------------------
$entries = mysql_select_multi("
	SELECT `id`, `text`, `time`, `report_id`, `status`
	FROM `znote_changelog`
	ORDER BY `id` DESC;
");
$entries = is_array($entries) ? $entries : [];

$editing = null;
if (($_GET['action'] ?? '') === 'edit') {
	$editId = intv($_GET['id'] ?? 0);
	foreach ($entries as $entry) {
		if ((int)$entry['id'] === $editId) {
			$editing = $entry;
			break;
		}
	}
	if ($editing === null) {
		acp_flash_error('That entry no longer exists.');
		acp_redirect('changelog');
	}
}

$fromReports = 0;
foreach ($entries as $entry) {
	if ((int)$entry['report_id'] > 0) {
		$fromReports++;
	}
}
?>

<div class="acp-stats">
	<?php
	acp_stat('Entries', count($entries), 'fa-list-ul', null, 'blue');
	acp_stat('From bug reports', $fromReports, 'fa-bug', acp_url('reports'), 'purple');
	?>
</div>

<div class="acp-grid acp-grid--2">

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= $editing !== null ? 'Edit entry #' . (int)$editing['id'] : 'New entry' ?></h2>
			<p>Appears at the top of the public changelog</p>
		</header>
		<div class="acp-card-body">
			<form method="post">
				<?= acp_csrf_field() ?>
				<input type="hidden" name="do" value="<?= $editing !== null ? 'update' : 'create' ?>">
				<?php if ($editing !== null): ?>
					<input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
				<?php endif; ?>

				<div class="acp-field">
					<label class="acp-label" for="text">Text</label>
					<?php acp_editor('text', $editing !== null ? (string)$editing['text'] : '', [
						'height'    => 150,
						'maxlength' => 254,
						// A changelog line is one sentence in a varchar(255), so the
						// toolbar stays small - lists and images would not fit.
						'toolbar'   => 'bold,italic,underline,strike|color,removeformat|link,unlink|undo,redo,source',
					]); ?>
				</div>

				<div class="acp-actions">
					<button class="acp-btn acp-btn--green" type="submit">
						<i class="fa fa-check"></i> <?= $editing !== null ? 'Save changes' : 'Publish entry' ?>
					</button>
					<?php if ($editing !== null): ?>
						<a class="acp-btn acp-btn--ghost" href="<?= h(acp_url('changelog')) ?>">Cancel</a>
					<?php endif; ?>
				</div>
			</form>
		</div>
	</section>

	<section class="acp-card">
		<header class="acp-card-head">
			<h2>How this works</h2>
		</header>
		<div class="acp-card-body">
			<p>
				Entries created here are stand-alone. Entries created from
				<a href="<?= h(acp_url('reports')) ?>">Bug Reports</a> stay tied to their report and
				are marked below, so you can tell at a glance what came from a player report.
			</p>
			<p>
				The public page reads a cache file. It is refreshed automatically on every change
				made here, so what you save is live immediately.
			</p>
			<p>
				<a href="<?= h(acp_site('changelog.php')) ?>" target="_blank" rel="noopener">
					<i class="fa fa-external-link"></i> View the public changelog
				</a>
			</p>
		</div>
	</section>
</div>

<section class="acp-card">
	<header class="acp-card-head">
		<h2>Published entries</h2>
		<p>Newest first</p>
	</header>
	<div class="acp-card-body is-flush">
		<?php if ($entries): ?>
			<div class="acp-table-wrap">
				<table class="acp-table">
					<thead>
						<tr>
							<th>#</th>
							<th>Date</th>
							<th>Entry</th>
							<th>Source</th>
							<th class="is-num">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($entries as $entry):
							$id       = (int)$entry['id'];
							$reportId = (int)$entry['report_id'];
						?>
							<tr>
								<td class="is-muted"><?= $id ?></td>
								<td class="is-nowrap is-muted"><?= h(getClock((int)$entry['time'], true, true)) ?></td>
								<td><?= h((string)$entry['text']) ?></td>
								<td class="is-nowrap">
									<?php if ($reportId > 0): ?>
										<a class="acp-pill acp-pill--purple" href="<?= h(acp_url('reports', ['action' => 'edit', 'id' => $reportId])) ?>">
											report #<?= $reportId ?>
										</a>
									<?php else: ?>
										<span class="acp-pill acp-pill--grey">manual</span>
									<?php endif; ?>
								</td>
								<td class="is-num is-nowrap">
									<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('changelog', ['action' => 'edit', 'id' => $id])) ?>">
										<i class="fa fa-pencil"></i> Edit
									</a>
									<form class="acp-inline-form" method="post" data-confirm="Delete this changelog entry?">
										<?= acp_csrf_field() ?>
										<input type="hidden" name="do" value="delete">
										<input type="hidden" name="id" value="<?= $id ?>">
										<button class="acp-btn acp-btn--red acp-btn--sm" type="submit"><i class="fa fa-trash"></i> Delete</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<?php acp_empty('No changelog entries yet.', 'fa-list-ul'); ?>
		<?php endif; ?>
	</div>
</section>
