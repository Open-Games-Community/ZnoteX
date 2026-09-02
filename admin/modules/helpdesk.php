<?php
/**
 * Title: Helpdesk
 * Icon: fa-life-ring
 * Group: Support
 * Order: 20
 * Description: Answer, close and delete player support tickets.
 */

if (!defined('ACP_ROOT')) {
	http_response_code(403);
	die('Direct access denied.');
}

$view = intv($_GET['view'] ?? 0);

// ---------------------------------------------------------------------------
// Ticket actions
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $view > 0) {

	// ----------------------------------------------------------- Post reply
	if (!empty($_POST['reply_text'])) {
		$replyText = sanitize((string)$_POST['reply_text']);
		$username  = sanitize((string)($_POST['username'] ?? 'ADMIN'));

		mysql_insert("
			INSERT INTO `znote_tickets_replies` (`tid`, `username`, `message`, `created`)
			VALUES ({$view}, '" . esc($username) . "', '" . esc($replyText) . "', " . time() . ");
		");

		mysql_update("
			UPDATE `znote_tickets`
			SET `status` = 'Staff-Reply'
			WHERE `id` = {$view}
			LIMIT 1;
		");

		acp_flash_success('Reply posted.');
		acp_redirect('helpdesk', ['view' => $view]);
	}

	$ticketId = intv($_POST['admin_ticket_id'] ?? 0);

	// ---------------------------------------------------------- Close ticket
	if (!empty($_POST['admin_ticket_close']) && $ticketId > 0) {
		mysql_update("UPDATE `znote_tickets` SET `status` = 'CLOSED' WHERE `id` = {$ticketId} LIMIT 1;");
		acp_flash_success('Ticket closed.');
		acp_redirect('helpdesk', ['view' => $view]);
	}

	// ----------------------------------------------------------- Open ticket
	if (!empty($_POST['admin_ticket_open']) && $ticketId > 0) {
		mysql_update("UPDATE `znote_tickets` SET `status` = 'Open' WHERE `id` = {$ticketId} LIMIT 1;");
		acp_flash_success('Ticket reopened.');
		acp_redirect('helpdesk', ['view' => $view]);
	}

	// --------------------------------------------------------- Delete ticket
	if (!empty($_POST['admin_ticket_delete']) && $ticketId > 0) {
		mysql_delete("DELETE FROM `znote_tickets` WHERE `id` = {$ticketId} LIMIT 1;");
		mysql_delete("DELETE FROM `znote_tickets_replies` WHERE `tid` = {$ticketId};");
		acp_flash_success('Ticket #' . $ticketId . ' deleted.');
		acp_redirect('helpdesk');
	}
}

// Pill colour per ticket status.
function acp_ticket_tone(string $status): string {
	switch (strtoupper($status)) {
		case 'CLOSED':      return 'grey';
		case 'STAFF-REPLY': return 'blue';
		case 'OPEN':        return 'green';
		default:            return 'amber';
	}
}
?>

<?php if ($view > 0):

	$ticket = mysql_select_single("SELECT * FROM `znote_tickets` WHERE `id` = {$view} LIMIT 1;");

	if (!is_array($ticket)):
		?>
		<section class="acp-card">
			<div class="acp-card-body">
				<?php acp_empty('That ticket does not exist.', 'fa-question-circle-o'); ?>
				<div class="acp-actions" style="justify-content:center;">
					<a class="acp-btn acp-btn--ghost" href="<?= h(acp_url('helpdesk')) ?>">Back to all tickets</a>
				</div>
			</div>
		</section>
		<?php
	else:
		$ticketId = (int)($ticket['id'] ?? $view);
		$status   = (string)($ticket['status'] ?? '');
		$isClosed = (strtoupper($status) === 'CLOSED');

		$replies = mysql_select_multi("
			SELECT * FROM `znote_tickets_replies`
			WHERE `tid` = {$view}
			ORDER BY `created` ASC;
		");
		?>

		<div class="acp-toolbar">
			<div>
				<strong>Ticket #<?= $ticketId ?></strong>
				&mdash; <?= h((string)($ticket['subject'] ?? '')) ?>
				<span class="acp-pill acp-pill--<?= h(acp_ticket_tone($status)) ?>"><?= h($status) ?></span>
			</div>
			<div class="acp-actions is-tight">
				<a class="acp-btn acp-btn--ghost acp-btn--sm" href="<?= h(acp_url('helpdesk')) ?>">
					<i class="fa fa-arrow-left"></i> All tickets
				</a>
				<form class="acp-inline-form" method="post">
					<?= acp_csrf_field() ?>
					<input type="hidden" name="admin_ticket_id" value="<?= $ticketId ?>">
					<?php if (!$isClosed): ?>
						<button class="acp-btn acp-btn--amber acp-btn--sm" type="submit" name="admin_ticket_close" value="1">
							<i class="fa fa-lock"></i> Close
						</button>
					<?php else: ?>
						<button class="acp-btn acp-btn--green acp-btn--sm" type="submit" name="admin_ticket_open" value="1">
							<i class="fa fa-unlock"></i> Reopen
						</button>
					<?php endif; ?>
				</form>
				<form class="acp-inline-form" method="post" data-confirm="Delete this ticket and every reply on it?">
					<?= acp_csrf_field() ?>
					<input type="hidden" name="admin_ticket_id" value="<?= $ticketId ?>">
					<button class="acp-btn acp-btn--red acp-btn--sm" type="submit" name="admin_ticket_delete" value="1">
						<i class="fa fa-trash"></i> Delete
					</button>
				</form>
			</div>
		</div>

		<article class="acp-post">
			<header class="acp-post-head">
				<span>Opened by <strong><?= h((string)($ticket['username'] ?? '')) ?></strong></span>
				<span><?= h(getClock((int)($ticket['creation'] ?? 0), true)) ?></span>
			</header>
			<div class="acp-post-body"><?= nl2br(h((string)($ticket['message'] ?? ''))) ?></div>
		</article>

		<?php if (is_array($replies) && $replies): ?>
			<?php foreach ($replies as $reply):
				$replyUser = (string)($reply['username'] ?? '');
				$isStaff   = (strtoupper($replyUser) === 'ADMIN');
			?>
				<article class="acp-post<?= $isStaff ? ' acp-post--staff' : '' ?>">
					<header class="acp-post-head">
						<span><?= $isStaff ? '<i class="fa fa-shield"></i> ' : '' ?>Reply by <strong><?= h($replyUser) ?></strong></span>
						<span><?= h(getClock((int)($reply['created'] ?? 0), true)) ?></span>
					</header>
					<div class="acp-post-body"><?= nl2br(h((string)($reply['message'] ?? ''))) ?></div>
				</article>
			<?php endforeach; ?>
		<?php endif; ?>

		<?php if (!$isClosed): ?>
			<section class="acp-card">
				<header class="acp-card-head"><h2>Reply</h2></header>
				<div class="acp-card-body">
					<form method="post">
						<?= acp_csrf_field() ?>
						<input type="hidden" name="username" value="ADMIN">
						<div class="acp-field">
							<textarea class="acp-textarea" name="reply_text" rows="7" placeholder="Write your reply&hellip;" required></textarea>
						</div>
						<div class="acp-actions">
							<button class="acp-btn" type="submit"><i class="fa fa-reply"></i> Post reply</button>
						</div>
					</form>
				</div>
			</section>
		<?php else: ?>
			<section class="acp-card">
				<div class="acp-card-body">
					<?php acp_empty('This ticket is closed. Reopen it to reply.', 'fa-lock'); ?>
				</div>
			</section>
		<?php endif; ?>

	<?php endif; ?>

<?php else:

	$tickets = mysql_select_multi("
		SELECT `id`, `subject`, `username`, `creation`, `status`
		FROM `znote_tickets`
		ORDER BY `creation` DESC;
	");
	?>

	<section class="acp-card">
		<header class="acp-card-head">
			<h2>All tickets</h2>
			<p>Newest first</p>
		</header>
		<div class="acp-card-body is-flush">
			<?php if (is_array($tickets) && $tickets): ?>
				<div class="acp-table-wrap">
					<table class="acp-table">
						<thead>
							<tr>
								<th>#</th>
								<th>Subject</th>
								<th>Opened by</th>
								<th>Created</th>
								<th>Status</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($tickets as $ticket):
								$id     = (int)($ticket['id'] ?? 0);
								$status = (string)($ticket['status'] ?? '');
							?>
								<tr>
									<td class="is-muted"><?= $id ?></td>
									<td><a href="<?= h(acp_url('helpdesk', ['view' => $id])) ?>"><?= h((string)($ticket['subject'] ?? '')) ?></a></td>
									<td><?= h((string)($ticket['username'] ?? '')) ?></td>
									<td class="is-nowrap is-muted"><?= h(getClock((int)($ticket['creation'] ?? 0), true)) ?></td>
									<td><span class="acp-pill acp-pill--<?= h(acp_ticket_tone($status)) ?>"><?= h($status) ?></span></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<?php acp_empty('No helpdesk tickets have been submitted.', 'fa-life-ring'); ?>
			<?php endif; ?>
		</div>
	</section>

<?php endif; ?>
