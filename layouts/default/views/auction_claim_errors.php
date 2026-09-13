<table class="auction_error">
	<tr class="yellow">
		<td>#</td>
		<td><?= t('auc.claim_issues') ?></td>
	</tr>
	<?php foreach($errors as $i => $error): ?>
		<tr>
			<td><?php echo $i+1; ?></td>
			<td><?php echo $error; ?></td>
		</tr>
	<?php endforeach; ?>
</table>
