<?php
/**
 * Title: Accounts
 * Icon: fa-address-card-o
 * Group: Players
 * Order: 10
 * Description: Search an account, see its characters, points and history.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

$isOthire   = (znote_server_adapter()->accountIdentityColumn() === 'id');
$accNameCol = znote_server_adapter()->accountDisplayColumn();

$search    = trim((string)($_GET['q'] ?? ''));
$accountId = intv($_GET['id'] ?? 0);

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$id = intv($_POST['id'] ?? 0);
	$do = (string)($_POST['do'] ?? '');

	if ($id <= 0) {
		acp_flash_error(t('acp.acc.no_selected'));
		acp_redirect('accounts');
	}

	if ($do === 'points') {
		$delta = intv($_POST['points'] ?? 0);

		$row = db()->fetchOne("SELECT `points` FROM `znote_accounts` WHERE `account_id` = ? LIMIT 1;", [$id]);
		if (!is_array($row)) {
			acp_flash_error(t('acp.acc.no_row'));
		} else {
			$new = max(0, (int)$row['points'] + $delta);
			db()->execute("UPDATE `znote_accounts` SET `points` = ? WHERE `account_id` = ?;", [$new, $id]);
			acp_log('account.points', '#' . $id, ['delta' => $delta, 'new_balance' => $new]);
			acp_flash_success(t('acp.acc.points_applied', ['delta' => ($delta >= 0 ? '+' : '') . $delta, 'new' => $new]));
		}
	}

	acp_redirect('accounts', array('id' => $id));
}

// ------------------------------------------------------ Bulk (selected rows)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['do'] ?? '') === 'bulk_points') {
	$ids = array_values(array_unique(array_filter(array_map('intv', (array)($_POST['ids'] ?? [])))));
	$delta = intv($_POST['bulk_points'] ?? 0);

	if (!$ids) {
		acp_flash_error(t_default('acp.acc.bulk_none_selected', 'Select at least one account first.'));
		acp_redirect('accounts', array('q' => $search));
	}
	if ($delta === 0) {
		acp_flash_error(t('acp.mpts.zero_value'));
		acp_redirect('accounts', array('q' => $search));
	}

	$placeholders = implode(',', array_fill(0, count($ids), '?'));
	$updated = db()->execute("
		UPDATE `znote_accounts` SET `points` = GREATEST(0, `points` + ?) WHERE `account_id` IN ({$placeholders});
	", array_merge([$delta], $ids));

	if ($updated !== false) {
		acp_log('accounts.bulk_points', implode(',', $ids), ['delta' => $delta, 'count' => count($ids)]);
		acp_flash_success(t_default('acp.acc.bulk_points_done', '{delta} points applied to {n} account(s).', ['delta' => ($delta >= 0 ? '+' : '') . $delta, 'n' => count($ids)]));
	} else {
		acp_flash_error(t('acp.mpts.failed'));
	}

	acp_redirect('accounts', array('q' => $search));
}

// ---------------------------------------------------------------------------
// One account
// ---------------------------------------------------------------------------
$account = null;
if ($accountId > 0) {
	$account = db()->fetchOne("
		SELECT `a`.`id`, {$accNameCol} AS `account_name`, `a`.`email`,
		       `za`.`points`, `za`.`created`, `za`.`ip`, `za`.`flag`, `za`.`active_email`
		FROM `accounts` `a`
		LEFT JOIN `znote_accounts` `za` ON `za`.`account_id` = `a`.`id`
		WHERE `a`.`id` = ?
		LIMIT 1;
	", [$accountId]);

	if (!is_array($account)) {
		acp_flash_error(t('acp.acc.no_account', ['id' => $accountId]));
		acp_redirect('accounts');
	}

	$characters = db()->fetchAll("
		SELECT `id`, `name`, `level`, `vocation`, `group_id`
		FROM `players`
		WHERE `account_id` = ?
		ORDER BY `level` DESC;
	", [$accountId]);
	$characters = is_array($characters) ? $characters : array();

	$purchases = db()->fetchAll("
		SELECT `type`, `itemid`, `count`, `points`, `time`
		FROM `znote_shop_logs`
		WHERE `account_id` = ?
		ORDER BY `id` DESC
		LIMIT 10;
	", [$accountId]);
	$purchases = is_array($purchases) ? $purchases : array();
}

// ---------------------------------------------------------------------------
// Search / listing
// ---------------------------------------------------------------------------
const ACP_ACCOUNTS_PER_PAGE = 50;

$results = array();
if ($account === null) {
	$where = '';
	$params = [];
	if ($search !== '') {
		$like = '%' . $search . '%';
		// Match the account name, its e-mail, or a character on it.
		if ($isOthire) {
			$where = "WHERE `a`.`id` = ? OR `a`.`email` LIKE ? OR `a`.`id` IN (SELECT `account_id` FROM `players` WHERE `name` LIKE ?)";
			$params = [(int)$search, $like, $like];
		} else {
			$where = "WHERE `a`.`name` LIKE ? OR `a`.`email` LIKE ? OR `a`.`id` IN (SELECT `account_id` FROM `players` WHERE `name` LIKE ?)";
			$params = [$like, $like, $like];
		}
	}

	$totalRow  = db()->fetchOne("SELECT COUNT(*) AS `n` FROM `accounts` `a` {$where};", $params);
	$total     = is_array($totalRow) ? (int)$totalRow['n'] : 0;
	$pageCount = max(1, (int)ceil($total / ACP_ACCOUNTS_PER_PAGE));
	$page      = max(1, min($pageCount, intv($_GET['ap'] ?? 1)));
	$offset    = ($page - 1) * ACP_ACCOUNTS_PER_PAGE;

	$results = db()->fetchAll("
		SELECT `a`.`id`, {$accNameCol} AS `account_name`, `a`.`email`,
		       `za`.`points`, `za`.`created`,
		       (SELECT COUNT(*) FROM `players` `p` WHERE `p`.`account_id` = `a`.`id`) AS `characters`
		FROM `accounts` `a`
		LEFT JOIN `znote_accounts` `za` ON `za`.`account_id` = `a`.`id`
		{$where}
		ORDER BY `a`.`id` DESC
		LIMIT {$offset}, " . ACP_ACCOUNTS_PER_PAGE . ";
	", $params);
	$results = is_array($results) ? $results : array();
}

/** Keep the current search when building a paging link. */
function acp_accounts_page_url(int $page, string $search): string {
	$params = ['ap' => $page];
	if ($search !== '') {
		$params['q'] = $search;
	}
	return acp_url('accounts', $params);
}
?>

