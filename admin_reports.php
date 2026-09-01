<?php
require_once 'engine/init.php';
protect_page();
admin_only($user_data);
include 'layout/overall/header.php';

function h(string $s): string {
	return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function esc(string $s): string {
	return mysql_znote_escape_string($s);
}
function intv($v): int {
	return is_numeric($v) ? (int)$v : 0;
}

$statusTypes = [
	0 => '<span style="color:purple">Reported</span>',
	1 => '<span style="color:darkblue">To-Do List</span>',
	2 => '<span style="color:red">Confirmed bug</span>',
	3 => '<span style="color:grey">Invalid</span>',
	4 => '<span style="color:grey">Rejected</span>',
	5 => '<span style="color:green"><b>Fixed</b></span>',
];

$statusChangeLog = [0, 5];
$hideStatus = [3, 4, 5];

$reportsData = mysql_select_multi("
	SELECT id, name, posx, posy, posz, report_description, date, status
	FROM znote_player_reports
	ORDER BY id DESC
");

$reports = [];
if (is_array($reportsData)) {
	foreach ($reportsData as $r) {
		$sid = (int)$r['status'];
		$rid = (int)$r['id'];
		$reports[$sid][$rid] = $r;
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$playerName = esc((string)($_POST['playerName'] ?? ''));
	$status     = intv($_POST['status'] ?? 0);
	$price      = intv($_POST['price'] ?? 0);
	$custom     = intv($_POST['customPoints'] ?? 0);
	$reportId   = intv($_POST['id'] ?? 0);

	$price += $custom;

	// Update report status
	mysql_update("
		UPDATE `znote_player_reports`
		SET `status` = {$status}
		WHERE `id` = {$reportId}
		LIMIT 1
	");

	echo "<h1>Report status updated to {$statusTypes[$status]}!</h1>";

	$changelogReportId = intv($_POST['changelogReportId'] ?? 0);
	$changelogValue = $_POST['changelogValue'] ?? '1';
	$changelogText  = esc((string)($_POST['changelogText'] ?? ''));

	if ($changelogReportId > 0 && $changelogValue === '2' && $changelogText !== '') {
		$time = time();

		$existing = mysql_select_single("
			SELECT id FROM znote_changelog
			WHERE report_id = {$changelogReportId}
			LIMIT 1
		");

		if ($existing) {
			mysql_update("
				UPDATE znote_changelog
				SET `text`='{$changelogText}', `time`={$time}
				WHERE id={$existing['id']}
				LIMIT 1
			");
			echo "<h2>Changelog message updated!</h2>";
		} else {
			mysql_insert("
				INSERT INTO znote_changelog (`text`, `time`, `report_id`, `status`)
				VALUES ('{$changelogText}', {$time}, {$changelogReportId}, {$status})
			");
			echo "<h2>Changelog message created!</h2>";
		}

		$cache = new Cache('engine/cache/changelog');
		$cache->setContent(mysql_select_multi("
			SELECT id, text, time, report_id, status
			FROM znote_changelog
			ORDER BY id DESC
		"));
		$cache->save();
	}

	if ($price > 0) {
		$account = mysql_select_single("
			SELECT a.id, a.email
			FROM accounts a
			INNER JOIN players p ON p.account_id = a.id
			WHERE p.name = '{$playerName}'
			LIMIT 1
		");

		if ($account) {
			mysql_insert("
				INSERT INTO znote_paypal
				VALUES ('', {$reportId},
				'report@admin.{$user_data['name']} to {$account['email']}',
				{$account['id']}, 0, {$price})
			");

			$data = mysql_select_single("
				SELECT points FROM znote_accounts
				WHERE account_id = {$account['id']}
			");

			$newPoints = ((int)$data['points']) + $price;
			mysql_update("
				UPDATE znote_accounts
				SET points = {$newPoints}
				WHERE account_id = {$account['id']}
			");

			echo "<p style='color:green;font-size:18px;'>"
			   . h($playerName) . " received {$price} points.</p>";
		}
	}
}

if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
	$reportId = intv($_GET['id']);

	foreach ($reports as $group)
		if (isset($group[$reportId]))
			$report = $group[$reportId];

	if (!isset($report)) {
		echo "<h2>Invalid report.</h2>";
	} else {
		?>
		<div style="width: 320px; margin:auto;">
			<form method="post">
				Player:
				<a target="_blank" href="characterprofile.php?name=<?= urlencode($report['name']) ?>">
					<?= h($report['name']) ?>
				</a>

				<input type="hidden" name="playerName" value="<?= h($report['name']) ?>">
				<input type="hidden" name="id" value="<?= (int)$report['id'] ?>">

				<br>Status:
				<select name="status">
					<?php foreach ($statusTypes as $sid => $label): ?>
						<option value="<?= $sid ?>" <?= $sid == $report['status'] ? 'selected' : '' ?>>
							<?= strip_tags($label) ?>
						</option>
					<?php endforeach; ?>
				</select>

				<br>Give points:
				<select name="price">
					<option value="0">0</option>
					<?php foreach ($config['paypal_prices'] as $p): ?>
						<option value="<?= (int)$p ?>"><?= (int)$p ?></option>
					<?php endforeach; ?>
				</select>
				+ <input name="customPoints" type="number" style="width:60px" value="0">

				<?php if (in_array($report['status'], $statusChangeLog)): ?>
					<br><br>
					<input type="hidden" name="changelogReportId" value="<?= (int)$report['id'] ?>">
					Add changelog?
					<select name="changelogValue">
						<option value="1">No</option>
						<option value="2">Yes</option>
					</select>
					<br>
					<textarea name="changelogText" rows="6" cols="40"></textarea>
				<?php endif; ?>

				<br><br>
				<input type="submit" value="Update Report" style="width:100%;">
			</form>
		</div>
		<?php
	}
}

if (!empty($reports)) {
	echo '<center>';
	foreach ($reports as $statusId => $group) {
		?>
		<h2 class="statusType"><?= $statusTypes[$statusId] ?> (<span id="status-<?= $statusId ?>">Visible</span>)</h2>
		<table class="table tbl" width="100%">
			<tr class="yellow" onclick="toggle(<?= $statusId ?>)">
				<td width="38%">Info</td>
				<td>Description</td>
			</tr>
			<?php foreach ($group as $r): ?>
				<tbody class="row<?= $statusId ?>">
					<tr>
						<td>
							<b>ID:</b> #<?= (int)$r['id'] ?><br>
							<b>Name:</b>
							<a href="characterprofile.php?name=<?= urlencode($r['name']) ?>">
								<?= h($r['name']) ?>
							</a><br>
							<b>Position:</b>
							<input disabled value="/pos <?= (int)$r['posx'] ?>,<?= (int)$r['posy'] ?>,<?= (int)$r['posz'] ?>">
							<br>
							<b>Reported:</b> <?= h(getClock($r['date'], true, true)) ?><br>
							<b>Status:</b> <?= $statusTypes[$r['status']] ?>
							- <a href="?action=edit&id=<?= (int)$r['id'] ?>">Edit</a>
						</td>
						<td><?= h($r['report_description']) ?></td>
					</tr>
				</tbody>
			<?php endforeach; ?>
		</table>
	<?php
	}
	echo '</center>';
} else {
	echo "<h2>No reports submitted.</h2>";
}
?>

<style>
tr.yellow td { font-weight:bold; text-align:center; color:white; }
</style>

<script>
function toggle(id) {
	let rows = document.getElementsByClassName('row' + id);
	let label = document.getElementById('status-' + id);
	let visible = label.innerHTML === 'Visible';
	label.innerHTML = visible ? 'Hidden' : 'Visible';
	for (let r of rows)
		r.style.display = visible ? 'none' : 'table-row-group';
}
<?php foreach ($hideStatus as $s) echo "toggle($s);\n"; ?>
</script>

<?php include 'layout/overall/footer.php'; ?>