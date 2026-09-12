<?php
/**
 * Title: Bug Reports
 * Icon: fa-bug
 * Group: Support
 * Order: 10
 * Description: Triage in-game reports, reward reporters and publish changelogs.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

$statusTypes = [
	0 => t('acp.rep.status_reported'),
	1 => t('acp.rep.status_todo'),
	2 => t('acp.rep.status_confirmed'),
	3 => t('acp.rep.status_invalid'),
	4 => t('acp.rep.status_rejected'),
	5 => t('acp.rep.status_fixed'),
];

$statusTone = [
	0 => 'purple',
	1 => 'blue',
	2 => 'red',
	3 => 'grey',
	4 => 'grey',
	5 => 'green',
];

// Statuses that may carry a public changelog entry.
$statusChangeLog = [0, 5];

// Statuses whose section starts collapsed.
$collapsedStatus = [3, 4, 5];

// ---------------------------------------------------------------------------
// Update a report
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$playerName = trim((string)($_POST['playerName'] ?? ''));
	$status     = intv($_POST['status'] ?? 0);
	$reportId   = intv($_POST['id'] ?? 0);
	$price      = intv($_POST['price'] ?? 0) + intv($_POST['customPoints'] ?? 0);
	if ($price > 0 && !is_admin($user_data ?? null)) {
		$price = 0;
		acp_log('access.action_denied', 'reports.reward', ['roles' => admin_roles($user_data ?? null)]);
		acp_flash_error('Only an owner can grant reward points. The report status was still processed.');
	}

	if ($reportId <= 0 || !isset($statusTypes[$status])) {
		acp_flash_error(t('acp.rep.invalid'));
		acp_redirect('reports');
	}

	db()->execute("
		UPDATE `znote_player_reports`
		SET `status` = ?
		WHERE `id` = ?
		LIMIT 1;
	", [$status, $reportId]);
	acp_log('reports.status', '#' . $reportId, ['status' => $statusTypes[$status]]);
	acp_flash_success(t('acp.rep.set_to', ['id' => $reportId, 'status' => '<strong>' . h($statusTypes[$status]) . '</strong>']));

	// ------------------------------------------------------ Changelog entry
	$changelogReportId = intv($_POST['changelogReportId'] ?? 0);
	$changelogValue    = (string)($_POST['changelogValue'] ?? '1');
	$changelogText     = trim((string)($_POST['changelogText'] ?? ''));

	if ($changelogReportId > 0 && $changelogValue === '2' && $changelogText !== '') {
		$now = time();

		$existing = db()->fetchOne("
			SELECT `id` FROM `znote_changelog`
			WHERE `report_id` = ?
			LIMIT 1;
		", [$changelogReportId]);

		if (is_array($existing)) {
			db()->execute("
				UPDATE `znote_changelog`
				SET `text` = ?, `time` = ?
				WHERE `id` = ?
				LIMIT 1;
			", [$changelogText, $now, (int)$existing['id']]);
			acp_log('reports.changelog_update', '#' . $changelogReportId, ['changelog_id' => (int)$existing['id']]);
			acp_flash_info(t('acp.rep.changelog_updated'));
		} else {
			db()->execute("
				INSERT INTO `znote_changelog` (`text`, `time`, `report_id`, `status`)
				VALUES (?, ?, ?, ?);
			", [$changelogText, $now, $changelogReportId, $status]);
			acp_log('reports.changelog_create', '#' . $changelogReportId);
			acp_flash_info(t('acp.rep.changelog_created'));
		}

		$cache = new Cache('engine/cache/changelog');
		$cache->setContent(db()->fetchAll("
			SELECT `id`, `text`, `time`, `report_id`, `status`
			FROM `znote_changelog`
			ORDER BY `id` DESC;
		") ?: []);
		$cache->save();
	}

	// ------------------------------------------------------- Reward points
	if ($price > 0 && $playerName !== '') {
		$account = db()->fetchOne("
			SELECT `a`.`id`, `a`.`email`
			FROM `accounts` `a`
			INNER JOIN `players` `p` ON `p`.`account_id` = `a`.`id`
			WHERE `p`.`name` = ?
			LIMIT 1;
		", [$playerName]);

		if (is_array($account)) {
			$accountId = (int)$account['id'];

			$rewarded = db()->transaction(function ($db) use ($reportId, $accountId, $price, $account, $user_data) {
				$balance = $db->fetchOne(
					"SELECT `points` FROM `znote_accounts` WHERE `account_id` = ? LIMIT 1 FOR UPDATE;",
					[$accountId]
				);
				if (!is_array($balance)) {
					return false;
				}

				$db->execute(
					"INSERT INTO `znote_paypal` VALUES ('', ?, ?, ?, 0, ?);",
					[
						$reportId,
						'report@admin' . (string)($user_data['name'] ?? '') . ' to ' . (string)$account['email'],
						$accountId,
						$price,
					]
				);

				$newPoints = ((int)$balance['points']) + $price;
				$db->execute(
					"UPDATE `znote_accounts` SET `points` = ? WHERE `account_id` = ?;",
					[$newPoints, $accountId]
				);

				return true;
			});

			if ($rewarded) {
				acp_log('reports.reward', $playerName, ['points' => $price, 'report_id' => $reportId]);
				acp_flash_success(t('acp.rep.points_received', ['name' => h($playerName), 'price' => (int)$price]));
			} else {
				acp_flash_error(t('acp.rep.no_account_row'));
			}
		} else {
			acp_flash_error(t('acp.rep.no_account_found', ['name' => '<strong>' . h($playerName) . '</strong>']));
		}
	}

	acp_redirect('reports');
}

// ---------------------------------------------------------------------------
// Load and group
// ---------------------------------------------------------------------------
$rows = db()->fetchAll("
	SELECT `id`, `name`, `posx`, `posy`, `posz`, `report_description`, `date`, `status`
	FROM `znote_player_reports`
	ORDER BY `id` DESC;
");

$reports = [];
$total   = 0;
if (is_array($rows)) {
	foreach ($rows as $r) {
		$reports[(int)$r['status']][(int)$r['id']] = $r;
		$total++;
	}
}
ksort($reports);

// Report being edited
$editing = null;
if (($_GET['action'] ?? '') === 'edit') {
	$editId = intv($_GET['id'] ?? 0);
	foreach ($reports as $group) {
		if (isset($group[$editId])) {
			$editing = $group[$editId];
			break;
		}
	}
	if ($editing === null) {
		acp_flash_error(t('acp.rep.not_found'));
		acp_redirect('reports');
	}
}
?>

<?php if ($editing !== null): ?>

	<div class="acp-toolbar">
		<div>
			<strong><?= t('acp.rep.report_hash', ['id' => (int)$editing['id']]) ?></strong>
			<span class="acp-pill acp-pill--<?= h($statusTone[(int)$editing['status']] ?? 'grey') ?>">
				<?= h($statusTypes[(int)$editing['status']] ?? t('acp.rep.status_unknown')) ?>
			</span>
		</div>
		<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('reports')) ?>">
			<i class="fa fa-arrow-left"></i> <?= t('acp.rep.back_all') ?>
		</a>
	</div>

	<div class="acp-grid acp-grid--2">
		<section class="acp-card">
			<header class="acp-card-head"><h2><?= t('acp.rep.the_report') ?></h2></header>
			<div class="acp-card-body">
				<dl class="acp-dl">
					<dt><?= t('acp.rep.reporter') ?></dt>
					<dd>
						<a href="<?= h(acp_site('characterprofile.php?name=' . urlencode((string)$editing['name']))) ?>" target="_blank" rel="noopener">
							<?= h((string)$editing['name']) ?>
						</a>
					</dd>
					<dt><?= t('acp.rep.position') ?></dt>
					<dd><code>/pos <?= (int)$editing['posx'] ?>, <?= (int)$editing['posy'] ?>, <?= (int)$editing['posz'] ?></code></dd>
					<dt><?= t('acp.rep.reported') ?></dt>
					<dd><?= h(getClock((int)$editing['date'], true, true)) ?></dd>
				</dl>
				<hr>
				<p><?= nl2br(h((string)$editing['report_description'])) ?></p>
			</div>
		</section>

		<section class="acp-card">
			<header class="acp-card-head"><h2><?= t('acp.rep.resolve') ?></h2></header>
			<div class="acp-card-body">
				<form method="post">
					<?= acp_csrf_field() ?>
					<input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
					<input type="hidden" name="playerName" value="<?= h((string)$editing['name']) ?>">

					<div class="acp-field">
						<label class="acp-label" for="status"><?= t('acp.rep.status') ?></label>
						<select class="acp-select" id="status" name="status">
							<?php foreach ($statusTypes as $sid => $label): ?>
								<option value="<?= (int)$sid ?>" <?= (int)$sid === (int)$editing['status'] ? 'selected' : '' ?>>
									<?= h($label) ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="acp-row">
						<div class="acp-field">
							<label class="acp-label" for="price"><?= t('acp.rep.reward_preset') ?></label>
							<select class="acp-select" id="price" name="price">
								<option value="0"><?= t('acp.rep.no_points') ?></option>
								<?php foreach (($config['paypal_prices'] ?? []) as $p): ?>
									<option value="<?= (int)$p ?>"><?= t('acp.rep.n_points', ['n' => (int)$p]) ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="acp-field">
							<label class="acp-label" for="customPoints"><?= t('acp.rep.extra_points') ?></label>
							<input class="acp-input" id="customPoints" name="customPoints" type="number" value="0">
						</div>
					</div>

					<?php if (in_array((int)$editing['status'], $statusChangeLog, true)): ?>
						<hr>
						<input type="hidden" name="changelogReportId" value="<?= (int)$editing['id'] ?>">
						<div class="acp-field">
							<label class="acp-label" for="changelogValue"><?= t('acp.rep.publish_changelog') ?></label>
							<select class="acp-select" id="changelogValue" name="changelogValue">
								<option value="1"><?= t('acp.rep.no') ?></option>
								<option value="2"><?= t('acp.rep.yes') ?></option>
							</select>
						</div>
						<div class="acp-field">
							<label class="acp-label" for="changelogText"><?= t('acp.rep.changelog_text') ?></label>
							<textarea class="acp-textarea" id="changelogText" name="changelogText" rows="5"></textarea>
							<p class="acp-hint"><?= t('acp.rep.changelog_hint') ?></p>
						</div>
					<?php endif; ?>

					<div class="acp-actions">
						<button class="acp-btn acp-btn--green" type="submit"><i class="fa fa-check"></i> <?= t('acp.rep.update_report') ?></button>
					</div>
				</form>
			</div>
		</section>
	</div>

<?php elseif ($total === 0): ?>

	<section class="acp-card">
		<div class="acp-card-body">
			<?php acp_empty(t('acp.rep.empty'), 'fa-bug'); ?>
		</div>
	</section>

<?php else: ?>

	<?php foreach ($reports as $statusId => $group): ?>
		<section class="acp-card">
			<details <?= in_array((int)$statusId, $collapsedStatus, true) ? '' : 'open' ?>>
				<summary class="acp-card-head" style="cursor:pointer;">
					<h2>
						<span class="acp-pill acp-pill--<?= h($statusTone[$statusId] ?? 'grey') ?>">
							<?= h($statusTypes[$statusId] ?? t('acp.rep.status_unknown')) ?>
						</span>
					</h2>
					<p><?= t('acp.rep.n_reports', ['n' => count($group)]) ?></p>
				</summary>
				<div class="acp-card-body is-flush">
					<div class="acp-table-wrap">
						<table class="acp-table">
							<thead>
								<tr>
									<th>#</th>
									<th><?= t('acp.rep.col_reporter') ?></th>
									<th><?= t('acp.rep.col_position') ?></th>
									<th><?= t('acp.rep.col_reported') ?></th>
									<th><?= t('acp.rep.col_description') ?></th>
									<th class="is-num">&nbsp;</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($group as $r): ?>
									<tr>
										<td class="is-muted"><?= (int)$r['id'] ?></td>
										<td class="is-nowrap">
											<a href="<?= h(acp_site('characterprofile.php?name=' . urlencode((string)$r['name']))) ?>" target="_blank" rel="noopener">
												<?= h((string)$r['name']) ?>
											</a>
										</td>
										<td class="is-nowrap"><code><?= (int)$r['posx'] ?>,<?= (int)$r['posy'] ?>,<?= (int)$r['posz'] ?></code></td>
										<td class="is-nowrap is-muted"><?= h(getClock((int)$r['date'], true, true)) ?></td>
										<td><?= h((string)$r['report_description']) ?></td>
										<td class="is-num is-nowrap">
											<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('reports', ['action' => 'edit', 'id' => (int)$r['id']])) ?>">
												<i class="fa fa-pencil"></i> <?= t('acp.rep.handle') ?>
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</details>
		</section>
	<?php endforeach; ?>

<?php endif; ?>
