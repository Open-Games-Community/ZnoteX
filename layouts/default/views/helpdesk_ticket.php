<?php
?>
<div class="znx-acct">

<div class="znx-acct-head"><?= t('helpdesk.view_ticket') ?>
<?php
	echo $ticketData['id'];
	if ($ticketData['status'] === 'CLOSED') {
		echo '<span style="color:red">[' . t('helpdesk.closed') . ']</span>';
	}
?></div>
<div class="znx-acct-table-wrap">
<table class="znx-acct-table">
	<tr class="yellow">
		<th>
			<?php
				echo getClock($ticketData['creation'], true);
			?>
			 - <?= t('helpdesk.created_by') ?>
			 <?php
			 	echo $ticketData['username'];
			 ?>
		</th>
	</tr>
	<tr>
		<td>
			<p><?php echo nl2br($ticketData['message']); ?></p>
		</td>
	</tr>
</table>
</div>
<?php
if ($replies !== false) {
	foreach($replies as $reply) {
		?>
		<div class="znx-acct-table-wrap">
		<table class="znx-acct-table">
			<tr class="yellow">
				<th>
					<?php
						echo getClock($reply['created'], true);
					?>
					 - <?= t('helpdesk.posted_by') ?>
					 <?php
					 	echo $reply['username'];
					 ?>
				</th>
			</tr>
			<tr>
				<td>
					<p><?php echo nl2br($reply['message']); ?></p>
				</td>
			</tr>
		</table>
		</div>
	<?php
	}
}
?>

<?php if ($ticketData['status'] !== 'CLOSED') { ?>
	<form action="" method="post" class="znx-editchar-box">
		<input type="hidden" name="username" value="<?php echo $ticketData['username']; ?>">
		<textarea class="znx-editchar-textarea forumReply" name="reply_text"></textarea>
		<button type="submit" class="znx-acct-btn"><?= t('helpdesk.reply') ?></button>
	</form>
<?php } ?>

</div><!-- .znx-acct -->
