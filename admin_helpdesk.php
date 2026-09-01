<?php
require_once 'engine/init.php';
include 'layout/overall/header.php';

protect_page();
admin_only($user_data);

/**
 * HTML escape helper (XSS protection)
 */
function h(string $s): string {
	return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * Escape for SQL with your engine's mysqli escape
 * (your connect.php provides mysql_znote_escape_string)
 */
function esc(string $s): string {
	return mysql_znote_escape_string($s);
}

/**
 * Safe int getter
 */
function int_from($value): int {
	return (is_numeric($value)) ? (int)$value : 0;
}

$view = isset($_GET['view']) ? int_from($_GET['view']) : 0;

// --------------------
// VIEW A TICKET
// --------------------
if ($view > 0) {

	// ---------- Handle POST actions ----------
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {

		// Reply
		if (!empty($_POST['reply_text'])) {
			$replyText = (string)$_POST['reply_text'];
			// Keep your existing sanitize call if it exists in your engine
			if (function_exists('sanitize')) {
				sanitize($replyText);
			}

			$username = (string)($_POST['username'] ?? 'ADMIN');
			// If getValue() exists in your engine, use it; otherwise fallback to raw.
			if (function_exists('getValue')) {
				$username = (string)getValue($username);
				$replyText = (string)getValue($replyText);
			}

			$usernameSql = esc($username);
			$messageSql  = esc($replyText);
			$created     = time();

			mysql_insert("
				INSERT INTO `znote_tickets_replies` (`tid`, `username`, `message`, `created`)
				VALUES ({$view}, '{$usernameSql}', '{$messageSql}', {$created})
			");

			mysql_update("
				UPDATE `znote_tickets`
				SET `status` = 'Staff-Reply'
				WHERE `id` = {$view}
				LIMIT 1
			");

			// Avoid form resubmission
			header("Location: admin_helpdesk.php?view={$view}");
			exit;
		}

		// Close ticket
		if (!empty($_POST['admin_ticket_close'])) {
			$ticketId = isset($_POST['admin_ticket_id']) ? int_from($_POST['admin_ticket_id']) : 0;
			if ($ticketId > 0) {
				mysql_update("
					UPDATE `znote_tickets`
					SET `status` = 'CLOSED'
					WHERE `id` = {$ticketId}
					LIMIT 1
				");
			}
			header("Location: admin_helpdesk.php?view={$view}");
			exit;
		}

		// Open ticket
		if (!empty($_POST['admin_ticket_open'])) {
			$ticketId = isset($_POST['admin_ticket_id']) ? int_from($_POST['admin_ticket_id']) : 0;
			if ($ticketId > 0) {
				mysql_update("
					UPDATE `znote_tickets`
					SET `status` = 'Open'
					WHERE `id` = {$ticketId}
					LIMIT 1
				");
			}
			header("Location: admin_helpdesk.php?view={$view}");
			exit;
		}

		// Delete ticket
		if (!empty($_POST['admin_ticket_delete'])) {
			$ticketId = isset($_POST['admin_ticket_id']) ? int_from($_POST['admin_ticket_id']) : 0;
			if ($ticketId > 0) {
				mysql_delete("DELETE FROM `znote_tickets` WHERE `id` = {$ticketId} LIMIT 1;");
				// Optional: also delete replies (recommended cleanup)
				mysql_delete("DELETE FROM `znote_tickets_replies` WHERE `tid` = {$ticketId};");
			}
			header("Location: admin_helpdesk.php");
			exit;
		}
	}

	// ---------- Load ticket ----------
	$ticketData = mysql_select_single("SELECT * FROM `znote_tickets` WHERE `id` = {$view} LIMIT 1;");
	if (!$ticketData) {
		echo '<p>' . h('You can not view this ticket!') . '</p>';
		include 'layout/overall/footer.php';
		exit;
	}

	$ticketId   = (int)($ticketData['id'] ?? $view);
	$createdAt  = (int)($ticketData['creation'] ?? 0);
	$status     = (string)($ticketData['status'] ?? '');
	$username   = (string)($ticketData['username'] ?? '');
	$message    = (string)($ticketData['message'] ?? '');

	?>
	<h1>View Ticket #<?= (int)$ticketId ?></h1>

	<table class="znoteTable ThreadTable table table-striped">
		<tr class="yellow">
			<th>
				<?= h(getClock($createdAt, true)) ?>
				&nbsp;- Created by: <?= h($username) ?>
			</th>
		</tr>
		<tr>
			<td>
				<p><?= nl2br(h($message), false) ?></p>
			</td>
		</tr>
	</table>

	<?php
	// ---------- Load replies ----------
	$replies = mysql_select_multi("
		SELECT * FROM `znote_tickets_replies`
		WHERE `tid` = {$view}
		ORDER BY `created` ASC
	");

	if (is_array($replies) && !empty($replies)) {
		foreach ($replies as $reply) {
			$rCreated  = (int)($reply['created'] ?? 0);
			$rUser     = (string)($reply['username'] ?? '');
			$rMessage  = (string)($reply['message'] ?? '');
			?>
			<table class="znoteTable ThreadTable table table-striped">
				<tr class="yellow">
					<th>
						<?= h(getClock($rCreated, true)) ?>
						&nbsp;- Posted by: <?= h($rUser) ?>
					</th>
				</tr>
				<tr>
					<td>
						<p><?= nl2br(h($rMessage), false) ?></p>
					</td>
				</tr>
			</table>
			<?php
		}
	}
	?>

	<!-- Open/Close/Delete Ticket -->
	<table class="znoteTable ThreadTable table table-striped">
		<tr>
			<td>
				<form action="" method="post" style="text-align:center;">
					<input type="hidden" name="admin_ticket_id" value="<?= (int)$ticketId ?>">
					<?php if ($status !== 'CLOSED') { ?>
						<input type="submit" name="admin_ticket_close" value="Close Ticket" class="btn btn-warning">
					<?php } else { ?>
						<input type="submit" name="admin_ticket_open" value="Open Ticket" class="btn btn-success">
					<?php } ?>
				</form>
			</td>
			<td>
				<form action="" method="post" style="text-align:center;" onsubmit="return confirm('Are you sure you want to delete this ticket?');">
					<input type="hidden" name="admin_ticket_id" value="<?= (int)$ticketId ?>">
					<input type="submit" name="admin_ticket_delete" value="Delete Ticket" class="btn btn-danger">
				</form>
			</td>
		</tr>
	</table>

	<?php if ($status !== 'CLOSED') { ?>
		<hr class="bighr">
		<form action="" method="post">
			<input type="hidden" name="username" value="ADMIN">
			<textarea class="forumReply" name="reply_text" style="width: 610px; height: 150px"></textarea><br>
			<input type="submit" value="Post Reply" class="btn btn-primary">
		</form>
	<?php } ?>

	<?php

// --------------------
// LIST TICKETS
// --------------------
} else {

	echo '<h1>Latest Tickets</h1>';

	$tickets = mysql_select_multi("
		SELECT `id`, `subject`, `creation`, `status`
		FROM `znote_tickets`
		ORDER BY `creation` DESC
	");

	if (is_array($tickets) && !empty($tickets)) {
		?>
		<table>
			<tr class="yellow">
				<td>ID:</td>
				<td>Subject:</td>
				<td>Creation:</td>
				<td>Status:</td>
			</tr>
			<?php foreach ($tickets as $ticket): 
				$id = (int)($ticket['id'] ?? 0);
				$subject = (string)($ticket['subject'] ?? '');
				$creation = (int)($ticket['creation'] ?? 0);
				$status = (string)($ticket['status'] ?? '');
			?>
				<tr class="special">
					<td><?= $id ?></td>
					<td><a href="admin_helpdesk.php?view=<?= $id ?>"><?= h($subject) ?></a></td>
					<td><?= h(getClock($creation, true)) ?></td>
					<td><?= h($status) ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	} else {
		echo h('No helpdesk tickets has been submitted.');
	}
}

include 'layout/overall/footer.php'; ?>
