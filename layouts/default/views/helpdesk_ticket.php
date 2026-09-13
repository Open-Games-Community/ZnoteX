<?php
?>
<h1>View Ticket #
<?php
	echo $ticketData['id'];
	if ($ticketData['status'] === 'CLOSED') {
		echo '<span style="color:red">[CLOSED]</SPAN>';
	}
?></h1>
<table class="znoteTable ThreadTable table table-striped">
	<tr class="yellow">
		<th>
			<?php
				echo getClock($ticketData['creation'], true);
			?>
			 - Created by:
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
<?php
if ($replies !== false) {
	foreach($replies as $reply) {
		?>
		<table class="znoteTable ThreadTable table table-striped">
			<tr class="yellow">
				<th>
					<?php
						echo getClock($reply['created'], true);
					?>
					 - Posted by:
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
		<hr class="bighr">
	<?php
	}
}
?>

<?php if ($ticketData['status'] !== 'CLOSED') { ?>
	<form action="" method="post">
		<input type="hidden" name="username" value="<?php echo $ticketData['username']; ?>"><br>
		<textarea class="forumReply" name="reply_text" style="width: 610px; height: 150px"></textarea><br>
		<input name="" type="submit" value="<?= t('helpdesk.reply') ?>" class="btn btn-primary">
	</form>
<?php } ?>
