<?php ?>

<div class="znx-acct">

<?php if (!empty($guilds) && $guilds !== false): ?>
	<div class="znx-acct-head"><?= t('topguilds.title') ?></div>
	<div class="znx-acct-table-wrap">
	<table id="onlinelistTable" class="znx-acct-table">
	    <tr class="yellow">
			<th>#</th>
	        <th><?= t('online.label_name') ?></th>
	        <th><?= t('topguilds.frags') ?></th>
	    </tr>
	    <?php
		foreach ($guilds as $guild):
		    $url = url("guilds.php?name=". $guild['name']);
			?>
			<tr class="special" onclick="javascript:window.location.href='<?php echo $url; ?>'">
				<td><?php
					echo convert_number_to_words($count);
					$count++;
				?></td>
		        <td><a href="" onclick="return false"><?php echo $guild['name']; ?></a></td>
		        <td><?php echo $guild['frags']; ?></td>
		    </tr>
	    	<?php
		endforeach; ?>
	</table>
	</div>
<?php else: ?>
	<div class="znx-acct-head"><?= t('topguilds.no_frags') ?></div>
<?php endif; ?>

</div><!-- .znx-acct -->