<?php if ($account !== null): ?>

	<div class="acp-toolbar">
		<div>
			<strong><?= h((string)$account['account_name']) ?></strong>
			<span class="acp-pill acp-pill--grey">#<?= (int)$account['id'] ?></span>
		</div>
		<div class="acp-actions is-tight">
			<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('adminlog', array('target' => '#' . (int)$account['id']))) ?>">
				<i class="fa fa-history"></i> <?= t_default('acp.acc.view_history', 'Admin log for this account') ?>
			</a>
			<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('accounts')) ?>">
				<i class="fa fa-arrow-left"></i> <?= t('acp.acc.all_accounts') ?>
			</a>
		</div>
	</div>

	<div class="acp-grid acp-grid--2">

		<section class="acp-card">
			<header class="acp-card-head"><h2><?= t('acp.acc.account_title') ?></h2></header>
			<div class="acp-card-body">
				<dl class="acp-dl">
					<dt><?= t('acp.acc.name') ?></dt><dd><?= h((string)$account['account_name']) ?></dd>
					<dt><?= t('acp.acc.email') ?></dt>
					<dd>
						<?= h((string)$account['email']) ?>
						<?php if (!empty($account['active_email'])): ?>
							<span class="acp-pill acp-pill--green"><?= t('acp.acc.verified') ?></span>
						<?php else: ?>
							<span class="acp-pill acp-pill--grey"><?= t('acp.acc.unverified') ?></span>
						<?php endif; ?>
					</dd>
					<dt><?= t('acp.acc.registered') ?></dt>
					<dd><?= !empty($account['created']) ? h(getClock((int)$account['created'], true)) : '&mdash;' ?></dd>
					<dt><?= t('acp.acc.last_ip') ?></dt>
					<dd><?= !empty($account['ip']) ? '<code>' . h(long2ip((int)$account['ip'])) . '</code>' : '&mdash;' ?></dd>
					<dt><?= t('acp.acc.country') ?></dt>
					<dd><?= !empty($account['flag']) ? h((string)$account['flag']) : '&mdash;' ?></dd>
					<dt><?= t('acp.acc.shop_points') ?></dt>
					<dd><strong><?= number_format((int)($account['points'] ?? 0)) ?></strong></dd>
				</dl>
			</div>
		</section>

		<section class="acp-card">
			<header class="acp-card-head">
				<h2><?= t('acp.acc.adjust_points') ?></h2>
				<p><?= t('acp.acc.adjust_sub') ?></p>
			</header>
			<div class="acp-card-body">
				<form method="post">
					<?= acp_csrf_field() ?>
					<input type="hidden" name="do" value="points">
					<input type="hidden" name="id" value="<?= (int)$account['id'] ?>">
					<div class="acp-field">
						<label class="acp-label" for="points"><?= t('acp.acc.amount') ?></label>
						<input class="acp-input" id="points" name="points" type="number" value="0" required>
						<p class="acp-hint"><?= t('acp.acc.balance_hint') ?></p>
					</div>
					<div class="acp-actions">
						<button class="acp-btn acp-btn--green" type="submit"><i class="fa fa-diamond"></i> <?= t('acp.acc.apply') ?></button>
					</div>
				</form>

				<hr>
				<p class="is-muted" style="font-size:12.5px;">
					<?= t('acp.acc.other_tools', [
						'link' => '<a href="' . h(acp_url('players')) . '">' . t('acp.acc.player_tools_link') . '</a>',
					]) ?>
				</p>
			</div>
		</section>
	</div>

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= t('acp.acc.characters') ?></h2>
			<p><?= t('acp.acc.n_on_account', ['n' => count($characters)]) ?></p>
		</header>
		<div class="acp-card-body is-flush">
			<?php if ($characters): ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<thead><tr><th><?= t('acp.acc.col_name') ?></th><th><?= t('acp.acc.col_vocation') ?></th><th class="is-num"><?= t('acp.acc.col_level') ?></th><th><?= t('acp.acc.col_group') ?></th><th class="is-num">&nbsp;</th></tr></thead>
						<tbody>
							<?php foreach ($characters as $char): ?>
								<tr>
									<td>
										<a href="<?= h(acp_site('characterprofile.php?name=' . urlencode((string)$char['name']))) ?>" target="_blank" rel="noopener">
											<?= h((string)$char['name']) ?>
										</a>
									</td>
									<td class="is-muted"><?= h(vocation_id_to_name((int)$char['vocation'])) ?></td>
									<td class="is-num"><?= (int)$char['level'] ?></td>
									<td>
										<?php if ((int)$char['group_id'] > 1): ?>
											<span class="acp-pill acp-pill--red"><?= t('acp.acc.staff') ?></span>
										<?php else: ?>
											<span class="is-muted"><?= t('acp.acc.player') ?></span>
										<?php endif; ?>
									</td>
									<td class="is-num is-nowrap">
										<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('skills', array('name' => (string)$char['name']))) ?>">
											<i class="fa fa-bolt"></i> <?= t('acp.acc.skills') ?>
										</a>
										<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('adminlog', array('target' => (string)$char['name']))) ?>" title="<?= h(t_default('acp.acc.view_history', 'Admin log for this account')) ?>">
											<i class="fa fa-history"></i>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<?php acp_empty(t('acp.acc.no_characters'), 'fa-user-o'); ?>
			<?php endif; ?>
		</div>
	</section>

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= t('acp.acc.recent_purchases') ?></h2>
			<p><?= t('acp.acc.last_10') ?></p>
		</header>
		<div class="acp-card-body is-flush">
			<?php if ($purchases): ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<thead><tr><th><?= t('acp.acc.col_date') ?></th><th><?= t('acp.acc.col_type') ?></th><th class="is-num"><?= t('acp.acc.col_count') ?></th><th class="is-num"><?= t('acp.acc.col_points') ?></th></tr></thead>
						<tbody>
							<?php
							$types = array(
								1 => t('acp.acc.type_item'), 2 => t('acp.acc.type_premium'), 3 => t('acp.acc.type_gender'),
								4 => t('acp.acc.type_name'), 5 => t('acp.acc.type_outfit'), 6 => t('acp.acc.type_mount'),
								7 => t('acp.acc.type_custom'),
							);
							foreach ($purchases as $buy): ?>
								<tr>
									<td class="is-nowrap is-muted"><?= h(getClock((int)$buy['time'], true)) ?></td>
									<td><?= h($types[(int)$buy['type']] ?? t('acp.acc.type_unknown')) ?></td>
									<td class="is-num"><?= (int)$buy['count'] ?></td>
									<td class="is-num"><?= (int)$buy['points'] ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<?php acp_empty(t('acp.acc.never_bought'), 'fa-shopping-cart'); ?>
			<?php endif; ?>
		</div>
	</section>

<?php else: ?>

	<div class="acp-toolbar">
		<form method="get" style="display:flex;gap:8px;flex:1 1 340px;max-width:520px;">
			<input type="hidden" name="p" value="accounts">
			<input class="acp-input" type="search" name="q" value="<?= h($search) ?>"
				   placeholder="<?= h(t('acp.acc.search_placeholder')) ?>" autofocus>
			<button class="acp-btn" type="submit"><i class="fa fa-search"></i> <?= t('acp.acc.search') ?></button>
			<?php if ($search !== ''): ?>
				<a class="acp-btn acp-btn--ghost" href="<?= h(acp_url('accounts')) ?>"><?= t('acp.acc.clear') ?></a>
			<?php endif; ?>
		</form>
		<span class="is-muted">
			<?= $search !== '' ? t('acp.acc.match_count', ['n' => $total]) : t_default('acp.acc.total_count', '{n} accounts', ['n' => $total]) ?>
			<?php if ($pageCount > 1): ?>
				&middot; <?= t('acp.lay.page_of', ['page' => (int)$page, 'pageCount' => (int)$pageCount]) ?>
			<?php endif; ?>
		</span>
	</div>

	<section class="acp-card">
		<header class="acp-card-head">
			<h2><?= $search !== '' ? t('acp.acc.search_results') : t('acp.acc.newest_accounts') ?></h2>
		</header>
		<div class="acp-card-body is-flush">
			<?php if ($results): ?>
				<form id="acpAccBulkForm" method="post">
					<?= acp_csrf_field() ?>
					<input type="hidden" name="do" value="bulk_points">
					<input type="hidden" name="q" value="<?= h($search) ?>">
				</form>

				<div class="acp-toolbar" id="acpAccBulkBar" hidden>
					<span class="is-muted"><span id="acpAccBulkCount">0</span> <?= h(t_default('acp.acc.bulk_selected', 'selected')) ?></span>
					<div class="acp-actions is-tight">
						<input class="acp-input" form="acpAccBulkForm" name="bulk_points" type="number" step="1"
							   placeholder="<?= h(t_default('acp.acc.bulk_points_placeholder', '+/- points')) ?>" style="width:120px;">
						<button type="submit" form="acpAccBulkForm" class="acp-btn acp-btn--sm">
							<i class="fa fa-diamond"></i> <?= h(t_default('acp.acc.bulk_apply', 'Apply to selected')) ?>
						</button>
					</div>
				</div>

				<div class="acp-table-wrap">
					<table class="acp-table" data-sortable>
						<thead>
							<tr><th><input type="checkbox" id="acpAccBulkAll" aria-label="<?= h(t_default('acp.acc.bulk_select_all', 'Select all')) ?>"></th><th>#</th><th><?= t('acp.acc.col_account') ?></th><th><?= t('acp.acc.col_email') ?></th><th class="is-num"><?= t('acp.acc.col_chars') ?></th><th class="is-num"><?= t('acp.acc.col_points') ?></th><th><?= t('acp.acc.col_registered') ?></th><th class="is-num">&nbsp;</th></tr>
						</thead>
						<tbody>
							<?php foreach ($results as $row): ?>
								<tr>
									<td><input type="checkbox" class="acp-acc-bulk-check" form="acpAccBulkForm" name="ids[]" value="<?= (int)$row['id'] ?>"></td>
									<td class="is-muted"><?= (int)$row['id'] ?></td>
									<td><?= h((string)$row['account_name']) ?></td>
									<td class="is-muted"><?= h((string)$row['email']) ?></td>
									<td class="is-num"><?= (int)$row['characters'] ?></td>
									<td class="is-num"><?= number_format((int)($row['points'] ?? 0)) ?></td>
									<td class="is-nowrap is-muted"><?= !empty($row['created']) ? h(getClock((int)$row['created'], true)) : '&mdash;' ?></td>
									<td class="is-num">
										<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('accounts', array('id' => (int)$row['id']))) ?>">
											<i class="fa fa-eye"></i> <?= t('acp.acc.open') ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<script>
				(function () {
					var all   = document.getElementById('acpAccBulkAll');
					var boxes = Array.prototype.slice.call(document.querySelectorAll('.acp-acc-bulk-check'));
					var bar   = document.getElementById('acpAccBulkBar');
					var count = document.getElementById('acpAccBulkCount');
					if (!all || !bar) return;

					function refresh() {
						var checked = boxes.filter(function (b) { return b.checked; });
						bar.hidden = checked.length === 0;
						if (count) count.textContent = checked.length;
						all.checked = checked.length > 0 && checked.length === boxes.length;
						all.indeterminate = checked.length > 0 && checked.length < boxes.length;
					}

					all.addEventListener('change', function () {
						boxes.forEach(function (b) { b.checked = all.checked; });
						refresh();
					});
					boxes.forEach(function (b) { b.addEventListener('change', refresh); });
				})();
				</script>
			<?php else: ?>
				<?php acp_empty($search !== '' ? t('acp.acc.no_match', ['search' => $search]) : t('acp.acc.no_accounts_yet'), 'fa-address-card-o'); ?>
			<?php endif; ?>
		</div>
	</section>

	<?php if ($pageCount > 1): ?>
		<nav class="acp-actions" style="justify-content:center;margin:18px 0 24px;" aria-label="<?= h(t_default('acp.pagination_label', 'Pages')) ?>">
			<a class="acp-btn acp-btn--ghost acp-btn--sm<?= $page <= 1 ? ' is-disabled' : '' ?>"
			   href="<?= h(acp_accounts_page_url(max(1, $page - 1), $search)) ?>"
			   <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
				<i class="fa fa-angle-left"></i> <?= t('acp.lay.previous') ?>
			</a>

			<?php for ($p = 1; $p <= $pageCount; $p++): ?>
				<?php if ($p === $page): ?>
					<span class="acp-btn acp-btn--sm"><?= $p ?></span>
				<?php else: ?>
					<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_accounts_page_url($p, $search)) ?>"><?= $p ?></a>
				<?php endif; ?>
			<?php endfor; ?>

			<a class="acp-btn acp-btn--ghost acp-btn--sm<?= $page >= $pageCount ? ' is-disabled' : '' ?>"
			   href="<?= h(acp_accounts_page_url(min($pageCount, $page + 1), $search)) ?>"
			   <?= $page >= $pageCount ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
				<?= t('acp.lay.next') ?> <i class="fa fa-angle-right"></i>
			</a>
		</nav>
	<?php endif; ?>

<?php endif; ?>
